<?php
/**
 * MediaFactory class file
 *
 * PHP Version 8.1
 *
 * @category Factory
 * @package  Rivet\Database\Factories\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Database\Factories\Storage;

use Illuminate\Database\Eloquent\Factories\Factory;
use Rivet\Data\Models\Storage\Media;

/**
 * MediaFactory
 *
 * @category Factory
 * @package  Rivet\Database\Factories\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

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
            'comments' => fake()->sentence(10),
            'max_chunk' => rand(1, 32767),
            'mimetype' => '*/*',
            'min_width' => rand(1, 32767),
            'max_width' => rand(1, 32767),
            'min_height' => rand(1, 32767),
            'max_height' => rand(1, 32767)
        ];
    }
}
