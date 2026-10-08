<?php

namespace App\Livewire\Admin\Users;

use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Edit extends Component
{
    public User $user;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $role = '';
    public string $departmentId = '';
    public bool $requireTwoFactor = false;
    public string $status = '';

    public function mount(User $user): void
    {
        $this->authorize('update', $user);

        $this->user = $user->load(['roles', 'department', 'invitedBy']);

        $this->name             = $user->name;
        $this->email            = $user->email;
        $this->phone            = $user->phone ?? '';
        $this->role             = $user->roleName() ?? '';
        $this->departmentId     = (string) ($user->department_id ?? '');
        $this->requireTwoFactor = (bool) $user->require_two_factor;
        $this->status           = $user->status;
    }

    #[Computed]
    public function departments(): \Illuminate\Database\Eloquent\Collection
    {
        return Department::orderBy('name')->get();
    }

    #[Computed]
    public function showDepartment(): bool
    {
        return $this->role === 'department-user';
    }

    protected function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user->id)],
            'phone'            => ['nullable', 'string', 'max:30'],
            'role'             => ['required', 'string', Rule::in([
                'system-admin',
                'warehouse-manager',
                'department-user',
                'storekeeper',
                'scanner-operator',
                'viewer-auditor',
            ])],
            'departmentId'     => ['required_if:role,department-user', 'nullable', 'exists:departments,id'],
            'requireTwoFactor' => ['boolean'],
            'status'           => ['required', Rule::in(['invited', 'active', 'suspended'])],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required'            => 'A full name is required.',
            'email.required'           => 'An email address is required.',
            'email.email'              => 'Please enter a valid email address.',
            'email.unique'             => 'This email address is already registered.',
            'role.required'            => 'Please assign a role to this user.',
            'role.in'                  => 'Please select a valid role.',
            'departmentId.required_if' => 'A department is required for Department User role.',
            'departmentId.exists'      => 'The selected department does not exist.',
            'status.required'          => 'A status is required.',
            'status.in'                => 'Please select a valid status.',
        ];
    }

    public function save(): void
    {
        $this->authorize('update', $this->user);

        $data = $this->validate();

        $emailChanged = $data['email'] !== $this->user->email;

        $this->user->update([
            'name'               => $data['name'],
            'email'              => $data['email'],
            'phone'              => $data['phone'] ?: null,
            'department_id'      => $data['departmentId'] ?: null,
            'require_two_factor' => $data['requireTwoFactor'],
            'status'             => $data['status'],
            // Reset email verification if address changed
            'email_verified_at'  => $emailChanged ? null : $this->user->email_verified_at,
        ]);

        // Sync role via Spatie
        $this->user->syncRoles([$data['role']]);

        // If email changed and the user was invited, re-send invitation
        if ($emailChanged && $this->user->isInvited()) {
            $this->user->update(['invitation_sent_at' => now()]);
            // $this->user->notify(new \App\Notifications\UserInvitationNotification($this->user));
        }

        $this->dispatch('toast', message: $this->user->name . ' updated successfully.', type: 'success');

        $this->redirect(route('admin.users.show', $this->user), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.users.edit')
            ->layout('components.layouts.app', [
                'title'           => 'Edit User',
                'breadcrumbGroup' => 'Admin',
            ]);
    }
}
