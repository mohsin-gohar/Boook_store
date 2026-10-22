<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'title' => fake()->unique()->sentence(3),
            'author' => fake()->name(),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 5, 50),
            'cover_image' => fake()->imageUrl(400, 600, 'books'),
            'stock' => fake()->numberBetween(0, 100),
            'is_bestseller' => fake()->boolean(20),
        ];
    }

    /**
     * Indicate that the book is a bestseller.
     */
    public function bestseller(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_bestseller' => true,
        ]);
    }

    /**
     * Indicate that the book is in stock.
     */
    public function inStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => fake()->numberBetween(10, 100),
        ]);
    }
}
