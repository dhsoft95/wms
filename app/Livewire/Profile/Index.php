<?php

namespace App\Livewire\Profile;

use App\Models\EmailOtpCode;
use App\Notifications\EmailOtpNotification;
use App\Notifications\PasswordChangedNotification;
use App\Services\Auth\UserSessionManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    // ─── Active tab ──────────────────────────────────────────────────────────

    #[Url]
    public string $activeTab = 'personal';

    // ─── Personal tab ────────────────────────────────────────────────────────

    public string $name  = '';
    public string $email = '';
    public string $phone = '';

    // ─── Password tab ────────────────────────────────────────────────────────

    public string $currentPassword  = '';
    public string $newPassword      = '';
    public string $confirmPassword  = '';

    // ─── 2FA tab ─────────────────────────────────────────────────────────────

    public string $twoFactorMethod  = '';
    public string $confirmCode      = '';
    public bool   $showQrCode       = false;
    public bool   $showRecoveryCodes = false;
    public bool   $confirmingEmail  = false;

    // ─── Sessions tab ────────────────────────────────────────────────────────

    public string $sessionPassword  = '';
    public bool   $confirmingLogout = false;

    // ─── Mount ───────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $user = auth()->user();

        $this->name  = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';

        $this->twoFactorMethod = $user->two_factor_method ?? '';
    }

    // ─── Computed ────────────────────────────────────────────────────────────

    /** @return array<int, string> */
    #[Computed]
    public function recoveryCodes(): array
    {
        $user = auth()->user();

        if (! $user->two_factor_recovery_codes) {
            return [];
        }

        return json_decode(decrypt($user->two_factor_recovery_codes), true) ?? [];
    }

    #[Computed]
    public function qrCodeSvg(): string
    {
        $user = auth()->user();

        if (! $user->two_factor_secret) {
            return '';
        }

        return $user->twoFactorQrCodeSvg();
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function sessions(): array
    {
        return app(UserSessionManager::class)
            ->forUser(auth()->id(), session()->getId())
            ->all();
    }

    // ─── Personal tab actions ────────────────────────────────────────────────

    public function savePersonal(): void
    {
        $user = auth()->user();

        $this->validate([
            'name'  => 'required|string|min:2|max:100',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:30',
        ]);

        $emailChanged = $this->email !== $user->email;

        $user->update([
            'name'              => $this->name,
            'email'             => $this->email,
            'phone'             => $this->phone ?: null,
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ]);

        $message = $emailChanged
            ? 'Profile updated. Please verify your new email address.'
            : 'Profile updated successfully.';

        $this->dispatch('toast', message: $message, type: 'success');
    }

    // ─── Password tab actions ────────────────────────────────────────────────

    public function changePassword(): void
    {
        $user = auth()->user();

        $this->validate([
            'currentPassword' => 'required',
            'newPassword'     => ['required', 'string', Password::defaults()],
            'confirmPassword' => 'required|same:newPassword',
        ]);

        if (! Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', 'The current password is incorrect.');
            return;
        }

        $user->update(['password' => Hash::make($this->newPassword)]);

        $user->notify(new PasswordChangedNotification());

        $this->currentPassword = '';
        $this->newPassword     = '';
        $this->confirmPassword = '';
        $this->resetValidation();

        $this->dispatch('toast', message: 'Password changed successfully.', type: 'success');
    }

    // ─── 2FA tab actions ─────────────────────────────────────────────────────

    public function enableTwoFactor(): void
    {
        match ($this->twoFactorMethod) {
            'app'   => $this->enableApp(),
            'email' => $this->enableEmail(),
            default => null,
        };
    }

    public function enableApp(): void
    {
        app(EnableTwoFactorAuthentication::class)(auth()->user());

        $this->twoFactorMethod = 'app';
        $this->showQrCode      = true;
        $this->confirmCode     = '';
    }

    public function confirmApp(): void
    {
        $this->validate(['confirmCode' => 'required|digits:6']);

        $user   = auth()->user();
        $secret = decrypt($user->two_factor_secret);

        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);

        if (! $google2fa->verifyKey($secret, $this->confirmCode)) {
            $this->addError('confirmCode', 'Invalid code — please try again.');
            return;
        }

        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_method' => 'app'])->save();

        app(GenerateNewRecoveryCodes::class)($user);

        $this->showQrCode       = false;
        $this->showRecoveryCodes = true;
        $this->confirmCode      = '';

        $this->dispatch('toast', message: 'Authenticator app enabled.', type: 'success');
    }

    public function enableEmail(): void
    {
        $user = auth()->user();

        ['code' => $plain] = EmailOtpCode::issueFor($user);
        $user->notify(new EmailOtpNotification($plain));

        $this->twoFactorMethod = 'email';
        $this->confirmingEmail = true;
        $this->confirmCode     = '';

        $this->dispatch('toast', message: 'A 6-digit code has been sent to your email.', type: 'info');
    }

    public function confirmEmail(): void
    {
        $this->validate(['confirmCode' => 'required|digits:6']);

        $user = auth()->user();

        $otp = EmailOtpCode::where('user_id', $user->id)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $otp || $otp->isExpired() || ! $otp->verify($this->confirmCode)) {
            $this->addError('confirmCode', 'Invalid or expired code.');
            return;
        }

        $otp->update(['used_at' => now()]);

        // Only persist the method after the code is verified
        $user->update(['two_factor_method' => 'email']);

        $this->confirmingEmail = false;
        $this->confirmCode     = '';

        $this->dispatch('toast', message: 'Email OTP two-factor authentication enabled.', type: 'success');
    }

    public function disableTwoFactor(): void
    {
        $user = auth()->user();

        app(DisableTwoFactorAuthentication::class)($user);

        $user->update(['two_factor_method' => null]);

        $this->twoFactorMethod   = '';
        $this->showQrCode        = false;
        $this->showRecoveryCodes = false;
        $this->confirmingEmail   = false;

        $this->dispatch('toast', message: 'Two-factor authentication disabled.', type: 'info');
    }

    public function regenerateRecoveryCodes(): void
    {
        app(GenerateNewRecoveryCodes::class)(auth()->user());

        $this->showRecoveryCodes = true;

        // Force computed to refresh
        unset($this->recoveryCodes);

        $this->dispatch('toast', message: 'Recovery codes regenerated. Store them safely.', type: 'success');
    }

    // ─── Sessions tab actions ─────────────────────────────────────────────────

    public function logoutOtherSessions(): void
    {
        $confirmedAt = session('auth.password_confirmed_at');

        if (! $confirmedAt || (time() - $confirmedAt) > 10800) {
            $this->validate(['sessionPassword' => 'required']);

            if (! Hash::check($this->sessionPassword, auth()->user()->password)) {
                $this->addError('sessionPassword', 'Incorrect password.');
                return;
            }

            session(['auth.password_confirmed_at' => time()]);
        }

        app(UserSessionManager::class)
            ->deleteOtherSessions(auth()->id(), session()->getId());

        $this->sessionPassword  = '';
        $this->confirmingLogout = false;

        // Force sessions computed to refresh
        unset($this->sessions);

        $this->dispatch('toast', message: 'All other sessions have been logged out.', type: 'success');
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.profile.index')
            ->layout('components.layouts.app', [
                'title'           => 'My Profile',
                'breadcrumbGroup' => 'Profile',
            ]);
    }
}
