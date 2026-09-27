<?php
/**
 * Token class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Token
 *
 * Single-use tokens (password creation/reset...). Only the SHA-256 hash
 * of the plain token is stored.
 *
 * @category Model
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class Token extends BaseModel
{
    use MassPrunable;

    /**
     * Password creation token (new account without password).
     */
    public const PURPOSE_PWD_CREATE = 'pwd_create';

    /**
     * Password reset token ("forgot password").
     */
    public const PURPOSE_PWD_FORGOT = 'pwd_forgot';

    /**
     * Purposes allowed to set a password.
     */
    public const PASSWORD_PURPOSES = [
        self::PURPOSE_PWD_CREATE, self::PURPOSE_PWD_FORGOT
    ];

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
    protected $fillable = [ 'name', 'token', 'purpose', 'expires_at' ];

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
     * Get the model the token belongs to.
     *
     * @return MorphTo
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo('tokenable');
    }

    /**
     * -------------------------------------------------------------------------
     * Scopes
     * -------------------------------------------------------------------------
     */

    /**
     * Tokens with one of the given purposes.
     *
     * @param Builder         $query    The query
     * @param string|string[] $purposes The purpose(s)
     *
     * @return Builder
     */
    public function scopePurpose(Builder $query, string|array $purposes): Builder
    {
        return $query->whereIn('purpose', (array) $purposes);
    }

    /**
     * Tokens not expired yet (no expiry = never expires).
     *
     * @param Builder $query The query
     *
     * @return Builder
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * -------------------------------------------------------------------------
     * Mutators & helpers
     * -------------------------------------------------------------------------
     */

    /**
     * Store only the hash of the token.
     *
     * @param string $value The plain token
     *
     * @return void
     */
    public function setTokenAttribute(string $value): void
    {
        $this->attributes['token'] = self::hashToken($value);
    }

    /**
     * Whether the token is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return !is_null($this->expires_at) && $this->expires_at->isPast();
    }

    /**
     * Find a valid token from its plain value.
     *
     * @param string          $plain    The plain token
     * @param string|string[] $purposes The accepted purpose(s)
     *
     * @return static|null
     */
    public static function findValid(string $plain, string|array $purposes): ?static
    {
        return static::where('token', self::hashToken($plain))
            ->purpose($purposes)->valid()->first();
    }

    /**
     * Hash a plain token as stored in DB.
     *
     * @param string $plain The plain token
     *
     * @return string
     */
    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Generate a plain token string (prefix + entropy + CRC32 checksum).
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

    /**
     * Expired tokens, removed by `php artisan model:prune`.
     *
     * @return Builder
     */
    public function prunable(): Builder
    {
        return static::whereNotNull('expires_at')->where('expires_at', '<=', now());
    }
}
