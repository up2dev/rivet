<?php
/**
 * User class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models\Auth;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Rivet\Data\Models\BaseAuthModel;
use Rivet\Data\Models\Token;
use Rivet\Database\Factories\Auth\UserFactory;

/**
 * User
 *
 * @category Model
 * @package  Rivet\Data\Models\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class User extends BaseAuthModel
{
    use UserTrait, HasFactory, SoftDeletes;

    /**
     * The uid associated with the model log.
     *
     * @var string
     */
    public $log_uid = 'User';

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [ 'is_active' => true ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [ /*'roles'*/ ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [ /*'fullname'*/ ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'pivot', 'deleted_at', 'email_token'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at'    => 'datetime',
        'is_active'            => 'boolean'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'login', 'email', 'email_verified_at', 'password' ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }

    /**
     * -------------------------------------------------------------------------
     * Relations
     * -------------------------------------------------------------------------
     */

    /**
     * Get the User's Roles.
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')->without(
            'users'
        )->without('permissions');
    }

    /**
     * Get the access tokens that belong to model.
     *
     * @return MorphMany
     */
    public function pwdTokens(): MorphMany
    {
        return $this->morphMany(Token::class, 'tokenable');
    }

    /**
     * Get the User's enrolled two-factor methods.
     *
     * @return HasMany
     */
    public function twoFactorMethods(): HasMany
    {
        return $this->hasMany(TwoFactorMethod::class);
    }

    /**
     * -------------------------------------------------------------------------
     * Mutators
     * -------------------------------------------------------------------------
     */

    /**
     * Set the user's email.
     *
     * @param string $value The email value
     *
     * @return void
     */
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower($value);
    }

    /**
     * Set the user's password.
     *
     * @param string $value The password value
     *
     * @return void
     */
    public function setPasswordAttribute(?string $value): void
    {
        $this->attributes['password'] = is_null($value)? null: Hash::make($value);
    }

    /**
     * Create a token.
     *
     * @return string
     */
    public static function emailTokenize(): string
    {
        do {
            $token = Str::random(32);
        } while (!is_null(User::firstWhere('email_token', $token)));

        return $token;
    }
}
