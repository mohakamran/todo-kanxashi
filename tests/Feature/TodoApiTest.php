<?php

namespace Tests\Feature;

use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ToDo API のフィーチャーテスト。各エンドポイントの契約を検証する。
// Feature tests for the ToDo API, verifying each endpoint's contract.
class TodoApiTest extends TestCase
{
    // 各テストごとに DB を初期化する（in-memory sqlite）。
    // Reset the DB for every test (in-memory sqlite).
    use RefreshDatabase;

    // 一覧は新しい順で JSON として返る。
    // Index returns todos as JSON, newest first.
    public function test_index_returns_todos(): void
    {
        Todo::create(['title' => 'First']);
        Todo::create(['title' => 'Second']);

        $response = $this->getJson('/api/todos');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Second'); // latest first
    }

    // 作成は 201 を返し、DB に保存される。
    // Store returns 201 and persists the row.
    public function test_store_creates_todo(): void
    {
        $response = $this->postJson('/api/todos', [
            'title' => 'Buy milk',
            'description' => 'low fat',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Buy milk')
            ->assertJsonPath('data.is_completed', false);

        $this->assertDatabaseHas('todos', ['title' => 'Buy milk']);
    }

    // title 未入力は 422 とエラーボディを返す。
    // Missing title yields 422 with an error body.
    public function test_store_requires_title(): void
    {
        $response = $this->postJson('/api/todos', ['title' => '']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    // 更新で title / description が変更できる。
    // Update changes title / description.
    public function test_update_modifies_todo(): void
    {
        $todo = Todo::create(['title' => 'Old']);

        $response = $this->putJson("/api/todos/{$todo->id}", [
            'title' => 'New',
            'description' => 'updated',
        ]);

        $response->assertOk()->assertJsonPath('data.title', 'New');
        $this->assertDatabaseHas('todos', ['id' => $todo->id, 'title' => 'New']);
    }

    // 完了トグルは真偽値を反転させる。
    // Toggle flips the completion boolean.
    public function test_complete_toggles_state(): void
    {
        $todo = Todo::create(['title' => 'Task', 'is_completed' => false]);

        $this->patchJson("/api/todos/{$todo->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);

        $this->patchJson("/api/todos/{$todo->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.is_completed', false);
    }

    // 削除は 204 を返し、行が消える。
    // Destroy returns 204 and removes the row.
    public function test_destroy_deletes_todo(): void
    {
        $todo = Todo::create(['title' => 'Delete me']);

        $this->deleteJson("/api/todos/{$todo->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('todos', ['id' => $todo->id]);
    }

    // 存在しない ID は 404 を返す。
    // A missing id returns 404.
    public function test_missing_todo_returns_404(): void
    {
        $this->getJson('/api/todos'); // warm up
        $this->putJson('/api/todos/999', ['title' => 'x'])
            ->assertNotFound();
    }
}
