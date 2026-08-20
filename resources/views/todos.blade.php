<!DOCTYPE html>
{{-- 単一ページ（SPA）の唯一の Blade ビュー。一覧はサーバー描画せず、Ajax で取得する。 --}}
{{-- The single Blade view (SPA). The list is NOT server-rendered; it is fetched via Ajax. --}}
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- CSRF トークン: JS がこの値を X-CSRF-TOKEN ヘッダーで送信する --}}
    {{-- CSRF token: the JS reads this and sends it in the X-CSRF-TOKEN header --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>ToDo リスト / ToDo List</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <main class="app">
        <header class="app__header">
            <h1 class="app__title">
                <span class="jp">ToDo リスト</span>
                <span class="en">ToDo List</span>
            </h1>
        </header>

        {{-- グローバルなエラー表示領域（ネットワークエラーなど） --}}
        {{-- Global error banner (e.g. network errors) --}}
        <div id="global-error" class="alert alert--error" role="alert" hidden></div>

        {{-- 作成フォーム --}}
        {{-- Create form --}}
        <section class="card">
            <h2 class="card__heading">
                <span class="jp">新しいタスク</span>
                <span class="en">New Task</span>
            </h2>
            <form id="create-form" novalidate>
                <div class="field">
                    <label for="create-title">
                        <span class="jp">タイトル<span class="required">（必須）</span></span>
                        <span class="en">Title (required)</span>
                    </label>
                    <input type="text" id="create-title" name="title" maxlength="255" autocomplete="off">
                    {{-- タイトル項目のエラー / per-field error for title --}}
                    <p class="field__error" data-error-for="title"></p>
                </div>

                <div class="field">
                    <label for="create-description">
                        <span class="jp">説明<span class="optional">（任意）</span></span>
                        <span class="en">Description (optional)</span>
                    </label>
                    <textarea id="create-description" name="description" rows="2"></textarea>
                    <p class="field__error" data-error-for="description"></p>
                </div>

                <button type="submit" class="btn btn--primary">
                    <span class="jp">追加</span>
                    <span class="en">Add</span>
                </button>
            </form>
        </section>

        {{-- 一覧領域: JS が描画する --}}
        {{-- List area: rendered by JS --}}
        <section class="card">
            <h2 class="card__heading">
                <span class="jp">タスク一覧</span>
                <span class="en">Tasks</span>
            </h2>

            {{-- 読み込み中の表示 / loading state --}}
            <p id="loading" class="muted">
                <span class="jp">読み込み中…</span>
                <span class="en">Loading…</span>
            </p>

            {{-- 空状態の表示 / empty state --}}
            <p id="empty-state" class="muted" hidden>
                <span class="jp">タスクはまだありません。</span>
                <span class="en">No tasks yet.</span>
            </p>

            <ul id="todo-list" class="todo-list"></ul>
        </section>
    </main>

    {{-- 一覧アイテムのテンプレート。JS がクローンして使う。 --}}
    {{-- Template for a list item; cloned by JS. --}}
    <template id="todo-item-template">
        <li class="todo" data-id="">
            <div class="todo__main">
                <label class="todo__check">
                    {{-- 完了トグル / completion toggle --}}
                    <input type="checkbox" class="js-toggle">
                    <span class="visually-hidden">
                        完了にする / Mark complete
                    </span>
                </label>
                <div class="todo__body">
                    <p class="todo__title js-title"></p>
                    <p class="todo__description js-description"></p>
                </div>
            </div>

            <div class="todo__actions">
                <button type="button" class="btn btn--small js-edit">
                    <span class="jp">編集</span><span class="en">Edit</span>
                </button>
                <button type="button" class="btn btn--small btn--danger js-delete">
                    <span class="jp">削除</span><span class="en">Delete</span>
                </button>
            </div>

            {{-- インライン編集フォーム（初期は非表示） --}}
            {{-- Inline edit form (hidden initially) --}}
            <form class="todo__edit js-edit-form" hidden novalidate>
                <div class="field">
                    <label>
                        <span class="jp">タイトル</span><span class="en">Title</span>
                    </label>
                    <input type="text" class="js-edit-title" name="title" maxlength="255">
                    <p class="field__error" data-error-for="title"></p>
                </div>
                <div class="field">
                    <label>
                        <span class="jp">説明</span><span class="en">Description</span>
                    </label>
                    <textarea class="js-edit-description" name="description" rows="2"></textarea>
                    <p class="field__error" data-error-for="description"></p>
                </div>
                <div class="todo__edit-actions">
                    <button type="submit" class="btn btn--primary btn--small">
                        <span class="jp">保存</span><span class="en">Save</span>
                    </button>
                    <button type="button" class="btn btn--small js-cancel">
                        <span class="jp">キャンセル</span><span class="en">Cancel</span>
                    </button>
                </div>
            </form>
        </li>
    </template>

    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
