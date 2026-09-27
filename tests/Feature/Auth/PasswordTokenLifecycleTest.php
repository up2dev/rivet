<?php
/**
 * PasswordTokenLifecycleTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Auth;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Rivet\Data\Models\Auth\User;
use Rivet\Data\Models\Token;
use Rivet\Mail\BaseMail;
use Rivet\Tests\TestCase;

/**
 * PasswordTokenLifecycleTest
 *
 * Password creation/reset tokens (the 'tokens' table): validity,
 * purpose, single use, revocation, anti-enumeration and pruning.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class PasswordTokenLifecycleTest extends TestCase
{
    /**
     * @return User
     */
    private function _createUser(string $login = 'jdoe'): User
    {
        return User::create([
            'login'             => $login,
            'email'             => "{$login}@example.test",
            'email_verified_at' => now(),
            'password'          => 'OldPassw0rd!'
        ]);
    }

    /**
     * @return string The plain token
     */
    private function _token(User $user, string $purpose, $expires_at): string
    {
        $plain = Token::generateTokenString();

        $user->pwdTokens()->create([
            'purpose'    => $purpose,
            'name'       => "{$purpose}-test",
            'token'      => $plain,
            'expires_at' => $expires_at
        ]);

        return $plain;
    }

    /**
     * @return \Illuminate\Testing\TestResponse
     */
    private function _renew(string $plain, string $password = 'NewPassw0rd!')
    {
        return $this->postJson("/api/auth/pwd/{$plain}", [
            'password'              => $password,
            'password_confirmation' => $password
        ]);
    }

    /**
     * Regression: the old check only read the minutes component of the
     * interval, so a token expired for exactly N hours (and < 1 min)
     * was still accepted.
     *
     * @return void
     */
    public function testATokenExpiredForWholeHoursIsRejected(): void
    {
        $user = $this->_createUser();
        $plain = $this->_token(
            $user, Token::PURPOSE_PWD_FORGOT,
            now()->subHours(2)->subSeconds(30)
        );

        $this->_renew($plain)->assertStatus(500);
        $this->assertTrue(Hash::check('OldPassw0rd!', $user->fresh()->password));
    }

    /**
     * @return void
     */
    public function testATokenExpiredForAFewMinutesIsRejected(): void
    {
        $user = $this->_createUser();
        $plain = $this->_token(
            $user, Token::PURPOSE_PWD_FORGOT, now()->subMinutes(3)
        );

        $this->_renew($plain)->assertStatus(500);
    }

    /**
     * A token of another purpose must never set a password.
     *
     * @return void
     */
    public function testATokenWithAnotherPurposeCannotSetAPassword(): void
    {
        $user = $this->_createUser();
        $plain = $this->_token($user, 'email_verify', now()->addHour());

        $this->_renew($plain)->assertStatus(500);
        $this->assertTrue(Hash::check('OldPassw0rd!', $user->fresh()->password));
    }

    /**
     * @return void
     */
    public function testAPasswordCreationTokenSetsThePassword(): void
    {
        $user = $this->_createUser();
        $plain = $this->_token(
            $user, Token::PURPOSE_PWD_CREATE, now()->addHour()
        );

        $this->_renew($plain)->assertStatus(200);
        $this->assertTrue(Hash::check('NewPassw0rd!', $user->fresh()->password));
    }

    /**
     * A soft-deleted user cannot use a pending link.
     *
     * @return void
     */
    public function testASoftDeletedUserCannotUseAPendingLink(): void
    {
        $user = $this->_createUser();
        $plain = $this->_token(
            $user, Token::PURPOSE_PWD_FORGOT, now()->addHour()
        );
        $user->delete();

        $this->_renew($plain)->assertStatus(500);
    }

    /**
     * A successful renewal revokes every pending password link and,
     * by default, every open session.
     *
     * @return void
     */
    public function testASuccessfulRenewalRevokesOtherLinksAndSessions(): void
    {
        $user = $this->_createUser();
        $user->createToken('old-session');
        $other = $this->_token($user, Token::PURPOSE_PWD_CREATE, now()->addHour());
        $plain = $this->_token($user, Token::PURPOSE_PWD_FORGOT, now()->addHour());

        $this->_renew($plain)->assertStatus(200);

        $this->assertSame(0, $user->pwdTokens()->count());
        $this->assertSame(0, $user->tokens()->count());
        $this->_renew($other, 'Another1!')->assertStatus(500);
    }

    /**
     * @return void
     */
    public function testSessionsAreKeptWhenRevocationIsDisabled(): void
    {
        config([ 'auth.pwd_reset_revokes_sessions' => false ]);

        $user = $this->_createUser();
        $user->createToken('old-session');
        $plain = $this->_token($user, Token::PURPOSE_PWD_FORGOT, now()->addHour());

        $this->_renew($plain)->assertStatus(200);

        $this->assertSame(1, $user->tokens()->count());
    }

    /**
     * Unknown login: same answer as a known one, and no email.
     *
     * @return void
     */
    public function testForgotDoesNotRevealWhetherALoginExists(): void
    {
        $this->_createUser();
        Mail::fake();

        $known = $this->postJson('/api/auth/pwd/forgot', [ 'login' => 'jdoe' ]);
        $unknown = $this->postJson('/api/auth/pwd/forgot', [ 'login' => 'nobody' ]);

        $known->assertStatus(200);
        $unknown->assertStatus(200);
        $this->assertSame($known->json('data'), $unknown->json('data'));
        Mail::assertSent(BaseMail::class, 1);
    }

    /**
     * Only the last "forgot password" link works.
     *
     * @return void
     */
    public function testANewForgotRequestRevokesThePreviousLink(): void
    {
        Mail::fake();

        $user = $this->_createUser();

        $this->postJson('/api/auth/pwd/forgot', [ 'login' => 'jdoe' ]);
        $first = Mail::sent(BaseMail::class)->last()->token;
        $this->postJson('/api/auth/pwd/forgot', [ 'login' => 'jdoe' ]);
        $second = Mail::sent(BaseMail::class)->last()->token;

        $this->assertNotSame($first, $second);
        $this->assertSame(
            1, $user->pwdTokens()->purpose(Token::PURPOSE_PWD_FORGOT)->count()
        );
        $this->_renew($first)->assertStatus(500);
        $this->_renew($second)->assertStatus(200);
    }

    /**
     * The emailed expiry date is the real one, not the creation date.
     *
     * @return void
     */
    public function testTheEmailShowsTheRealExpiryDate(): void
    {
        Mail::fake();
        config([ 'auth.pwd_token_validity' => 30 ]);

        $this->_createUser();
        $this->postJson('/api/auth/pwd/forgot', [ 'login' => 'jdoe' ]);

        $this->assertEqualsWithDelta(
            now()->addMinutes(30)->getTimestamp(),
            Mail::sent(BaseMail::class)->last()->token_expires_at->getTimestamp(),
            5
        );
    }

    /**
     * Regression: while the password was empty, EVERY save of the user
     * generated a new link and a new email.
     *
     * @return void
     */
    public function testThePasswordCreationLinkIsSentOnlyOnce(): void
    {
        Mail::fake();
        config([ 'mail.is_forcing_password_creation' => true ]);

        $user = User::create([
            'login' => 'newbie', 'email' => 'newbie@example.test',
            'email_verified_at' => now()
        ]);

        $user->login = 'newbie2';
        $user->save();
        $user->save();

        $this->assertSame(
            1, $user->pwdTokens()->purpose(Token::PURPOSE_PWD_CREATE)->count()
        );
        $this->assertCount(
            1, Mail::sent(BaseMail::class, fn ($mail) => isset($mail->token))
        );
    }

    /**
     * Once the first link has expired, a new one can be sent.
     *
     * @return void
     */
    public function testAnExpiredCreationLinkIsReplacedOnNextSave(): void
    {
        Mail::fake();
        config([ 'mail.is_forcing_password_creation' => true ]);

        $user = User::create([
            'login' => 'newbie', 'email' => 'newbie@example.test',
            'email_verified_at' => now()
        ]);
        $user->pwdTokens()->update([ 'expires_at' => now()->subMinute() ]);

        $user->save();

        $this->assertSame(
            1, $user->pwdTokens()->purpose(Token::PURPOSE_PWD_CREATE)->valid()->count()
        );
        $this->assertSame(1, $user->pwdTokens()->count());
    }

    /**
     * @return void
     */
    public function testForceDeletingAUserRemovesItsTokens(): void
    {
        $user = $this->_createUser();
        $this->_token($user, Token::PURPOSE_PWD_FORGOT, now()->addHour());

        $user->forceDelete();

        $this->assertSame(0, Token::count());
    }

    /**
     * Expired tokens are removed by `model:prune`.
     *
     * @return void
     */
    public function testExpiredTokensArePrunable(): void
    {
        $user = $this->_createUser();
        $this->_token($user, Token::PURPOSE_PWD_FORGOT, now()->subMinute());
        $this->_token($user, Token::PURPOSE_PWD_CREATE, now()->addHour());

        Artisan::call('model:prune', [ '--model' => [ Token::class ] ]);

        $this->assertSame(1, Token::count());
        $this->assertSame(Token::PURPOSE_PWD_CREATE, Token::first()->purpose);
    }

    /**
     * Only the hash of the token is stored.
     *
     * @return void
     */
    public function testOnlyTheTokenHashIsStored(): void
    {
        $user = $this->_createUser();
        $plain = $this->_token($user, Token::PURPOSE_PWD_FORGOT, now()->addHour());

        $stored = Token::first()->getAttributes()['token'];

        $this->assertNotSame($plain, $stored);
        $this->assertSame(hash('sha256', $plain), $stored);
    }
}
