<?php

namespace App\Services\Auth;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class UserSessionManager
{
    /** @return Collection<int, array<string, mixed>> */
    public function forUser(int $userId, string $currentSessionId): Collection
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) use ($currentSessionId) {
                $agent = $session->user_agent ?? '';

                return [
                    'id'            => $session->id,
                    'ip_address'    => $session->ip_address,
                    'user_agent'    => $agent,
                    'device'        => $this->parseDevice($agent),
                    'last_activity' => Carbon::createFromTimestamp($session->last_activity),
                    'is_current'    => $session->id === $currentSessionId,
                ];
            });
    }

    public function deleteOtherSessions(int $userId, string $currentSessionId): void
    {
        DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    private function parseDevice(string $userAgent): string
    {
        if (str_contains($userAgent, 'Mobile') || str_contains($userAgent, 'Android')) {
            return 'Mobile';
        }

        if (str_contains($userAgent, 'Tablet') || str_contains($userAgent, 'iPad')) {
            return 'Tablet';
        }

        return 'Desktop';
    }
}
