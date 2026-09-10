<?php
/**
 * TaxonomyFactory class file
 *
 * PHP Version 8.1
 *
 * @category Factory
 * @package  Rivet\Database\Factories\Dictionaries
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Database\Factories\Dictionaries;

use Illuminate\Database\Eloquent\Factories\Factory;
use Rivet\Data\Models\Dictionaries\Taxonomy;

/**
 * TaxonomyFactory
 *
 * @category Factory
 * @package  Rivet\Database\Factories\Dictionaries
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class TaxonomyFactory extends Factory
{
    protected $model = Taxonomy::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uid' => fake()->unique()->word,
            'name' => fake()->jobTitle(),
            'is_ordered' => false
        ];
    }
}
