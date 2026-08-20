<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// ToDo 作成時の入力バリデーションをカプセル化する FormRequest。
// FormRequest encapsulating validation for creating a ToDo.
// コントローラを薄く保つため、検証ロジックはここに集約する。
// Validation lives here to keep the controller thin.
class StoreTodoRequest extends FormRequest
{
    // 認証は不要（単一ユーザー前提）なので常に許可する。
    // No auth in this single-user app, so always authorize.
    public function authorize(): bool
    {
        return true;
    }

    // バリデーションルール。title は必須、description は任意。
    // Validation rules: title is required, description is optional.
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // 過大なペイロードを防ぐため説明にも上限を設ける。
            // Cap the description too, to guard against oversized payloads.
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    // 日英併記のエラーメッセージ。フロントでそのまま表示できるようにする。
    // Bilingual (JP/EN) error messages, ready to be shown directly in the UI.
    public function messages(): array
    {
        return [
            'title.required' => 'タイトルは必須です。 / Title is required.',
            'title.max' => 'タイトルは255文字以内で入力してください。 / Title must be 255 characters or fewer.',
            'title.string' => 'タイトルは文字列で入力してください。 / Title must be a string.',
            'description.string' => '説明は文字列で入力してください。 / Description must be a string.',
            'description.max' => '説明は5000文字以内で入力してください。 / Description must be 5000 characters or fewer.',
        ];
    }
}
