<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// アプリ全体のシーダーのエントリーポイント。
// The root seeder entry point for the application.
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ToDo のサンプルデータを投入する。
        // Seed the sample ToDo data.
        $this->call(TodoSeeder::class);
    }
}
