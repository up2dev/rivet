<?php
/**
 * PasswordResetSecurityTest class file
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
 * PasswordResetSecurityTest
 *
 * Covers PasswordController::mailRenew(), through the real
 * '/api/auth/pwd/{token}' route (not calling the method directly), so
 * the DataValidate middleware and ResponseService formatting are
 * exercised too.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class PasswordResetSecurityTest extends TestCase
{
    /**
     * An unknown token must return a clean error response, not crash.
     *
     * @return void
     */
    public function testRenewingWithAnUnknownTokenReturnsACleanErrorInsteadOfCrashing(): void
    {
        $response = $this->postJson('/api/auth/pwd/this-token-does-not-exist', [
            'password'              => 'NewPassw0rd!',
            'password_confirmation' => 'NewPassw0rd!'
        ]);

        $response->assertStatus(500);
    }

    /**
     * A reset token must not be usable a second time after a
     * successful renewal.
     *
     * @return void
     */
    public function testAResetTokenCannotBeReplayedAfterASuccessfulRenewal(): void
    {
        $user = User::create([
            'login'    => 'jdoe',
            'email'    => 'jdoe@example.test',
            'password' => 'OldPassw0rd!'
        ]);

        $plain_token = Token::generateTokenString();

        $user->pwdTokens()->create([
            'purpose'    => 'pwd_forgot',
            'name'       => 'pwd_forgot-test',
            'token'      => $plain_token,
            'expires_at' => now()->addMinutes(30)
        ]);

        $first = $this->postJson("/api/auth/pwd/{$plain_token}", [
            'password'              => 'NewPassw0rd!',
            'password_confirmation' => 'NewPassw0rd!'
        ]);

        $first->assertStatus(200);

        $second = $this->postJson("/api/auth/pwd/{$plain_token}", [
            'password'              => 'AnotherPassw0rd!',
            'password_confirmation' => 'AnotherPassw0rd!'
        ]);

        $second->assertStatus(500);

        // And the password from the FIRST (legitimate) renewal must be
        // the one that stuck - the replay attempt must not have
        // silently overwritten it before failing.
        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check(
                'NewPassw0rd!', $user->fresh()->password
            )
        );
    }

    /**
     * A still-valid, never-used token must keep working - guards
     * against an overly aggressive fix that invalidates on read
     * rather than on successful use.
     *
     * @return void
     */
    public function testAFreshValidTokenStillRenewsThePasswordSuccessfully(): void
    {
        $user = User::create([
            'login'    => 'asmith',
            'email'    => 'asmith@example.test',
            'password' => 'OldPassw0rd!'
        ]);

        $plain_token = Token::generateTokenString();

        $user->pwdTokens()->create([
            'purpose'    => 'pwd_forgot',
            'name'       => 'pwd_forgot-test',
            'token'      => $plain_token,
            'expires_at' => now()->addMinutes(30)
        ]);

        $response = $this->postJson("/api/auth/pwd/{$plain_token}", [
            'password'              => 'NewPassw0rd!',
            'password_confirmation' => 'NewPassw0rd!'
        ]);

        $response->assertStatus(200);
        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check(
                'NewPassw0rd!', $user->fresh()->password
            )
        );
    }
}
