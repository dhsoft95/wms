<?php

namespace App\Livewire\Admin\Users;

use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterRole = '';
    public string $filterDepartment = '';
    public string $filterStatus = '';
    public ?int $confirmingDelete = null;
    public ?int $confirmingReset2FA = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatingFilterDepartment(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::with(['roles', 'department'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterRole, function ($query) {
                $query->whereHas('roles', fn ($q) => $q->where('name', $this->filterRole));
            })
            ->when($this->filterDepartment, function ($query) {
                $query->where('department_id', $this->filterDepartment);
            })
            ->when($this->filterStatus, function ($query) {
                $query->where('status', $this->filterStatus);
            })
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function departments(): \Illuminate\Database\Eloquent\Collection
    {
        return Department::orderBy('name')->get();
    }

    public function suspend(int $id): void
    {
        abort_if(auth()->id() === $id, 403, 'You cannot suspend yourself.');

        $user = User::findOrFail($id);
        $this->authorize('suspend', $user);

        $user->update(['status' => 'suspended']);
        $this->dispatch('toast', message: $user->name . ' has been suspended.', type: 'info');
    }

    public function activate(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        $user->update(['status' => 'active']);
        $this->dispatch('toast', message: $user->name . ' has been activated.', type: 'success');
    }

    public function resendInvitation(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        $user->update(['invitation_sent_at' => now()]);
        // Notification dispatched here — e.g. $user->notify(new \App\Notifications\UserInvitationNotification($user));
        $this->dispatch('toast', message: 'Invitation resent to ' . $user->email . '.', type: 'success');
    }

    public function deleteUser(int $id): void
    {
        $user = User::findOrFail($id);

        abort_if(auth()->id() === $id, 403, 'You cannot delete yourself.');
        abort_if(
            $user->hasRole('system-admin') && User::role('system-admin')->where('status', 'active')->count() <= 1,
            403,
            'Cannot delete the last system administrator.'
        );

        $this->authorize('delete', $user);

        $user->delete();
        $this->confirmingDelete = null;
        $this->dispatch('toast', message: 'User deleted successfully.', type: 'success');
    }

    public function resetTwoFactor(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        $user->update([
            'two_factor_method'        => null,
            'two_factor_confirmed_at'  => null,
            'require_two_factor'       => false,
        ]);

        $this->confirmingReset2FA = null;
        $this->dispatch('toast', message: '2FA reset for ' . $user->name . '.', type: 'info');
    }

    public function render(): View
    {
        return view('livewire.admin.users.index')
            ->layout('components.layouts.app', [
                'title'           => 'Users',
                'breadcrumbGroup' => 'Admin',
            ]);
    }
}
