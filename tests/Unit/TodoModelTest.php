<?php

namespace Tests\Unit;

use App\Models\Todo;
use PHPUnit\Framework\TestCase;

// Todo モデルの単体テスト。DB を使わずモデルの契約（既定値・キャスト・fillable）を検証する。
// Unit tests for the Todo model. No database — they verify the model's contract
// (defaults, casts, fillable).
class TodoModelTest extends TestCase
{
    // 新規インスタンスの is_completed は既定で false になる。
    // A new instance defaults is_completed to false.
    public function test_is_completed_defaults_to_false(): void
    {
        $todo = new Todo;

        $this->assertFalse($todo->is_completed);
    }

    // is_completed は真偽値へキャストされる（"1" → true, 0 → false）。
    // is_completed is cast to a boolean ("1" → true, 0 → false).
    public function test_is_completed_is_cast_to_boolean(): void
    {
        $todo = new Todo;

        $todo->is_completed = '1';
        $this->assertTrue($todo->is_completed);

        $todo->is_completed = 0;
        $this->assertFalse($todo->is_completed);
    }

    // fillable は title / description / is_completed のみを許可する（Mass Assignment 対策）。
    // fillable allows only title / description / is_completed (mass-assignment protection).
    public function test_fillable_is_limited(): void
    {
        $todo = new Todo;

        $this->assertSame(
            ['title', 'description', 'is_completed'],
            $todo->getFillable()
        );
    }

    // id など fillable 外の属性は一括代入で設定されない。
    // Attributes outside fillable (e.g. id) are not set via mass assignment.
    public function test_guarded_attributes_are_not_mass_assignable(): void
    {
        $todo = new Todo(['id' => 999, 'title' => 'Safe']);

        $this->assertNull($todo->id);
        $this->assertSame('Safe', $todo->title);
    }
}
