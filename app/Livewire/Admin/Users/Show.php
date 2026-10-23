<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Show extends Component
{
    public User $user;

    public bool   $showPasswordModal        = false;
    public string $newPassword              = '';
    public string $newPasswordConfirmation  = '';

    public function openPasswordModal(): void
    {
        $this->newPassword             = '';
        $this->newPasswordConfirmation = '';
        $this->resetValidation();
        $this->showPasswordModal = true;
    }

    public function closePasswordModal(): void
    {
        $this->showPasswordModal = false;
    }

    public function updatePassword(): void
    {
        $this->authorize('update', $this->user);

        $this->validate([
            'newPassword' => ['required', 'string', 'min:8', 'same:newPasswordConfirmation'],
            'newPasswordConfirmation' => ['required', 'string'],
        ], [
            'newPassword.same' => 'Passwords do not match.',
        ]);

        $this->user->update(['password' => Hash::make($this->newPassword)]);

        $this->showPasswordModal = false;
        $this->dispatch('toast', message: 'Password updated for ' . $this->user->name . '.', type: 'success');
    }

    public function mount(User $user): void
    {
        $this->authorize('view', $user);

        $this->user = $user->load(['roles', 'department', 'invitedBy']);
    }

    public function suspend(): void
    {
        $this->authorize('update', $this->user);

        $this->user->update(['status' => 'suspended']);
        $this->dispatch('toast', message: $this->user->name . ' has been suspended.', type: 'info');
    }

    public function activate(): void
    {
        $this->authorize('update', $this->user);

        $this->user->update(['status' => 'active']);
        $this->dispatch('toast', message: $this->user->name . ' has been activated.', type: 'success');
    }

    public function resendInvitation(): void
    {
        $this->authorize('update', $this->user);

        $this->user->update(['invitation_sent_at' => now()]);
        // $this->user->notify(new \App\Notifications\UserInvitationNotification($this->user));
        $this->dispatch('toast', message: 'Invitation resent to ' . $this->user->email . '.', type: 'success');
    }

    public function resetTwoFactor(): void
    {
        $this->authorize('update', $this->user);

        $this->user->update([
            'two_factor_method'       => null,
            'two_factor_confirmed_at' => null,
            'require_two_factor'      => false,
        ]);

        $this->dispatch('toast', message: '2FA has been reset for ' . $this->user->name . '.', type: 'info');
    }

    public function render(): View
    {
        return view('livewire.admin.users.show')
            ->layout('components.layouts.app', [
                'title'           => $this->user->name,
                'breadcrumbGroup' => 'Admin',
            ]);
    }
}
