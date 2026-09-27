<?php
/**
 * UserTrait class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models\Auth;

use DateTime;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Request;
use Rivet\Data\Models\Mailing\Sendmail;
use Rivet\Data\Models\Token;
use Rivet\Mail\BaseMail;
use Rivet\Support\FrontendUrl;

/**
 * UserTrait
 *
 * @category Model
 * @package  Rivet\Data\Models\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
trait UserTrait
{
    /**
     * Boot the trait
     *
     * @return void
     */
    protected static function bootUserTrait(): void
    {
        static::saving(function (User $model) {
            if (is_null($model->email_verified_at) && !config('app.is_mail_checked')) {
                $model->email_verified_at = new \DateTime();
            }

            if ($model->getOriginal(
                'email_verified_at'
            ) !== $model->email_verified_at) {
                $model->email_token = null;
            }

            if (is_null($model->email_verified_at)) {
                $model->email_token = User::emailTokenize();
            }

            // if (!is_null($model->password) && Hash::needsRehash($model->password)) {
            //     $model->password = Hash::make($model->password);
            // }
        });

        // Password tokens are useless once the user is gone for good.
        static::forceDeleted(function (User $model) {
            $model->pwdTokens()->delete();
        });

        static::saved(function (User $model) {
            // Send email validation when a new mail token is generated
            if (
                !is_null($model->email) &&
                !is_null($model->email_token) &&
                $model->getOriginal('email_token') !== $model->email_token
            ) {
                $model->email_verified_at = (
                    config('auth.is_mail_relocked')? null: $model->email_verified_at
                );
                $model->saveQuietly();

                Mail::send(new BaseMail('rivet::emails.user.validate', [
                    'user' => $model,
                    'token' => $model->email_token,
                    'subject' => trans('rivet::mail.subject_user_validate')
                ]));
            }

            // Send email validation success on email verification
            if (
                config('mail.is_mail_checked') &&
                !is_null($model->email) &&
                !is_null($model->email_verified_at) &&
                (
                    is_null($model->getOriginal('email_verified_at')) ||
                    $model->email_verified_at->ne($model->getOriginal('email_verified_at'))
                )
            ) {
                Mail::send(new BaseMail('rivet::emails.user.email', [
                    'user' => $model,
                    'subject' => trans('rivet::mail.subject_user_validates')
                ]));
            }

            // Send password creation link when password is empty - once:
            // no new link while a previous one is still valid (every
            // other save of the user used to send a new email).
            if (
                config('mail.is_forcing_password_creation') &&
                !is_null($model->email) &&
                is_null($model->password) &&
                is_null($model->deleted_at) &&
                $model->is_active &&
                !$model->pwdTokens()->purpose(
                    Token::PURPOSE_PWD_CREATE
                )->valid()->exists()
            ) {
                [ $token_string, $expires_at ] = $model->issuePasswordToken(
                    Token::PURPOSE_PWD_CREATE
                );

                // Résolu ICI, de façon synchrone, pendant que la requête
                // HTTP d'origine (et son en-tête Accept-Language) existe
                // encore — jamais dans le template Blade, qui peut être
                // rendu plus tard par un job de queue sans contexte de
                // requête (voir FrontendUrl::build()).
                Mail::send(new BaseMail('rivet::emails.auth.password', [
                    'user'             => $model,
                    'token'            => $token_string,
                    'token_expires_at' => $expires_at,
                    'url'              => FrontendUrl::build('password', $token_string),
                    'subject'          => trans('rivet::mail.subject_auth_password')
                ]));
            }

            // Send password creation success on password change
            if (
                config('mail.is_confirming_password') &&
                !is_null($model->email) &&
                Request::has('password') &&
                Hash::check(Request::get('password'), $model->password)
            ) {
                Mail::send(new BaseMail('rivet::emails.user.password', [
                    'user' => $model,
                    'subject' => trans('rivet::mail.subject_user_password')
                ]));
            }
        });
    }

    /**
     * Create a password token, revoking the previous ones of this purpose.
     *
     * @param string $purpose Token::PURPOSE_PWD_CREATE|PURPOSE_PWD_FORGOT
     *
     * @return array{0: string, 1: DateTime} Plain token and expiry date
     */
    public function issuePasswordToken(string $purpose): array
    {
        $token_string = Token::generateTokenString();
        $creation_date = new DateTime();
        $expires_at = (clone $creation_date)->modify(
            '+' . (int) config('auth.pwd_token_validity') . ' minutes'
        );

        $this->pwdTokens()->purpose($purpose)->delete();
        $this->pwdTokens()->create([
            'purpose'    => $purpose,
            'name'       => "{$purpose}-{$creation_date->getTimestamp()}",
            'token'      => $token_string,
            'expires_at' => $expires_at
        ]);

        return [ $token_string, $expires_at ];
    }

    /**
     * Revoke every password token of the user.
     *
     * @return void
     */
    public function revokePasswordTokens(): void
    {
        $this->pwdTokens()->purpose(Token::PASSWORD_PURPOSES)->delete();
    }
}
