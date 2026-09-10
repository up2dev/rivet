<?php
/**
 * FileFactory class file
 *
 * PHP Version 8.1
 *
 * @category Factory
 * @package  Rivet\Database\Factories\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Database\Factories\Storage;

use Illuminate\Database\Eloquent\Factories\Factory;
use Rivet\Data\Models\Storage\File;

/**
 * FileFactory
 *
 * @category Factory
 * @package  Rivet\Database\Factories\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->jobTitle()
        ];
        // $table->string('token');
        // $table->string('extension');
        // $table->unsignedInteger('size');
        // $table->foreignId('media_id')->references('id')->on(
        //     'medias'
        // )->onDelete('cascade');
        // $table->timestamps();
        // $table->softDeletes();
    }
}
