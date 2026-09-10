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

            // Send password creation link when password is empty
            if (
                config('mail.is_forcing_password_creation') &&
                !is_null($model->email) &&
                // is_null($model->getOriginal('password')) &&
                is_null($model->password) &&
                is_null($model->deleted_at) &&
                $model->is_active
                // $model->pwdTokens()->where(
                //     'purpose', 'pwd_email'
                // )->where(
                //     'expires_at', '>', (new DateTime())->format('Y-m-d H:i:s')
                // )->count() === 0 &&
                // $model->pwdTokens()->where(
                //     'purpose', 'pwd_create'
                // )->where(
                //     'expires_at', '>', (new DateTime())->format('Y-m-d H:i:s')
                // )->count() === 0
            ) {
                $token_string = Token::generateTokenString();
                $duration_min = config('auth.pwd_token_validity');
                $creation_date = new DateTime();

                $model->pwdTokens()->create([
                    'purpose'    => 'pwd_create',
                    'name'       => "pwd_create-{$creation_date->getTimestamp()}",
                    'token'      => $token_string,
                    'expires_at' => $creation_date->modify("+{$duration_min} minutes")
                ]);

                Mail::send(new BaseMail('rivet::emails.auth.password', [
                    'user'             => $model,
                    'token'            => $token_string,
                    'token_expires_at' => $creation_date,
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
}
