<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class EmailOtpCode extends Model
{
    protected $fillable = ['user_id', 'code', 'expires_at', 'attempts', 'used_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at'    => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return ! is_null($this->used_at);
    }

    public function verify(string $plainCode): bool
    {
        return Hash::check($plainCode, $this->code);
    }

    // ─── Factory helper ───────────────────────────────────────────────────────

    public static function issueFor(User $user): array
    {
        // Invalidate any existing codes
        static::where('user_id', $user->id)->whereNull('used_at')->delete();

        $plain = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        static::create([
            'user_id'    => $user->id,
            'code'       => Hash::make($plain),
            'expires_at' => now()->addMinutes(config('wms.otp_expiry_minutes', 10)),
            'attempts'   => 0,
        ]);

        return ['code' => $plain];
    }
}
