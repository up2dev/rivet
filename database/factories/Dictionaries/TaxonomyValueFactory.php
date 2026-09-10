<?php
/**
 * TaxonomyValueFactory class file
 *
 * PHP Version 8.1
 *
 * @category Factory
 * @package  Rivet\Database\Factories\Dictionaries
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Database\Factories\Dictionaries;

use Illuminate\Database\Eloquent\Factories\Factory;
use Rivet\Data\Models\Dictionaries\TaxonomyValue;

/**
 * TaxonomyValueFactory
 *
 * @category Factory
 * @package  Rivet\Database\Factories\Dictionaries
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class TaxonomyValueFactory extends Factory
{
    protected $model = TaxonomyValue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uid' => fake()->unique()->word,
            'value' => fake()->jobTitle()
        ];
    }
}
