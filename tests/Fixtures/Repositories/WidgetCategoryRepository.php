<?php
/**
 * WidgetCategoryRepository class file - TEST FIXTURE ONLY
 *
 * PHP Version 8.1
 *
 * @category Repository
 * @package  Rivet\Tests\Fixtures\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Fixtures\Repositories;

use Rivet\Data\Repositories\CRUD;
use Rivet\Tests\Fixtures\Models\WidgetCategory;

/**
 * Placed and named so CRUD's ns_search()-based repository resolution
 * finds it from WidgetCategory when building a join, rather than
 * being wired explicitly like WidgetRepository.
 *
 * @category Repository
 * @package  Rivet\Tests\Fixtures\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class WidgetCategoryRepository extends CRUD
{
    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct(WidgetCategory::class);
    }

    /**
     * @var array
     */
    protected $filters = [ 'name' => 'name' ];

    /**
     * @param array $fields The fields to save
     *
     * @return bool
     */
    protected function register(array $fields): bool
    {
        return $this->defaultRegister($fields);
    }
}
