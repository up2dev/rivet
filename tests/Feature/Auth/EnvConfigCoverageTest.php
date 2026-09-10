<?php
/**
 * EnvConfigCoverageTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Auth;

use Rivet\Tests\TestCase;
use Rivet\Data\Models\Auth\User;
use Rivet\Data\Models\Token;

/**
 * Regression guard for several env() calls found outside config/*.php
 * (same bug class already fixed in AuthController::login() and
 * BaseMail: broken once `php artisan config:cache` has run in
 * production) - PasswordController::forgot() and UserTrait's
 * password-creation-link flow, plus Token::generateTokenString(), now
 * read through config(). config('auth.pwd_token_validity') in
 * particular had no config wiring and no default at all before this
 * fix - a token's expiry would have been computed from a null
 * duration.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class EnvConfigCoverageTest extends TestCase
{
    /**
     * @return void
     */
    public function testForgotPasswordUsesTheConfiguredTokenValidity(): void
    {
        config([ 'auth.pwd_token_validity' => 45 ]);

        $user = User::create([
            'login' => 'jdoe', 'email' => 'jdoe@example.test',
            'email_verified_at' => now(), 'password' => 'Passw0rd!'
        ]);

        $this->postJson('/api/auth/pwd/forgot', [ 'login' => 'jdoe' ])
            ->assertStatus(200);

        $token = $user->pwdTokens()->where('purpose', 'pwd_forgot')->first();

        $this->assertNotNull($token);
        $this->assertEqualsWithDelta(
            now()->addMinutes(45)->timestamp,
            $token->expires_at->timestamp,
            5
        );
    }

    /**
     * @return void
     */
    public function testGeneratedTokenStringsUseTheConfiguredPrefix(): void
    {
        config([ 'auth.token_prefix' => 'RVT-' ]);

        $this->assertStringStartsWith('RVT-', Token::generateTokenString());
    }
}
