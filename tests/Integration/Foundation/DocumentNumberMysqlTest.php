<?php

/**
 * MySQL-only concurrency tests for DocumentNumber::next().
 *
 * Run with:
 *   php artisan test --group=mysql
 *
 * Requires a real MySQL / MariaDB connection with InnoDB.
 * Tests are automatically skipped when the default connection is SQLite.
 *
 * These tests use DatabaseMigrations (not RefreshDatabase) so that
 * DocumentNumber::next() — which commits its own transaction — can be
 * read by separate raw PDO connections on the same database.
 */

use App\Models\DocumentSequence;
use App\Services\Foundation\DocumentNumber;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class)->group('mysql');

// ─── Helpers ─────────────────────────────────────────────────────────────────

function rawMysqlPdo(array $pdoOptions = []): PDO
{
    $c = config('database.connections.' . config('database.default'));

    $dsn = ! empty($c['unix_socket'])
        ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $c['unix_socket'], $c['database'])
        : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['port'] ?? 3306, $c['database']);

    return new PDO($dsn, $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION] + $pdoOptions);
}

function seedGrnSequence(): void
{
    DB::table('document_sequences')->insert([
        'type'         => 'grn',
        'prefix'       => 'GRN',
        'next_number'  => 1,
        'padding'      => 4,
        'include_year' => false,
        'reset_yearly' => false,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

test('lockForUpdate blocks a second connection until the first commits', function () {
    seedGrnSequence();

    // Connection A: open a transaction and hold the FOR UPDATE lock
    $connA = rawMysqlPdo();
    $connA->beginTransaction();
    $connA->prepare('SELECT id FROM document_sequences WHERE type = ? FOR UPDATE')->execute(['grn']);

    // Connection B: 1-second lock-wait timeout — must time out because A holds the lock
    $connB = rawMysqlPdo();
    $connB->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $connB->beginTransaction();

    $wasBlocked = false;
    try {
        $connB->prepare('SELECT id FROM document_sequences WHERE type = ? FOR UPDATE')->execute(['grn']);
    } catch (PDOException $e) {
        $wasBlocked = str_contains($e->getMessage(), 'wait timeout')
                   || str_contains($e->getMessage(), 'Deadlock')
                   || str_contains($e->getMessage(), '1205');
    } finally {
        try { $connB->rollBack(); } catch (Throwable) {}
    }

    expect($wasBlocked)->toBeTrue('A second connection must time out while A holds lockForUpdate.');

    $connA->commit(); // Release A's lock
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'Requires MySQL / MariaDB with InnoDB.');

test('second caller gets the next sequential number after the first commits', function () {
    seedGrnSequence();

    // Connection A simulates a caller that already incremented next_number to 2
    $connA = rawMysqlPdo();
    $connA->beginTransaction();
    $connA->prepare('SELECT * FROM document_sequences WHERE type = ? FOR UPDATE')->execute(['grn']);
    $connA->exec("UPDATE document_sequences SET next_number = 2, updated_at = NOW() WHERE type = 'grn'");
    $connA->commit();

    // DocumentNumber::next() now reads next_number = 2, returns GRN-0002, sets it to 3
    $number = DocumentNumber::next('grn');

    expect($number)->toBe('GRN-0002');
    expect(DB::table('document_sequences')->where('type', 'grn')->value('next_number'))->toBe(3);
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'Requires MySQL / MariaDB with InnoDB.');

test('two sequential calls always produce unique ascending numbers', function () {
    seedGrnSequence();

    $first  = DocumentNumber::next('grn');
    $second = DocumentNumber::next('grn');

    expect($first)->toBe('GRN-0001')
        ->and($second)->toBe('GRN-0002')
        ->and($first)->not->toBe($second);
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'Requires MySQL / MariaDB with InnoDB.');
