<?php

namespace Database\Factories;

use App\Models\Todo;
use Illuminate\Database\Eloquent\Factories\Factory;

// テスト用に ToDo のダミーデータを生成するファクトリー。
// Factory generating fake ToDo data for tests.
/**
 * @extends Factory<Todo>
 */
class TodoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->boolean(70) ? fake()->paragraph() : null,
            'is_completed' => fake()->boolean(30),
        ];
    }

    // 完了済みの状態を作るためのステート。
    // State helper producing a completed ToDo.
    public function completed(): static
    {
        return $this->state(fn () => ['is_completed' => true]);
    }
}
