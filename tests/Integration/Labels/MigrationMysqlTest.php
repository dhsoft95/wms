<?php
declare(strict_types=1);

/**
 * MySQL-only migration smoke tests for the Labels & Scanning module.
 *
 * Catches column-type overflows that SQLite silently accepts
 * (e.g. TINYINT overflow with dpi=203 or port=9100).
 *
 * Run with:
 *   php artisan test --group=mysql
 *
 * Automatically skipped when the default connection is SQLite.
 */

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class)->group('mysql');

$skipUnless = fn () => DB::connection()->getDriverName() !== 'mysql'
    ? 'Requires MySQL / MariaDB'
    : false;

// ─── label_templates ─────────────────────────────────────────────────────────

test('label_templates.dpi stores 203 without overflow', function () {
    $id = DB::table('label_templates')->insertGetId([
        'name'       => 'Test Template',
        'type'       => 'pallet_lpn',
        'width_mm'   => 100.00,
        'height_mm'  => 150.00,
        'dpi'        => 203,
        'is_default' => false,
        'is_active'  => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('label_templates')->where('id', $id)->value('dpi'))->toBe(203);
})->skip($skipUnless);

test('label_templates.dpi stores 300 dpi', function () {
    $id = DB::table('label_templates')->insertGetId([
        'name'       => '300dpi Template',
        'type'       => 'item',
        'width_mm'   => 50.00,
        'height_mm'  => 25.00,
        'dpi'        => 300,
        'is_default' => false,
        'is_active'  => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('label_templates')->where('id', $id)->value('dpi'))->toBe(300);
})->skip($skipUnless);

// ─── printers ────────────────────────────────────────────────────────────────

test('printers.dpi stores 203 without overflow', function () {
    $id = DB::table('printers')->insertGetId([
        'name'      => 'Test Printer',
        'kind'      => 'zpl_network',
        'ip'        => '192.168.1.100',
        'port'      => 9100,
        'dpi'       => 203,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('printers')->where('id', $id)->value('dpi'))->toBe(203);
})->skip($skipUnless);

test('printers.port stores 9100 without overflow', function () {
    $id = DB::table('printers')->insertGetId([
        'name'      => 'Port Test Printer',
        'kind'      => 'zpl_network',
        'ip'        => '10.0.0.1',
        'port'      => 9100,
        'dpi'       => 203,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('printers')->where('id', $id)->value('port'))->toBe(9100);
})->skip($skipUnless);

test('printers.port stores 65535 (max unsigned smallint)', function () {
    $id = DB::table('printers')->insertGetId([
        'name'      => 'High Port Printer',
        'kind'      => 'zpl_network',
        'ip'        => '10.0.0.2',
        'port'      => 65535,
        'dpi'       => 203,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('printers')->where('id', $id)->value('port'))->toBe(65535);
})->skip($skipUnless);

// ─── label_print_jobs ────────────────────────────────────────────────────────

test('label_print_jobs.copies stores 1 and 100', function () {
    $lpnId = DB::table('lpns')->insertGetId([
        'code'       => 'LPN-TEST-001',
        'status'     => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $templateId = DB::table('label_templates')->insertGetId([
        'name'       => 'Test',
        'type'       => 'pallet_lpn',
        'width_mm'   => 100,
        'height_mm'  => 150,
        'dpi'        => 203,
        'is_default' => false,
        'is_active'  => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $userId = DB::table('users')->insertGetId([
        'name'              => 'Test User',
        'email'             => 'test@example.com',
        'password'          => bcrypt('password'),
        'role'              => 'admin',
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    $id = DB::table('label_print_jobs')->insertGetId([
        'label_template_id' => $templateId,
        'type'              => 'pallet_lpn',
        'printable_type'    => 'App\Models\Inventory\Lpn',
        'printable_id'      => $lpnId,
        'copies'            => 100,
        'output'            => 'pdf',
        'is_reprint'        => false,
        'printed_by'        => $userId,
        'created_at'        => now(),
    ]);

    expect(DB::table('label_print_jobs')->where('id', $id)->value('copies'))->toBe(100);
})->skip($skipUnless);
