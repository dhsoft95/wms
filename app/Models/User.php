<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles, TwoFactorAuthenticatable;

    protected $fillable = [
        'name', 'email', 'phone', 'password',
        'department_id', 'status',
        'two_factor_method', 'require_two_factor',
        'invitation_token', 'invitation_sent_at',
        'last_login_at', 'last_login_ip',
        'invited_by', 'email_verified_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'invitation_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            'invitation_sent_at'     => 'datetime',
            'last_login_at'          => 'datetime',
            'require_two_factor'     => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function invitees(): HasMany
    {
        return $this->hasMany(User::class, 'invited_by');
    }

    public function emailOtpCodes(): HasMany
    {
        return $this->hasMany(EmailOtpCode::class);
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function isActive(): bool    { return $this->status === 'active'; }
    public function isInvited(): bool   { return $this->status === 'invited'; }
    public function isSuspended(): bool { return $this->status === 'suspended'; }

    // ─── 2FA helpers ─────────────────────────────────────────────────────────

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_method === 'app'
            ? ! is_null($this->two_factor_confirmed_at)
            : $this->two_factor_method === 'email';
    }

    // ─── Invitation token ─────────────────────────────────────────────────────

    public function generateInvitationToken(): string
    {
        $token = Str::random(64);
        $this->update([
            'invitation_token'   => hash('sha256', $token),
            'invitation_sent_at' => now(),
        ]);
        return $token;
    }

    public function hasValidInvitationToken(string $token): bool
    {
        return $this->invitation_token === hash('sha256', $token)
            && $this->invitation_sent_at?->isAfter(now()->subHours(
                config('wms.invitation_expiry_hours', 72)
            ));
    }

    public function clearInvitationToken(): void
    {
        $this->update(['invitation_token' => null, 'invitation_sent_at' => null]);
    }

    // ─── Display helpers ─────────────────────────────────────────────────────

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    public function roleName(): string
    {
        return $this->roles->first()?->name ?? '—';
    }

    public function roleSlug(): string
    {
        return $this->roles->first()?->name ?? '';
    }
}
