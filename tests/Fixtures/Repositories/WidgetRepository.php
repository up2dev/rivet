<?php
/**
 * WidgetRepository class file - TEST FIXTURE ONLY
 *
 * PHP Version 8.1
 *
 * @category Repository
 * @package  Rivet\Tests\Fixtures\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Fixtures\Repositories;

use Rivet\Data\Repositories\CRUD;
use Rivet\Tests\Fixtures\Models\Widget;

/**
 * WidgetRepository
 *
 * @category Repository
 * @package  Rivet\Tests\Fixtures\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class WidgetRepository extends CRUD
{
    /**
     * Explicitly passes the Model class rather than relying on
     * ns_search()'s naming-convention resolution: this fixture's extra
     * nesting level (Tests\Fixtures\Repositories) defeats that
     * heuristic. This fixture exercises defaultRegister()'s $fillable
     * enforcement, not ns_search itself.
     */
    public function __construct()
    {
        parent::__construct(Widget::class);
    }

    /**
     * The rows available as filters in the query.
     *
     * @var array
     */
    protected $filters = [
        'id'            => 'id',
        'name'          => 'name',
        'active'        => 'is_active',
        'category_raw'  => 'category_id',
        'category'      => 'relation.category'
    ];

    /**
     * Register the fields in database and edit the model.
     *
     * @param array $fields The fields to save
     *
     * @return bool
     */
    protected function register(array $fields): bool
    {
        return $this->defaultRegister($fields);
    }
}
