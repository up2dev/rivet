<?php
/**
 * TwoFactorTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Auth;

use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Rivet\Tests\TestCase;
use Rivet\Data\Models\Auth\User;
use Rivet\Data\Models\Auth\TwoFactorMethod;
use Rivet\Data\Models\Auth\Role;
use Rivet\Data\Models\Auth\Permission;
use Rivet\Data\Models\Dictionaries\Types\PermissionType;
use Rivet\Tests\Support\HostUser;

/**
 * Covers Rivet's native two-factor authentication end to end: the
 * login-time decision (none/verify/enroll), TOTP setup and
 * confirmation, login verification, forced enrollment, and the
 * exemption permission.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class TwoFactorTest extends TestCase
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
     * With no enrolled method and force_enrollment off (the default),
     * login proceeds exactly as it did before this feature existed.
     *
     * @return void
     */
    public function testLoginWithNoTwoFactorProceedsNormally(): void
    {
        $this->_createUser();

        $response = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ]);

        $response->assertStatus(200);
        $this->assertArrayHasKey('token', $response->json('data'));
    }

    /**
     * @return void
     */
    public function testTotpSetupThenConfirmActivatesTheMethod(): void
    {
        $user = $this->_createUser();

        $setup = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/2fa/totp/setup')->json('data');

        $this->assertNotEmpty($setup['secret']);
        $this->assertStringContainsString('otpauth://', $setup['qr_uri']);

        $code = (new Google2FA())->getCurrentOtp($setup['secret']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/2fa/totp/confirm', [ 'code' => $code ]);

        $response->assertStatus(200);
        $this->assertNotNull(
            TwoFactorMethod::where('user_id', $user->id)
                ->where('method', 'totp')->first()->confirmed_at
        );
    }

    /**
     * @return void
     */
    public function testTotpConfirmRejectsAWrongCode(): void
    {
        $user = $this->_createUser();

        $this->actingAs($user, 'sanctum')->postJson('/api/auth/2fa/totp/setup');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/2fa/totp/confirm', [ 'code' => '000000' ]);

        $response->assertStatus(400);
        $this->assertNull(
            TwoFactorMethod::where('user_id', $user->id)
                ->where('method', 'totp')->first()->confirmed_at
        );
    }

    /**
     * With a confirmed TOTP method, login stops short of a Sanctum
     * token and hands back a pending 'verify' token instead.
     *
     * @return void
     */
    public function testLoginWithConfirmedTotpReturnsAPendingVerifyToken(): void
    {
        $user = $this->_createUser();
        $secret = (new Google2FA())->generateSecretKey();

        TwoFactorMethod::create([
            'user_id' => $user->id, 'method' => 'totp',
            'secret' => $secret, 'confirmed_at' => now()
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertSame('verify', $data['intent']);
        $this->assertSame([ 'totp' ], $data['methods']);
        $this->assertNotEmpty($data['pending_token']);
        $this->assertArrayNotHasKey('token', $data);
    }

    /**
     * The full round trip: login gates behind a pending token, the
     * correct TOTP code against it issues a real Sanctum token.
     *
     * @return void
     */
    public function testVerifyingWithTheCorrectCodeCompletesLogin(): void
    {
        $user = $this->_createUser();
        $secret = (new Google2FA())->generateSecretKey();

        TwoFactorMethod::create([
            'user_id' => $user->id, 'method' => 'totp',
            'secret' => $secret, 'confirmed_at' => now()
        ]);

        $pending = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ])->json('data');

        $code = (new Google2FA())->getCurrentOtp($secret);

        $response = $this->postJson('/api/auth/2fa/verify', [
            'pending_token' => $pending['pending_token'],
            'method'        => 'totp',
            'code'          => $code
        ]);

        $response->assertStatus(200);
        $this->assertArrayHasKey('token', $response->json('data'));
    }

    /**
     * A pending token is single-use: verifying twice with the same
     * token fails the second time.
     *
     * @return void
     */
    public function testAPendingTokenCannotBeReusedAfterVerification(): void
    {
        $user = $this->_createUser();
        $secret = (new Google2FA())->generateSecretKey();

        TwoFactorMethod::create([
            'user_id' => $user->id, 'method' => 'totp',
            'secret' => $secret, 'confirmed_at' => now()
        ]);

        $pending = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ])->json('data');

        $code = (new Google2FA())->getCurrentOtp($secret);

        $this->postJson('/api/auth/2fa/verify', [
            'pending_token' => $pending['pending_token'],
            'method' => 'totp', 'code' => $code
        ])->assertStatus(200);

        $response = $this->postJson('/api/auth/2fa/verify', [
            'pending_token' => $pending['pending_token'],
            'method' => 'totp', 'code' => $code
        ]);

        $response->assertStatus(401);
    }

    /**
     * verify() must honour ?with=, the same way AuthController::login()
     * does for a direct (no 2FA) login - issueToken()'s own docblock
     * promises "the same token shape", which a silently-missing relation
     * would break.
     *
     * @return void
     */
    public function testVerifyEagerLoadsRequestedRelations(): void
    {
        $user = $this->_createUser();
        $secret = (new Google2FA())->generateSecretKey();

        TwoFactorMethod::create([
            'user_id' => $user->id, 'method' => 'totp',
            'secret' => $secret, 'confirmed_at' => now()
        ]);

        $role = Role::create([ 'uid' => 'MEMBER', 'name' => 'Member' ]);
        $user->roles()->attach($role->id);

        $pending = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ])->json('data');

        $code = (new Google2FA())->getCurrentOtp($secret);

        $response = $this->postJson('/api/auth/2fa/verify?with=roles', [
            'pending_token' => $pending['pending_token'],
            'method'        => 'totp',
            'code'          => $code
        ]);

        $response->assertStatus(200);
        $this->assertArrayHasKey('roles', $response->json('data.user'));
        $this->assertSame('MEMBER', $response->json('data.user.roles.0.uid'));
    }

    /**
     * verify() and the pending-token middleware must resolve the user
     * through config('crud.user_model') - a host application's own User
     * subclass (default eager-loaded relations, accessors, etc.) - not
     * Rivet's own base class hardcoded, which would silently discard
     * every one of those customizations on this path only. HostUser's
     * appended 'is_host_user' attribute is the concrete, JSON-observable
     * proof: it's only present when this exact subclass was the one
     * actually instantiated.
     *
     * @return void
     */
    public function testVerifyResolvesTheConfiguredUserModel(): void
    {
        config([ 'crud.user_model' => HostUser::class ]);

        $user = $this->_createUser();
        $secret = (new Google2FA())->generateSecretKey();

        TwoFactorMethod::create([
            'user_id' => $user->id, 'method' => 'totp',
            'secret' => $secret, 'confirmed_at' => now()
        ]);

        $pending = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ])->json('data');

        $code = (new Google2FA())->getCurrentOtp($secret);

        $response = $this->postJson('/api/auth/2fa/verify', [
            'pending_token' => $pending['pending_token'],
            'method'        => 'totp',
            'code'          => $code
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.user.is_host_user'));
    }

    /**
     * With force_enrollment on and no confirmed method, login gates
     * behind a pending 'enroll' token rather than letting the user
     * through unprotected.
     *
     * @return void
     */
    public function testForcedEnrollmentGatesLoginWhenNoMethodIsConfirmed(): void
    {
        config([ 'two_factor.force_enrollment' => true ]);
        $this->_createUser();

        $response = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ]);

        $data = $response->json('data');

        $this->assertSame('enroll', $data['intent']);
        $this->assertArrayNotHasKey('token', $data);
    }

    /**
     * Completing enrollment via a pending-enroll token (forced
     * enrollment at login) issues the real Sanctum token immediately -
     * confirming the method IS the proof needed to finish logging in.
     *
     * @return void
     */
    public function testConfirmingEnrollmentViaAPendingTokenCompletesLogin(): void
    {
        config([ 'two_factor.force_enrollment' => true ]);
        $user = $this->_createUser();

        $pending = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ])->json('data');

        $setup = $this->postJson('/api/auth/2fa/totp/setup', [
            'pending_token' => $pending['pending_token']
        ])->json('data');

        $code = (new Google2FA())->getCurrentOtp($setup['secret']);

        $response = $this->postJson('/api/auth/2fa/totp/confirm', [
            'pending_token' => $pending['pending_token'],
            'code'          => $code
        ]);

        $response->assertStatus(200);
        $this->assertArrayHasKey('token', $response->json('data'));
    }

    /**
     * A user holding the exemption permission skips two-factor
     * entirely, even with forced enrollment on.
     *
     * @return void
     */
    public function testBypassPermissionSkipsForcedEnrollment(): void
    {
        config([ 'two_factor.force_enrollment' => true ]);

        $type = PermissionType::create([ 'uid' => 'ENDPOINT', 'name' => 'Endpoint' ]);
        $permission = Permission::create([
            'uid' => 'RIVET_BYPASS_2FA', 'name' => 'RIVET_BYPASS_2FA',
            'permission_type_id' => $type->id
        ]);
        $role = Role::create([ 'uid' => 'EXEMPT', 'name' => 'Exempt' ]);
        $role->permissions()->attach($permission->id);

        $user = $this->_createUser();
        $user->roles()->attach($role->id);

        $response = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ]);

        $this->assertArrayHasKey('token', $response->json('data'));
    }

    /**
     * Disabling a method requires full normal authentication - a
     * pending token alone (even a valid one) must not be accepted,
     * or a compromised pending token could strip a user's existing
     * protection.
     *
     * @return void
     */
    public function testDisablingAMethodRequiresFullAuthentication(): void
    {
        $user = $this->_createUser();
        $secret = (new Google2FA())->generateSecretKey();

        TwoFactorMethod::create([
            'user_id' => $user->id, 'method' => 'totp',
            'secret' => $secret, 'confirmed_at' => now()
        ]);

        $pending = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ])->json('data');

        $response = $this->deleteJson('/api/auth/2fa/totp', [
            'pending_token' => $pending['pending_token']
        ]);

        $response->assertStatus(401);
        $this->assertNotNull(
            TwoFactorMethod::where('user_id', $user->id)
                ->where('method', 'totp')->first()->confirmed_at
        );
    }

    /**
     * @return void
     */
    public function testMethodsListsConfiguredAvailableAndUsersConfirmedMethods(): void
    {
        config([ 'two_factor.available_methods' => [ 'totp', 'email' ] ]);
        $user = $this->_createUser();
        $secret = (new Google2FA())->generateSecretKey();

        TwoFactorMethod::create([
            'user_id' => $user->id, 'method' => 'totp',
            'secret' => $secret, 'confirmed_at' => now()
        ]);
        // Enrôlement démarré mais jamais confirmé : ne doit pas apparaître
        // dans 'enabled'.
        TwoFactorMethod::create([
            'user_id' => $user->id, 'method' => 'email',
            'secret' => null, 'confirmed_at' => null
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/2fa/methods');

        $response->assertStatus(200);
        $this->assertSame([ 'totp', 'email' ], $response->json('data.available'));
        $this->assertSame([ 'totp' ], $response->json('data.enabled'));
    }

    /**
     * @return void
     */
    public function testMethodsRequiresFullAuthentication(): void
    {
        $this->_createUser();

        $response = $this->getJson('/api/auth/2fa/methods');

        $response->assertStatus(401);
    }

    /**
     * A pending 'enroll' response lists the project's configured
     * available methods, so the frontend knows what to offer - nothing
     * is confirmed yet for this user to infer it from otherwise.
     *
     * @return void
     */
    public function testForcedEnrollmentListsConfiguredAvailableMethods(): void
    {
        config([
            'two_factor.force_enrollment' => true,
            'two_factor.available_methods' => [ 'totp' ],
        ]);
        $this->_createUser();

        $response = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ]);

        $this->assertSame([ 'totp' ], $response->json('data.methods'));
    }

    /**
     * A method left out of available_methods is rejected outright, even
     * called directly - the config is an actual restriction, not just
     * what the login response happens to advertise.
     *
     * @return void
     */
    public function testSetupRejectsAMethodNotInAvailableMethods(): void
    {
        config([ 'two_factor.available_methods' => [ 'email' ] ]);
        $user = $this->_createUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/2fa/totp/setup');

        $response->assertStatus(404);
    }

    /**
     * @return void
     */
    public function testEnablingEmailSendsACodeAndConfirmingActivatesIt(): void
    {
        Mail::fake();

        $user = $this->_createUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/2fa/email/enable')->assertStatus(200);

        Mail::assertSent(\Rivet\Mail\BaseMail::class);

        // The code was generated inside the controller and isn't
        // otherwise observable from the test - read it back from the
        // same cache key the service stores it under.
        $code = \Illuminate\Support\Facades\Cache::get(
            "two_factor_email_code:{$user->id}"
        );

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/2fa/email/confirm', [ 'code' => $code ]);

        $response->assertStatus(200);
        $this->assertNotNull(
            TwoFactorMethod::where('user_id', $user->id)
                ->where('method', 'email')->first()->confirmed_at
        );
    }
}
