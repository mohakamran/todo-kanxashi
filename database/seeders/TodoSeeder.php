<?php

namespace Database\Seeders;

use App\Models\Todo;
use Illuminate\Database\Seeder;

// 初回起動時の動作確認用に、サンプルの ToDo を数件投入するシーダー。
// Seeder that inserts a few sample ToDos so a reviewer can verify the app on first run.
class TodoSeeder extends Seeder
{
    public function run(): void
    {
        // 冪等性のため既存データを一旦クリアしてから投入する。
        // Clear existing rows first so re-seeding stays idempotent.
        Todo::query()->delete();

        $samples = [
            [
                'title' => '牛乳を買う / Buy milk',
                'description' => 'スーパーで低脂肪乳を1本 / One carton of low-fat milk from the store',
                'is_completed' => false,
            ],
            [
                'title' => 'コードレビューを依頼する / Request a code review',
                'description' => 'ToDo API のプルリクをチームに共有 / Share the ToDo API pull request with the team',
                'is_completed' => false,
            ],
            [
                'title' => 'ジムに行く / Go to the gym',
                'description' => null,
                'is_completed' => true,
            ],
        ];

        foreach ($samples as $sample) {
            Todo::create($sample);
        }
    }
}
