<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// ToDo の JSON 出力形状を一元管理する API リソース。
// API Resource that centralizes and controls the JSON output shape of a ToDo.
// これによりフロントに返す構造が常に一貫する。
// Guarantees a consistent structure returned to the frontend.
class TodoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'is_completed' => $this->is_completed, // boolean にキャスト済み / already cast to boolean
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
