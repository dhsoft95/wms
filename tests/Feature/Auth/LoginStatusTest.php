<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LoginStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_invited_user_cannot_log_in(): void
    {
        User::factory()->create([
            'email'    => 'invited@example.com',
            'password' => Hash::make('Password1!'),
            'status'   => 'invited',
        ]);

        Volt::test('auth.login')
            ->set('email', 'invited@example.com')
            ->set('password', 'Password1!')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        User::factory()->create([
            'email'    => 'suspended@example.com',
            'password' => Hash::make('Password1!'),
            'status'   => 'suspended',
        ]);

        Volt::test('auth.login')
            ->set('email', 'suspended@example.com')
            ->set('password', 'Password1!')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    public function test_active_user_can_log_in(): void
    {
        User::factory()->create([
            'email'    => 'active@example.com',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);

        Volt::test('auth.login')
            ->set('email', 'active@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_public_register_route_is_disabled(): void
    {
        $this->get('/register')->assertStatus(404);
    }
}
