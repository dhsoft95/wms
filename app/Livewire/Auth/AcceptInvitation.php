<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AcceptInvitation extends Component
{
    #[Locked]
    public int $userId;

    #[Locked]
    public string $token;

    public string $password = '';
    public string $password_confirmation = '';

    public bool $tokenValid = false;

    public function mount(User $user, string $token): void
    {
        $this->userId = $user->id;
        $this->token  = $token;

        if (! $user->isInvited()) {
            // Link already used
            $this->tokenValid = false;
            return;
        }

        $this->tokenValid = $user->hasValidInvitationToken($token);
    }

    public function save(): void
    {
        $user = User::find($this->userId);

        if (! $user || ! $user->hasValidInvitationToken($this->token)) {
            throw ValidationException::withMessages([
                'token' => 'This invitation link is no longer valid.',
            ]);
        }

        $this->validate([
            'password'              => ['required', 'confirmed', Password::defaults()],
            'password_confirmation' => ['required'],
        ]);

        $user->update([
            'password'          => $this->password,
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $user->clearInvitationToken();
        $user->notify(new PasswordChangedNotification());

        Auth::login($user);

        if ($user->require_two_factor) {
            $this->redirect(route('profile.index', ['tab' => '2fa']), navigate: true);
            return;
        }

        if ($user->hasRole('scanner-operator')) {
            $this->redirect(route('scan.home'), navigate: true);
            return;
        }

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.accept-invitation', [
            'invitedUser' => User::find($this->userId),
        ])->layout('components.layouts.auth');
    }
}
