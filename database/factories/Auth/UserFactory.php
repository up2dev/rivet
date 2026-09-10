<?php
/**
 * User class file
 *
 * PHP Version 8.1
 *
 * @category Factory
 * @package  Rivet\Database\Factories\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Database\Factories\Auth;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * User
 *
 * @category Factory
 * @package  Rivet\Database\Factories\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class UserFactory extends Factory
{
    protected $model = config('crud.user_model');

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'login' => fake()->unique()->safeEmail(),
            'email' => fake()->safeEmail(),
            'email_verified_at' => now(),
            'password' => null
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
