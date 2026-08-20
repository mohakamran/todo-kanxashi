<?php

use App\Http\Controllers\TodoController;
use Illuminate\Support\Facades\Route;

// トップページ: 単一の Blade ビュー（SPA）を返す。
// Root page: serve the single Blade view (SPA shell).
// クロージャではなく Route::view を使い、route:cache でシリアライズ可能にする。
// Uses Route::view (not a closure) so the route remains serializable under route:cache.
Route::view('/', 'todos');

// ToDo の JSON API。
// ToDo JSON API.
//
// これらは web.php に定義しているため web ミドルウェアグループ（セッション + CSRF 検証）が適用される。
// Defined here in web.php so they run through the `web` middleware group (session + CSRF verification),
// フロントは <meta name="csrf-token"> の値を X-CSRF-TOKEN ヘッダーで送信する。
// which is why the frontend sends the <meta name="csrf-token"> value in the X-CSRF-TOKEN header.
Route::prefix('api')->group(function () {
    // 一覧取得 / list
    Route::get('todos', [TodoController::class, 'index']);
    // 作成 / create
    Route::post('todos', [TodoController::class, 'store']);
    // 更新 / update
    Route::put('todos/{todo}', [TodoController::class, 'update']);
    // 完了状態トグル / toggle completion
    Route::patch('todos/{todo}/complete', [TodoController::class, 'complete']);
    // 削除 / delete
    Route::delete('todos/{todo}', [TodoController::class, 'destroy']);
});
