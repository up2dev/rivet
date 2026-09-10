<?php
/**
 * HostUser class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Support
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Support;

use Rivet\Data\Models\Auth\User;

/**
 * A stand-in for a host application's own User subclass (e.g.
 * App\Data\Models\Auth\User) - same table, no behaviour difference,
 * used only to assert that code resolves config('crud.user_model')
 * rather than hardcoding Rivet\Data\Models\Auth\User directly, which
 * would silently drop a host's own model customizations (default
 * eager-loaded relations, accessors, etc.).
 *
 * @category Test
 * @package  Rivet\Tests\Support
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class HostUser extends User
{
    /**
     * Eloquent infers a table name from a model's OWN class basename,
     * not its parent's - without this, HostUser would resolve to
     * 'host_users', a table that doesn't exist, rather than 'users'.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * Same basename problem as $table above, but for every relation
     * that relies on implicit foreign-key inference (User::roles(),
     * a BelongsToMany, doesn't pass an explicit pivot key) - Eloquent
     * calls getForeignKey() internally for that, which would otherwise
     * return 'host_user_id' instead of the real 'user_id' the
     * 'user_role' pivot table actually has. One override here covers
     * every such relation, rather than patching each one individually.
     *
     * @return string
     */
    public function getForeignKey(): string
    {
        return 'user_id';
    }

    /**
     * A marker only present when this exact subclass was instantiated -
     * the concrete, JSON-observable proof a test can assert on, since
     * "same table, different class" isn't otherwise visible in a
     * response body.
     *
     * @var array
     */
    protected $appends = [ 'is_host_user' ];

    /**
     * @return bool
     */
    public function getIsHostUserAttribute(): bool
    {
        return true;
    }
}
