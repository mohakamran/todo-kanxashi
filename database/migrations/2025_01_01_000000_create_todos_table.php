<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// todos テーブルを定義するマイグレーション。スキーマの唯一の情報源。
// Migration defining the `todos` table. The single source of truth for the schema.
return new class extends Migration
{
    // テーブルを作成する。
    // Create the table.
    public function up(): void
    {
        Schema::create('todos', function (Blueprint $table) {
            $table->id();                                   // 主キー / primary key (BIGINT UNSIGNED, auto-increment)
            $table->string('title');                        // タイトル（必須）/ title (required, VARCHAR 255)
            $table->text('description')->nullable();        // 説明（任意）/ description (optional)
            $table->boolean('is_completed')->default(false); // 完了状態 / completion flag, defaults to false
            $table->timestamps();                           // created_at / updated_at (Laravel 管理)
        });
    }

    // ロールバック時にテーブルを削除する。
    // Drop the table on rollback.
    public function down(): void
    {
        Schema::dropIfExists('todos');
    }
};
