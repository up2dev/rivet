<?php
/**
 * AccessToken class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * AccessToken
 *
 * @category Model
 * @package  Rivet\Data\Models\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class Token extends BaseModel
{
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'last_used_at' => 'datetime', 'expires_at' => 'datetime'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', 'token', 'purpose', 'abilities', 'expires_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [ 'token' ];

    /**
     * -------------------------------------------------------------------------
     * Relations
     * -------------------------------------------------------------------------
     */

    /**
     * Get the tokenable model that the access token belongs to.
     *
     * @return MorphTo
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo('tokenable');
    }

    /**
     * -------------------------------------------------------------------------
     * Mutators
     * -------------------------------------------------------------------------
     */

    /**
     * Set the token's token.
     *
     * @param string $value The token value
     *
     * @return void
     */
    public function setTokenAttribute(string $value): void
    {
        $this->attributes['token'] = hash('sha256', $value);
    }

    /**
     * Generate the token string.
     *
     * @return string
     */
    public static function generateTokenString(): string
    {
        return sprintf(
            '%s%s%s',
            config('auth.token_prefix'),
            $tokenEntropy = Str::random(40),
            hash('crc32b', $tokenEntropy)
        );
    }
}
