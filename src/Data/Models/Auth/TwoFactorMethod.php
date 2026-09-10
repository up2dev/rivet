<?php
/**
 * TwoFactorMethod class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models\Auth;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Rivet\Data\Models\BaseModel;

/**
 * One enrolled two-factor method ('totp' or 'email') for a user. A row
 * with a null 'confirmed_at' is a method mid-enrollment: it does not
 * yet satisfy login verification and is not returned as an available
 * method until confirmed.
 *
 * @category Model
 * @package  Rivet\Data\Models\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class TwoFactorMethod extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_two_factor_methods';

    /**
     * The uid associated with the model log.
     *
     * @var string|null
     */
    public $log_uid = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'user_id', 'method', 'secret', 'confirmed_at' ];

    /**
     * The attributes excluded from the model's JSON form - the TOTP
     * secret is never returned once stored (only shown once, at
     * setup time, in the setup response itself).
     *
     * @var array
     */
    protected $hidden = [ 'secret' ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [ 'confirmed_at' => 'datetime' ];

    /**
     * -------------------------------------------------------------------------
     * Relations
     * -------------------------------------------------------------------------
     */

    /**
     * Get the owning User.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
