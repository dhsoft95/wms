<?php
declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('storekeeper has scan.receive permission', function () {
    $role = Role::findByName('storekeeper');
    expect($role->hasPermissionTo('scan.receive'))->toBeTrue();
});

it('storekeeper has scan.putaway permission', function () {
    $role = Role::findByName('storekeeper');
    expect($role->hasPermissionTo('scan.putaway'))->toBeTrue();
});

it('storekeeper has scan.gate permission', function () {
    $role = Role::findByName('storekeeper');
    expect($role->hasPermissionTo('scan.gate'))->toBeTrue();
});

it('scanner-operator has scan.receive permission', function () {
    $role = Role::findByName('scanner-operator');
    expect($role->hasPermissionTo('scan.receive'))->toBeTrue();
});

it('scanner-operator has scan.move and scan.lookup', function () {
    $role = Role::findByName('scanner-operator');
    expect($role->hasPermissionTo('scan.move'))->toBeTrue();
    expect($role->hasPermissionTo('scan.lookup'))->toBeTrue();
});

it('scanner-operator does not have scan.stock-lookup (removed)', function () {
    expect(\Spatie\Permission\Models\Permission::where('name', 'scan.stock-lookup')->exists())->toBeFalse();
});

it('warehouse-supervisor has all scan permissions', function () {
    $role = Role::findByName('warehouse-supervisor');
    foreach (['scan.receive', 'scan.putaway', 'scan.pick', 'scan.dispatch', 'scan.gate', 'scan.move', 'scan.lookup'] as $perm) {
        expect($role->hasPermissionTo($perm))->toBeTrue("Missing: {$perm}");
    }
});

it('gate-security has scan.gate', function () {
    $role = Role::findByName('gate-security');
    expect($role->hasPermissionTo('scan.gate'))->toBeTrue();
});

it('gate-security does not have scan.receive', function () {
    $role = Role::findByName('gate-security');
    expect($role->hasPermissionTo('scan.receive'))->toBeFalse();
});
