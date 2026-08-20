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

    {{-- 創作的なフォント（Poppins + Noto Sans JP）。オフライン時はシステムフォントにフォールバック。 --}}
    {{-- Creative fonts (Poppins + Noto Sans JP). Falls back to system fonts when offline. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <main class="app">
        <header class="app__header">
            <h1 class="app__title">
                <span class="app__title-icon" aria-hidden="true">✓</span>
                <span class="app__title-text">
                    <span class="jp">ToDo リスト</span>
                    <span class="en">ToDo List</span>
                </span>
            </h1>
            {{-- 残タスク数の表示（インタラクティブに更新） / live counter of remaining tasks --}}
            <p id="counter" class="app__counter" aria-live="polite"></p>
        </header>

        {{-- グローバルなエラー表示領域（ネットワークエラーなど） --}}
        {{-- Global error banner (e.g. network errors) --}}
        <div id="global-error" class="alert alert--error" role="alert" hidden></div>

        {{-- 追加/編集の兼用フォーム。編集時は既存の値を流し込み、ボタンを「保存」に切り替える。 --}}
        {{-- Dual-purpose add/edit form. In edit mode it is pre-filled and the button becomes "Save". --}}
        <section class="card" id="form-card">
            <h2 class="card__heading" id="form-heading">
                <span class="jp">新しいタスク</span>
                <span class="en">New Task</span>
            </h2>

            <form id="todo-form" novalidate autocomplete="off">
                {{-- 編集対象の ID を保持する隠しフィールド（空なら新規作成） --}}
                {{-- Hidden field holding the id being edited (empty means create) --}}
                <input type="hidden" id="todo-id" value="">

                <div class="field">
                    <label for="form-title">
                        <span class="jp">タイトル<span class="required">（必須）</span></span>
                        <span class="en">Title (required)</span>
                    </label>
                    <input type="text" id="form-title" name="title" maxlength="255" placeholder="例: 牛乳を買う / e.g. Buy milk">
                    <p class="field__error" data-error-for="title"></p>
                </div>

                <div class="field">
                    <label for="form-description">
                        <span class="jp">説明<span class="optional">（任意）</span></span>
                        <span class="en">Description (optional)</span>
                    </label>
                    <textarea id="form-description" name="description" rows="2" placeholder="詳細メモ / details"></textarea>
                    <p class="field__error" data-error-for="description"></p>
                </div>

                <div class="form__actions">
                    <button type="submit" class="btn btn--primary" id="submit-btn">
                        <span class="jp">追加</span>
                        <span class="en">Add</span>
                    </button>
                    {{-- 破棄ボタン: 編集モードのときのみ表示 / Discard: shown only in edit mode --}}
                    <button type="button" class="btn btn--ghost" id="discard-btn" hidden>
                        <span class="jp">破棄</span>
                        <span class="en">Discard</span>
                    </button>
                </div>
            </form>
        </section>

        {{-- 一覧領域: JS が描画する --}}
        {{-- List area: rendered by JS --}}
        <section class="card">
            <h2 class="card__heading">
                <span class="jp">タスク一覧</span>
                <span class="en">Tasks</span>
            </h2>

            <p id="loading" class="state-msg">
                <span class="spinner" aria-hidden="true"></span>
                <span class="jp">読み込み中…</span>
                <span class="en">Loading…</span>
            </p>

            <div id="empty-state" class="state-msg state-msg--empty" hidden>
                <span class="state-msg__emoji" aria-hidden="true">🗒️</span>
                <span class="jp">タスクはまだありません。</span>
                <span class="en">No tasks yet.</span>
            </div>

            <ul id="todo-list" class="todo-list"></ul>
        </section>

        <footer class="app__footer">
            <span class="jp">Laravel + Vanilla JS で構築</span>
            <span class="en">Built with Laravel + Vanilla JS</span>
        </footer>
    </main>

    {{-- 一覧アイテムのテンプレート。JS がクローンして使う。 --}}
    {{-- Template for a list item; cloned by JS. --}}
    <template id="todo-item-template">
        <li class="todo" data-id="">
            <label class="todo__check">
                {{-- 完了トグル / completion toggle --}}
                <input type="checkbox" class="js-toggle">
                <span class="todo__checkbox" aria-hidden="true"></span>
                <span class="visually-hidden">完了を切り替え / Toggle complete</span>
            </label>

            <div class="todo__body">
                <p class="todo__title js-title"></p>
                <p class="todo__description js-description"></p>
            </div>

            <div class="todo__actions">
                <button type="button" class="icon-btn js-edit" title="編集 / Edit" aria-label="編集 / Edit">
                    <span aria-hidden="true">✏️</span>
                </button>
                <button type="button" class="icon-btn icon-btn--danger js-delete" title="削除 / Delete" aria-label="削除 / Delete">
                    <span aria-hidden="true">🗑️</span>
                </button>
            </div>
        </li>
    </template>

    {{-- 削除確認モーダル（プロフェッショナルなダイアログ） --}}
    {{-- Delete confirmation modal (professional dialog) --}}
    <div id="delete-modal" class="modal" hidden>
        <div class="modal__overlay" data-close="true"></div>
        <div class="modal__dialog" role="alertdialog" aria-modal="true" aria-labelledby="modal-title" aria-describedby="modal-desc">
            <div class="modal__icon" aria-hidden="true">⚠️</div>
            <h3 class="modal__title" id="modal-title">
                <span class="jp">本当に削除しますか？</span>
                <span class="en">Delete this task?</span>
            </h3>
            <p class="modal__desc" id="modal-desc">
                <span class="jp">この操作は元に戻せません。</span>
                <span class="en">This action cannot be undone.</span>
            </p>
            {{-- 対象タスクのタイトルを表示（textContent で安全に挿入） --}}
            {{-- Shows the target task's title (inserted safely via textContent) --}}
            <p class="modal__target" id="modal-target"></p>

            <div class="modal__actions">
                <button type="button" class="btn btn--ghost" id="modal-cancel">
                    <span class="jp">キャンセル</span>
                    <span class="en">Cancel</span>
                </button>
                <button type="button" class="btn btn--danger" id="modal-confirm">
                    <span class="jp">削除する</span>
                    <span class="en">Delete</span>
                </button>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
