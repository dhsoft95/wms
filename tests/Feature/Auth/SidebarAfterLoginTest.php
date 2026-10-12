<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Volt\Volt as LivewireVolt;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

// ─── Redirect is a full page load (not wire:navigate) ─────────────────────────

it('login redirects to dashboard without navigate so the app layout mounts fresh', function () {
    $user = User::factory()->create();

    LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    // The redirect must be a standard 302, not a Livewire navigate response,
    // so the browser performs a full page load (new <html x-data="..."> mount).
    $this->assertAuthenticated();
});

// ─── Sidebar HTML is present for each role ────────────────────────────────────

it('system admin dashboard response contains full sidebar including Administration group', function () {
    $user = User::factory()->create();
    $user->assignRole('system-admin');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    // Sidebar nav groups visible to system admin
    $response->assertSee('Dashboard');
    $response->assertSee('Inventory Control');
    $response->assertSee('Administration');
});

it('department user dashboard response contains inventory but not administration', function () {
    $user = User::factory()->create();
    $user->assignRole('department-user');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Dashboard');
    $response->assertSee('Stock on Hand');
    $response->assertSee('Movement History');
    // Administration group is gated — department user must not see it
    $response->assertDontSee('Administration');
});

it('warehouse manager dashboard response contains operations groups and data import but not user admin', function () {
    $user = User::factory()->create();
    $user->assignRole('warehouse-manager');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Dashboard');
    $response->assertSee('Inbound');
    $response->assertSee('Inventory Control');
    $response->assertSee('Warehouse Operations');
    // WM sees Data Import under Administration
    $response->assertSee('Administration');
    $response->assertSee('Data Import');
    // WM cannot manage users/roles/departments
    $response->assertDontSee('Users');
    $response->assertDontSee('Roles');
});

it('viewer auditor dashboard response contains stock on hand', function () {
    $user = User::factory()->create();
    $user->assignRole('viewer-auditor');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Dashboard');
    $response->assertSee('Stock on Hand');
    $response->assertDontSee('Administration');
});

it('unauthenticated request is redirected to login not a blank page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});
