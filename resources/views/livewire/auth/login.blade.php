<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        // Check status before attempting authentication
        $user = User::where('email', $this->email)->first();

        if ($user) {
            if ($user->isInvited()) {
                throw ValidationException::withMessages([
                    'email' => 'Your account invitation is pending. Please check your email to set your password.',
                ]);
            }

            if ($user->isSuspended()) {
                throw ValidationException::withMessages([
                    'email' => 'Your account has been suspended. Please contact your administrator.',
                ]);
            }
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $user = Auth::user();

        // Record last login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        // 2FA challenge
        if ($user->hasTwoFactorEnabled()) {
            if ($user->two_factor_method === 'email') {
                $result = \App\Models\EmailOtpCode::issueFor($user);
                $user->notify(new \App\Notifications\EmailOtpNotification($result['code']));
            }
            Session::put('2fa.user_id', $user->id);
            Auth::logout();
            $this->redirect(route('two-factor.challenge'), navigate: true);
            return;
        }

        $this->redirectAfterLogin($user);
    }

    private function redirectAfterLogin(User $user): void
    {
        // navigate: false — crossing layout boundaries (auth → app/scan) requires a full
        // page load so that the app layout's <html x-data="..."> is mounted fresh by Alpine.
        if ($user->hasRole('scanner-operator')) {
            $this->redirect(route('scan.home'), navigate: false);
            return;
        }
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: false);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-5">
    <x-auth-header
        title="{{ __('Sign in to your account') }}"
        description="{{ __('Enter your email and password below to continue to :name.', ['name' => config('wms.branding.name') ?? config('app.name')]) }}"
    />

    @if (session('status'))
        <x-auth.alert :status="session('status')" type="success" />
    @endif

    @if ($errors->has('email') && str_contains($errors->first('email'), 'attempts'))
        <x-auth.alert :status="$errors->first('email')" type="warning" />
    @endif

    <form wire:submit="login" class="flex flex-col gap-4">
        <x-auth.input
            wire:model="email"
            name="email"
            label="{{ __('Email address') }}"
            icon="mail"
            type="email"
            required
            autofocus
            autocomplete="email"
            placeholder="you@example.com"
        />

        <div class="space-y-1.5">
            <div class="flex items-center justify-between">
                <label class="block text-xs font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Password') }}</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-xs font-semibold text-simba-600 dark:text-simba-300 hover:text-simba-700 dark:hover:text-simba-200 hover:underline transition-colors" wire:navigate>{{ __('Forgot password?') }}</a>
                @endif
            </div>
            <x-auth.password
                wire:model="password"
                name="password"
                label=""
                required
                autocomplete="current-password"
                placeholder="{{ __('Enter your password') }}"
            />
        </div>

        <x-auth.checkbox wire:model="remember" name="remember" label="{{ __('Remember me') }}" />

        <x-auth.button wire:loading.attr="disabled" loading-text="{{ __('Signing in…') }}">
            {{ __('Sign in') }}
        </x-auth.button>
    </form>

</div>
