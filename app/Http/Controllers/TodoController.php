<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTodoRequest;
use App\Http\Requests\UpdateTodoRequest;
use App\Http\Resources\TodoResource;
use App\Models\Todo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

// ToDo の CRUD を担う RESTful なリソースコントローラ。
// RESTful resource controller handling ToDo CRUD.
// メソッドは薄く保ち、検証は FormRequest、出力整形は Resource に委譲する。
// Methods stay thin: validation is delegated to FormRequests, output shaping to Resources.
class TodoController extends Controller
{
    // 全 ToDo を新しい順で返す。
    // Return all ToDos, newest first.
    public function index(): AnonymousResourceCollection
    {
        // 主キーの降順で並べ、同一秒に作成された場合でも順序を確定させる。
        // Order by primary key descending so ordering is deterministic even for rows created in the same second.
        return TodoResource::collection(
            Todo::query()->latest('id')->get()
        );
    }

    // 新しい ToDo を作成し、201 Created で返す。
    // Create a new ToDo and return it with 201 Created.
    public function store(StoreTodoRequest $request): JsonResponse
    {
        // validated() で通過した値のみを使い、意図しない入力を防ぐ。
        // Use only validated() input to avoid unintended fields.
        $todo = Todo::create($request->validated());

        return TodoResource::make($todo)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    // 既存の ToDo の title / description を更新する。
    // Update an existing ToDo's title / description.
    // {todo} はルートモデルバインディングで解決され、無ければ 404 になる。
    // {todo} is resolved via route-model binding; a missing id yields 404.
    public function update(UpdateTodoRequest $request, Todo $todo): TodoResource
    {
        $todo->update($request->validated());

        return TodoResource::make($todo);
    }

    // 完了状態をトグルする専用エンドポイント。
    // Dedicated endpoint that toggles the completion state.
    // 「完了/未完了の切り替え」という意図が URL から明確になるため update とは分離した。
    // Kept separate from update() so the intent (toggle done/undone) is explicit in the URL.
    public function complete(Todo $todo): TodoResource
    {
        $todo->update(['is_completed' => ! $todo->is_completed]);

        return TodoResource::make($todo);
    }

    // ToDo を削除し、本文なしの 204 No Content を返す。
    // Delete a ToDo and return 204 No Content (no body).
    public function destroy(Todo $todo): Response
    {
        $todo->delete();

        return response()->noContent();
    }
}
