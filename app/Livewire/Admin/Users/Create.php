<?php

namespace App\Livewire\Admin\Users;

use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Create extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $role = '';
    public string $departmentId = '';
    public bool $requireTwoFactor = false;

    #[Computed]
    public function departments(): \Illuminate\Database\Eloquent\Collection
    {
        return Department::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function showDepartment(): bool
    {
        return $this->role === 'department-user';
    }

    protected function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'role'            => ['required', 'string', Rule::in([
                'system-admin',
                'warehouse-manager',
                'department-user',
                'storekeeper',
                'scanner-operator',
                'viewer-auditor',
            ])],
            'departmentId'    => ['required_if:role,department-user', 'nullable', 'exists:departments,id'],
            'requireTwoFactor' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required'         => 'A full name is required.',
            'email.required'        => 'An email address is required.',
            'email.email'           => 'Please enter a valid email address.',
            'email.unique'          => 'This email address is already registered.',
            'role.required'         => 'Please assign a role to this user.',
            'role.in'               => 'Please select a valid role.',
            'departmentId.required_if' => 'A department is required for Department User role.',
            'departmentId.exists'   => 'The selected department does not exist.',
        ];
    }

    public function save(): void
    {
        $this->authorize('create', User::class);

        $data = $this->validate();

        $user = User::create([
            'name'               => $data['name'],
            'email'              => $data['email'],
            'phone'              => $data['phone'] ?: null,
            'department_id'      => $data['departmentId'] ?: null,
            'status'             => 'invited',
            'require_two_factor' => $data['requireTwoFactor'],
            'invited_by'         => auth()->id(),
            'invitation_sent_at' => now(),
            'password'           => null,
        ]);

        $user->assignRole($data['role']);

        // Send invitation notification
        // $user->notify(new \App\Notifications\UserInvitationNotification($user));

        $this->dispatch('toast', message: 'Invitation sent to ' . $user->email . '.', type: 'success');

        $this->redirect(route('admin.users.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.users.create')
            ->layout('components.layouts.app', [
                'title'           => 'New User',
                'breadcrumbGroup' => 'Admin',
            ]);
    }
}
