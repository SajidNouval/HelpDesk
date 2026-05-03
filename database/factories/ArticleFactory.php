<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition()
    {
        return [
            'title' => $this->faker->sentence(),
            'slug' => Str::slug($this->faker->sentence()),
            'content' => $this->faker->paragraphs(3, true),
            'category_id' => Category::factory(),
            'staff_id' => User::factory(),
            'is_published' => false,
            'publish_status' => 'pending',
            'views' => $this->faker->numberBetween(0, 1000),
        ];
    }

    public function published()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_published' => true,
                'publish_status' => 'approved',
            ];
        });
    }

    public function pending()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_published' => false,
                'publish_status' => 'pending',
            ];
        });
    }

    public function approved()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_published' => true,
                'publish_status' => 'approved',
            ];
        });
    }

    public function rejected()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_published' => false,
                'publish_status' => 'rejected',
                'rejection_note' => $this->faker->sentence(),
            ];
        });
    }
}