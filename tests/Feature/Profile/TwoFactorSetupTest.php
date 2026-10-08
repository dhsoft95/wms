<?php

namespace Tests\Feature\Profile;

use App\Livewire\Profile\Index;
use App\Models\EmailOtpCode;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function makeUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('warehouse-manager');
        return $user;
    }

    // ── TOTP (Authenticator App) ──────────────────────────────────────────────

    public function test_enable_totp_generates_secret_and_shows_qr(): void
    {
        $user = $this->makeUser();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('twoFactorMethod', 'app')
            ->call('enableTwoFactor')
            ->assertSet('showQrCode', true)
            ->assertSet('twoFactorMethod', 'app')
            ->assertHasNoErrors();

        $this->assertNotNull($user->fresh()->two_factor_secret);
    }

    public function test_confirm_totp_with_valid_code_activates_2fa(): void
    {
        $user = $this->makeUser();

        // Pre-generate the secret so we can derive a valid code
        app(EnableTwoFactorAuthentication::class)($user);
        $user->refresh();

        $secret = decrypt($user->two_factor_secret);
        $code   = (new Google2FA())->getCurrentOtp($secret);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('twoFactorMethod', 'app')
            ->set('showQrCode', true)
            ->set('confirmCode', $code)
            ->call('confirmApp')
            ->assertSet('showQrCode', false)
            ->assertSet('showRecoveryCodes', true)
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertSame('app', $user->two_factor_method);
        $this->assertNotNull($user->two_factor_recovery_codes);
    }

    public function test_confirm_totp_with_invalid_code_shows_error(): void
    {
        $user = $this->makeUser();

        app(EnableTwoFactorAuthentication::class)($user);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('twoFactorMethod', 'app')
            ->set('showQrCode', true)
            ->set('confirmCode', '000000')
            ->call('confirmApp')
            ->assertHasErrors('confirmCode');

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_confirm_totp_requires_six_digits(): void
    {
        $user = $this->makeUser();

        app(EnableTwoFactorAuthentication::class)($user);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('twoFactorMethod', 'app')
            ->set('showQrCode', true)
            ->set('confirmCode', '12')
            ->call('confirmApp')
            ->assertHasErrors('confirmCode');
    }

    // ── Email OTP ─────────────────────────────────────────────────────────────

    public function test_enable_email_otp_sends_notification_and_prompts_confirm(): void
    {
        Notification::fake();

        $user = $this->makeUser();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('twoFactorMethod', 'email')
            ->call('enableTwoFactor')
            ->assertSet('confirmingEmail', true)
            ->assertSet('twoFactorMethod', 'email')
            ->assertHasNoErrors();

        Notification::assertSentTo($user, EmailOtpNotification::class);

        // two_factor_method must NOT be saved until code is confirmed
        $this->assertNull($user->fresh()->two_factor_method);
    }

    public function test_confirm_email_otp_with_valid_code_activates_2fa(): void
    {
        $user = $this->makeUser();

        ['code' => $plain] = EmailOtpCode::issueFor($user);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('twoFactorMethod', 'email')
            ->set('confirmingEmail', true)
            ->set('confirmCode', $plain)
            ->call('confirmEmail')
            ->assertSet('confirmingEmail', false)
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('email', $user->two_factor_method);
    }

    public function test_confirm_email_otp_with_wrong_code_shows_error(): void
    {
        $user = $this->makeUser();

        EmailOtpCode::issueFor($user);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('twoFactorMethod', 'email')
            ->set('confirmingEmail', true)
            ->set('confirmCode', '000000')
            ->call('confirmEmail')
            ->assertHasErrors('confirmCode');

        $this->assertNull($user->fresh()->two_factor_method);
    }

    public function test_confirm_email_otp_with_expired_code_shows_error(): void
    {
        $user = $this->makeUser();

        EmailOtpCode::issueFor($user);

        // Manually expire it
        EmailOtpCode::where('user_id', $user->id)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $user->refresh();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('twoFactorMethod', 'email')
            ->set('confirmingEmail', true)
            ->set('confirmCode', '123456')
            ->call('confirmEmail')
            ->assertHasErrors('confirmCode');
    }

    // ── Disable ───────────────────────────────────────────────────────────────

    public function test_disable_totp_clears_all_2fa_fields(): void
    {
        $user = $this->makeUser();

        app(EnableTwoFactorAuthentication::class)($user);
        $user->update([
            'two_factor_confirmed_at' => now(),
            'two_factor_method'       => 'app',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('disableTwoFactor')
            ->assertSet('twoFactorMethod', '')
            ->assertSet('showQrCode', false)
            ->assertSet('showRecoveryCodes', false);

        $user->refresh();
        $this->assertNull($user->two_factor_method);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertNull($user->two_factor_secret);
    }

    public function test_disable_email_otp_clears_method(): void
    {
        $user = $this->makeUser();
        $user->update(['two_factor_method' => 'email']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('disableTwoFactor')
            ->assertSet('twoFactorMethod', '');

        $this->assertNull($user->fresh()->two_factor_method);
    }
}
