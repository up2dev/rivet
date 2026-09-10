<?php
/**
 * PermissionFactory class file
 *
 * PHP Version 8.1
 *
 * @category Factory
 * @package  Rivet\Database\Factories\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Database\Factories\Auth;

use Illuminate\Database\Eloquent\Factories\Factory;
use Rivet\Data\Models\Auth\Permission;

/**
 * PermissionFactory
 *
 * @category Factory
 * @package  Rivet\Database\Factories\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uid' => fake()->unique()->word,
            'name' => fake()->jobTitle()
        ];
    }
}
