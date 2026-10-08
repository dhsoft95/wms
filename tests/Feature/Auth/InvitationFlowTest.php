<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\AcceptInvitation;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class InvitationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_invited_user_can_accept_invitation_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'status'   => 'invited',
            'password' => null,
        ]);
        $token = $user->generateInvitationToken();

        Livewire::test(AcceptInvitation::class, ['user' => $user, 'token' => $token])
            ->assertSet('tokenValid', true)
            ->set('password', 'Password1!')
            ->set('password_confirmation', 'Password1!')
            ->call('save');

        $user->refresh();
        $this->assertSame('active', $user->status);
        $this->assertNull($user->invitation_token);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_expired_invitation_token_shows_invalid(): void
    {
        $user = User::factory()->create([
            'status'             => 'invited',
            'invitation_token'   => hash('sha256', 'sometoken'),
            'invitation_sent_at' => now()->subHours(73),
            'password'           => null,
        ]);

        Livewire::test(AcceptInvitation::class, ['user' => $user, 'token' => 'sometoken'])
            ->assertSet('tokenValid', false);
    }

    public function test_already_active_user_cannot_use_invitation_link(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        Livewire::test(AcceptInvitation::class, ['user' => $user, 'token' => 'anytoken'])
            ->assertSet('tokenValid', false);
    }
}
