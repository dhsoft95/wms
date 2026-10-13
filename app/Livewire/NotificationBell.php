<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;

    #[Computed]
    public function notifications()
    {
        return auth()->user()->unreadNotifications()->latest()->limit(20)->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function markRead(string $id): void
    {
        auth()->user()->notifications()->where('id', $id)->update(['read_at' => now()]);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        $this->open = false;
    }

    public function toggle(): void
    {
        $this->open = !$this->open;
    }

    public function render()
    {
        return view('livewire.notification-bell');
    }
}
