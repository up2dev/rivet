<?php
/**
 * TwoFactorHardeningTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Rivet\Data\Models\Auth\Role;
use Rivet\Data\Models\Auth\TwoFactorMethod;
use Rivet\Data\Models\Auth\User;
use Rivet\Mail\BaseMail;
use Rivet\Tests\TestCase;

/**
 * TwoFactorHardeningTest
 *
 * Encrypted secrets, hashed email codes, brute-force limit, TOTP
 * anti-replay, last-method guard and role exemption.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class TwoFactorHardeningTest extends TestCase
{
    /**
     * @return User
     */
    private function _createUser(): User
    {
        return User::create([
            'login'             => 'jdoe',
            'email'             => 'jdoe@example.test',
            'email_verified_at' => now(),
            'password'          => 'Passw0rd!'
        ]);
    }

    /**
     * @return string The TOTP secret
     */
    private function _confirmTotp(User $user): string
    {
        $secret = (new Google2FA())->generateSecretKey();

        TwoFactorMethod::create([
            'user_id' => $user->id, 'method' => 'totp',
            'secret' => $secret, 'confirmed_at' => now()
        ]);

        return $secret;
    }

    /**
     * @return string The pending token
     */
    private function _pendingToken(): string
    {
        return $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ])->json('data.pending_token');
    }

    /**
     * @return void
     */
    public function testTheTotpSecretIsEncryptedAtRest(): void
    {
        $user = $this->_createUser();
        $secret = $this->_confirmTotp($user);

        $raw = DB::table('user_two_factor_methods')->value('secret');

        $this->assertNotSame($secret, $raw);
        $this->assertSame($secret, TwoFactorMethod::first()->secret);
    }

    /**
     * @return void
     */
    public function testTheSecretIsNeverSerialized(): void
    {
        $user = $this->_createUser();
        $this->_confirmTotp($user);

        $this->assertArrayNotHasKey('secret', TwoFactorMethod::first()->toArray());
    }

    /**
     * @return void
     */
    public function testTheEmailCodeIsNotStoredInClear(): void
    {
        Mail::fake();

        $user = $this->_createUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/2fa/email/enable')->assertStatus(200);

        $code = (string) Mail::sent(BaseMail::class)->last()->code;
        $cached = Cache::get("two_factor_email_code:{$user->id}");

        $this->assertNotSame($code, $cached);
        $this->assertStringStartsWith('$', $cached);
    }

    /**
     * Too many wrong codes destroy the pending token.
     *
     * @return void
     */
    public function testThePendingTokenIsDestroyedAfterMaxAttempts(): void
    {
        config([ 'two_factor.max_attempts' => 3 ]);

        $user = $this->_createUser();
        $secret = $this->_confirmTotp($user);
        $pending = $this->_pendingToken();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/2fa/verify', [
                'pending_token' => $pending, 'method' => 'totp', 'code' => '000000'
            ])->assertStatus(400);
        }

        // Even the right code is now refused: log in again.
        $this->postJson('/api/auth/2fa/verify', [
            'pending_token' => $pending, 'method' => 'totp',
            'code' => (new Google2FA())->getCurrentOtp($secret)
        ])->assertStatus(401);
    }

    /**
     * Below the limit, a typo does not lock the user out.
     *
     * @return void
     */
    public function testAWrongCodeBelowTheLimitKeepsThePendingToken(): void
    {
        $user = $this->_createUser();
        $secret = $this->_confirmTotp($user);
        $pending = $this->_pendingToken();

        $this->postJson('/api/auth/2fa/verify', [
            'pending_token' => $pending, 'method' => 'totp', 'code' => '000000'
        ])->assertStatus(400);

        $this->postJson('/api/auth/2fa/verify', [
            'pending_token' => $pending, 'method' => 'totp',
            'code' => (new Google2FA())->getCurrentOtp($secret)
        ])->assertStatus(200);
    }

    /**
     * A TOTP code already used cannot be used again (new login).
     *
     * @return void
     */
    public function testATotpCodeCannotBeReplayed(): void
    {
        $user = $this->_createUser();
        $secret = $this->_confirmTotp($user);
        $code = (new Google2FA())->getCurrentOtp($secret);

        $this->postJson('/api/auth/2fa/verify', [
            'pending_token' => $this->_pendingToken(),
            'method' => 'totp', 'code' => $code
        ])->assertStatus(200);

        $this->postJson('/api/auth/2fa/verify', [
            'pending_token' => $this->_pendingToken(),
            'method' => 'totp', 'code' => $code
        ])->assertStatus(400);
    }

    /**
     * With forced enrollment, the last method cannot be removed.
     *
     * @return void
     */
    public function testTheLastMethodCannotBeDisabledWhenEnrollmentIsForced(): void
    {
        config([ 'two_factor.force_enrollment' => true ]);

        $user = $this->_createUser();
        $this->_confirmTotp($user);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/auth/2fa/totp')->assertStatus(422);

        $this->assertSame(1, $user->twoFactorMethods()->count());
    }

    /**
     * @return void
     */
    public function testAMethodCanBeDisabledWhenAnotherOneRemains(): void
    {
        config([ 'two_factor.force_enrollment' => true ]);

        $user = $this->_createUser();
        $this->_confirmTotp($user);
        TwoFactorMethod::create([
            'user_id' => $user->id, 'method' => 'email', 'confirmed_at' => now()
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/auth/2fa/totp')->assertStatus(200);

        $this->assertSame([ 'email' ], $user->twoFactorMethods()->pluck('method')->all());
    }

    /**
     * @return void
     */
    public function testTheLastMethodCanBeDisabledWhenEnrollmentIsOptional(): void
    {
        $user = $this->_createUser();
        $this->_confirmTotp($user);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/auth/2fa/totp')->assertStatus(200);

        $this->assertSame(0, $user->twoFactorMethods()->count());
    }

    /**
     * @return void
     */
    public function testABypassRoleSkipsForcedEnrollment(): void
    {
        config([
            'two_factor.force_enrollment' => true,
            'two_factor.bypass_roles'     => [ 'TECH' ]
        ]);

        $role = Role::create([ 'uid' => 'TECH', 'name' => 'Technical' ]);
        $user = $this->_createUser();
        $user->roles()->attach($role->id);

        $response = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ]);

        $this->assertArrayHasKey('token', $response->json('data'));
    }

    /**
     * The 2FA login path prunes expired tokens too.
     *
     * @return void
     */
    public function testVerifyPrunesExpiredAccessTokens(): void
    {
        $user = $this->_createUser();
        $secret = $this->_confirmTotp($user);
        $user->createToken('expired', [ '*' ], now()->subMinute());

        $this->postJson('/api/auth/2fa/verify', [
            'pending_token' => $this->_pendingToken(), 'method' => 'totp',
            'code' => (new Google2FA())->getCurrentOtp($secret)
        ])->assertStatus(200);

        $this->assertSame(0, $user->tokens()->where('name', 'expired')->count());
    }
}
