<?php

namespace App\Livewire\Shared;

use App\Services\MessagingService;
use App\Services\NotificationService;
use Livewire\Component;

class NotificationBell extends Component
{
    public function markAllRead(): void
    {
        $me = MessagingService::currentParticipant();

        if ($me) {
            NotificationService::markAllRead($me['type'], $me['id']);
        }
    }

    public function render()
    {
        $me = MessagingService::currentParticipant();

        return view('livewire.shared.notification-bell', [
            'notifications' => $me ? NotificationService::recentFor($me['type'], $me['id']) : collect(),
            'unreadCount' => $me ? NotificationService::unreadCountFor($me['type'], $me['id']) : 0,
        ]);
    }
}
