<?php

namespace App\Models;

use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// ToDo を表す Eloquent モデル。単一テーブル `todos` に対応する。
// Eloquent model representing a ToDo item, mapped to the single `todos` table.
class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory;

    // 一括代入を許可する属性を明示し、Mass Assignment 脆弱性を防ぐ。
    // Explicit allow-list for mass assignment to prevent mass-assignment vulnerabilities.
    protected $fillable = [
        'title',
        'description',
        'is_completed',
    ];

    // 新規インスタンスの既定値。作成直後のレスポンスでも is_completed を false として返すため。
    // Default attribute values for new instances, so a just-created record reports is_completed as false
    // in its response without needing a reload from the database.
    protected $attributes = [
        'is_completed' => false,
    ];

    // 属性の型キャスト。is_completed を確実に真偽値として扱う。
    // Attribute casting so `is_completed` is always a real boolean (true JSON boolean, not 0/1).
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
        ];
    }
}
