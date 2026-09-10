<?php
/**
 * ScopesQueryToUser trait file
 *
 * PHP Version 8.1
 *
 * @category Trait
 * @package  Rivet\Data\Repositories\Concerns
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Repositories\Concerns;

use Illuminate\Support\Facades\Schema;

/**
 * Restricts a query to the current user's own rows when the model has
 * a user-owning column, with an exemption list for specific Controller
 * actions.
 *
 * @category Trait
 * @package  Rivet\Data\Repositories\Concerns
 * @license  https://opensource.org/licenses/MIT MIT License
 */
trait ScopesQueryToUser
{
    /**
     * The calling Controller's action names exempted from the
     * per-user query scoping below, set by
     * BaseController::$AUTH_UNLIMITED.
     *
     * @var string[]
     */
    protected array $auth_unlimited = [];

    /**
     * Set the calling Controller's unlimited action names.
     *
     * @param string[] $methods The Controller::$AUTH_UNLIMITED value
     *
     * @return void
     */
    public function setAuthUnlimited(array $methods): void
    {
        $this->auth_unlimited = $methods;
    }

    /**
     * Format Query filter by user to limit resource access.
     * Only work if the resource is link to the user via a user_id row.
     *
     * @param array $fields Add user_id = Auth::id to the included fields
     *
     * @return void
     */
    protected function setQueryLimiters(?array &$fields = null): void
    {
        $urelation = config('crud.user_relation');
        $ufk = config('crud.user_fk');

        if (
            Schema::hasColumn($this->getTable(), $ufk) && auth()->check()
        ) {
            $action = optional(request()->route())->getActionMethod();

            if (!in_array($action, $this->auth_unlimited)) {
                if (is_null($fields)) {
                    $this->query->where(
                        function ($q) use ($ufk) {
                            $q->where($ufk, auth()->user()->id)->orWhereNull($ufk);
                        }
                    );
                } elseif (!is_null($this->model)) {
                    $this->model->$urelation()->associate(auth()->user());
                }
            }
        }
    }
}
