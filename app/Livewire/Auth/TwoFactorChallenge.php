<?php

namespace App\Livewire\Auth;

use App\Models\EmailOtpCode;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Livewire\Component;

class TwoFactorChallenge extends Component
{
    public string $code = '';
    public string $recoveryCode = '';
    public bool $usingRecovery = false;

    public ?User $challengeUser = null;

    public function mount(): void
    {
        $id = Session::get('2fa.user_id');

        if (! $id) {
            $this->redirect(route('login'), navigate: true);
            return;
        }

        $this->challengeUser = User::find($id);

        if (! $this->challengeUser) {
            Session::forget('2fa.user_id');
            $this->redirect(route('login'), navigate: true);
        }
    }

    public function verify(): void
    {
        $key = '2fa|' . Session::get('2fa.user_id') . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Please try again later.',
            ]);
        }

        $user = $this->challengeUser;

        if ($this->usingRecovery) {
            $this->verifyRecoveryCode($user, $key);
        } elseif ($user->two_factor_method === 'app') {
            $this->verifyTotp($user, $key);
        } else {
            $this->verifyEmailOtp($user, $key);
        }
    }

    private function verifyTotp(User $user, string $rateLimitKey): void
    {
        $valid = app(ConfirmTwoFactorAuthentication::class)($user, $this->code);

        if (! $valid) {
            RateLimiter::hit($rateLimitKey);
            throw ValidationException::withMessages(['code' => 'The provided code was invalid.']);
        }

        $this->completeLogin($user, $rateLimitKey);
    }

    private function verifyEmailOtp(User $user, string $rateLimitKey): void
    {
        $otp = EmailOtpCode::where('user_id', $user->id)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $otp || $otp->isExpired() || ! $otp->verify($this->code)) {
            RateLimiter::hit($rateLimitKey);
            if ($otp) {
                $otp->increment('attempts');
            }
            throw ValidationException::withMessages(['code' => 'Invalid or expired code.']);
        }

        $otp->update(['used_at' => now()]);
        $this->completeLogin($user, $rateLimitKey);
    }

    private function verifyRecoveryCode(User $user, string $rateLimitKey): void
    {
        $codes = json_decode(decrypt($user->two_factor_recovery_codes ?? '[]'), true) ?? [];
        $found = false;

        foreach ($codes as $i => $code) {
            if (hash_equals($code, $this->recoveryCode)) {
                $found = true;
                unset($codes[$i]);
                break;
            }
        }

        if (! $found) {
            RateLimiter::hit($rateLimitKey);
            throw ValidationException::withMessages(['recoveryCode' => 'Invalid recovery code.']);
        }

        $user->forceFill(['two_factor_recovery_codes' => encrypt(json_encode(array_values($codes)))])->save();
        $this->completeLogin($user, $rateLimitKey);
    }

    private function completeLogin(User $user, string $rateLimitKey): void
    {
        RateLimiter::clear($rateLimitKey);
        Session::forget('2fa.user_id');

        Auth::login($user, Session::get('2fa.remember', false));
        Session::regenerate();

        if ($user->hasRole('scanner-operator')) {
            $this->redirect(route('scan.home'), navigate: true);
            return;
        }

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function resendCode(): void
    {
        if (! $this->challengeUser || $this->challengeUser->two_factor_method !== 'email') {
            return;
        }

        $result = EmailOtpCode::issueFor($this->challengeUser);
        $this->challengeUser->notify(new EmailOtpNotification($result['code']));

        $this->dispatch('toast', message: 'A new code has been sent to your email.', type: 'info');
    }

    public function render(): View
    {
        return view('livewire.auth.two-factor-challenge')
            ->layout('components.layouts.auth');
    }
}
