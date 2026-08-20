/**
 * ToDo SPA のフロントエンドロジック。
 * Frontend logic for the ToDo SPA.
 *
 * すべての CRUD を fetch (Ajax) で行い、ページ遷移・リロードなしに DOM を更新する。
 * All CRUD is done via fetch (Ajax); the DOM is updated in place with no navigation/reload.
 */
(() => {
    'use strict';

    // API のベースパス。ルート定義（web.php の api プレフィックス）と一致させる。
    // API base path; must match the route definitions (the `api` prefix in web.php).
    const API_BASE = '/api/todos';

    // CSRF トークンを <meta> から取得する。全ての更新系リクエストで送信する。
    // Read the CSRF token from <meta>; sent on every mutating request.
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // よく使う DOM 要素をキャッシュする。
    // Cache frequently used DOM elements.
    const els = {
        list: document.getElementById('todo-list'),
        template: document.getElementById('todo-item-template'),
        createForm: document.getElementById('create-form'),
        createTitle: document.getElementById('create-title'),
        createDescription: document.getElementById('create-description'),
        loading: document.getElementById('loading'),
        emptyState: document.getElementById('empty-state'),
        globalError: document.getElementById('global-error'),
    };

    /**
     * fetch の薄いラッパー。JSON ヘッダーと CSRF トークンを付与し、
     * ステータスに応じて結果を正規化して返す。
     * Thin fetch wrapper that attaches JSON headers and the CSRF token,
     * and normalizes the result based on the response status.
     *
     * @returns {Promise<{ok: boolean, status: number, data: any}>}
     */
    async function apiRequest(method, url, body) {
        const options = {
            method,
            headers: {
                // JSON を期待することを明示 → バリデーション失敗時にリダイレクトでなく 422 JSON が返る。
                // Signal that we expect JSON → validation failures return 422 JSON, not a redirect.
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest',
            },
        };

        if (body !== undefined) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }

        const response = await fetch(url, options);

        // 204 No Content（削除など）は本文が無いので JSON パースしない。
        // 204 No Content (e.g. delete) has no body, so don't parse JSON.
        let data = null;
        if (response.status !== 204) {
            data = await response.json().catch(() => null);
        }

        return { ok: response.ok, status: response.status, data };
    }

    /**
     * ユーザー入力を安全に DOM へ挿入するため、textContent を使う。
     * ここでは値をそのまま返し、呼び出し側で textContent 経由で設定する（XSS 対策）。
     * We insert user input via textContent (never innerHTML) to prevent XSS.
     */

    // グローバルエラーの表示 / 非表示。
    // Show / hide the global error banner.
    function showGlobalError(message) {
        els.globalError.textContent = message;
        els.globalError.hidden = false;
    }
    function clearGlobalError() {
        els.globalError.textContent = '';
        els.globalError.hidden = true;
    }

    // フォーム内の項目別エラー表示をクリアする。
    // Clear per-field error messages inside a form.
    function clearFieldErrors(form) {
        form.querySelectorAll('[data-error-for]').forEach((el) => {
            el.textContent = '';
        });
    }

    // 422 のバリデーションエラーを各項目の下に表示する。
    // Render 422 validation errors under each field.
    function applyValidationErrors(form, errors) {
        clearFieldErrors(form);
        Object.entries(errors || {}).forEach(([field, messages]) => {
            const target = form.querySelector(`[data-error-for="${field}"]`);
            if (target && messages.length) {
                target.textContent = messages[0];
            }
        });
    }

    /**
     * 1 件の ToDo を表す <li> を生成する。
     * Build the <li> element for a single ToDo.
     */
    function createTodoElement(todo) {
        const fragment = els.template.content.cloneNode(true);
        const li = fragment.querySelector('.todo');
        li.dataset.id = todo.id;

        // textContent を使うことでユーザー入力を安全に描画する。
        // Using textContent renders user input safely (auto-escaped).
        li.querySelector('.js-title').textContent = todo.title;

        const descEl = li.querySelector('.js-description');
        if (todo.description) {
            descEl.textContent = todo.description;
        } else {
            descEl.remove();
        }

        const checkbox = li.querySelector('.js-toggle');
        checkbox.checked = todo.is_completed;
        li.classList.toggle('todo--completed', todo.is_completed);

        wireItemEvents(li, todo);
        return li;
    }

    // 1 件の <li> にイベントハンドラを結び付ける。
    // Attach event handlers for a single <li>.
    function wireItemEvents(li, todo) {
        const id = todo.id;

        // 完了トグル / toggle completion
        li.querySelector('.js-toggle').addEventListener('change', () => toggleTodo(id, li));

        // 削除 / delete
        li.querySelector('.js-delete').addEventListener('click', () => deleteTodo(id, li));

        // 編集フォームの開閉 / open & close the edit form
        const editForm = li.querySelector('.js-edit-form');
        li.querySelector('.js-edit').addEventListener('click', () => openEditForm(li));
        li.querySelector('.js-cancel').addEventListener('click', () => closeEditForm(li));

        // 保存 / save
        editForm.addEventListener('submit', (e) => {
            e.preventDefault();
            updateTodo(id, li);
        });
    }

    // 編集フォームを開き、現在の値を流し込む。
    // Open the edit form, pre-filling it with the current values.
    function openEditForm(li) {
        const titleText = li.querySelector('.js-title').textContent;
        const descEl = li.querySelector('.js-description');
        li.querySelector('.js-edit-title').value = titleText;
        li.querySelector('.js-edit-description').value = descEl ? descEl.textContent : '';
        li.querySelector('.js-edit-form').hidden = false;
    }

    function closeEditForm(li) {
        const form = li.querySelector('.js-edit-form');
        clearFieldErrors(form);
        form.hidden = true;
    }

    /**
     * 一覧の初期読み込み。
     * Initial load of the list.
     */
    async function loadTodos() {
        clearGlobalError();
        try {
            const { ok, data } = await apiRequest('GET', API_BASE);
            els.loading.hidden = true;

            if (!ok) {
                showGlobalError('一覧の取得に失敗しました。 / Failed to load tasks.');
                return;
            }

            const todos = data.data || [];
            els.list.replaceChildren();
            todos.forEach((todo) => els.list.appendChild(createTodoElement(todo)));
            els.emptyState.hidden = todos.length > 0;
        } catch (err) {
            els.loading.hidden = true;
            showGlobalError('サーバーに接続できませんでした。 / Could not reach the server.');
        }
    }

    /**
     * 作成。成功したら一覧の先頭に追加する。
     * Create. On success, prepend to the list.
     */
    async function createTodo(event) {
        event.preventDefault();
        clearGlobalError();
        clearFieldErrors(els.createForm);

        const payload = {
            title: els.createTitle.value,
            description: els.createDescription.value,
        };

        try {
            const { ok, status, data } = await apiRequest('POST', API_BASE, payload);

            if (status === 422) {
                applyValidationErrors(els.createForm, data.errors);
                return;
            }
            if (!ok) {
                showGlobalError('作成に失敗しました。 / Failed to create the task.');
                return;
            }

            // 新しいアイテムを先頭に追加し、フォームをリセットする。
            // Prepend the new item and reset the form.
            els.list.prepend(createTodoElement(data.data));
            els.emptyState.hidden = true;
            els.createForm.reset();
            els.createTitle.focus();
        } catch (err) {
            showGlobalError('サーバーに接続できませんでした。 / Could not reach the server.');
        }
    }

    /**
     * 更新（title / description）。
     * Update (title / description).
     */
    async function updateTodo(id, li) {
        clearGlobalError();
        const form = li.querySelector('.js-edit-form');
        const payload = {
            title: form.querySelector('.js-edit-title').value,
            description: form.querySelector('.js-edit-description').value,
        };

        try {
            const { ok, status, data } = await apiRequest('PUT', `${API_BASE}/${id}`, payload);

            if (status === 422) {
                applyValidationErrors(form, data.errors);
                return;
            }
            if (!ok) {
                showGlobalError('更新に失敗しました。 / Failed to update the task.');
                return;
            }

            // 更新後の内容で置き換える。
            // Replace the item with the updated content.
            li.replaceWith(createTodoElement(data.data));
        } catch (err) {
            showGlobalError('サーバーに接続できませんでした。 / Could not reach the server.');
        }
    }

    /**
     * 完了状態のトグル。
     * Toggle completion.
     */
    async function toggleTodo(id, li) {
        clearGlobalError();
        try {
            const { ok, data } = await apiRequest('PATCH', `${API_BASE}/${id}/complete`);

            if (!ok) {
                showGlobalError('状態の更新に失敗しました。 / Failed to update status.');
                // 失敗時はチェックボックスの見た目を元に戻す。
                // Revert the checkbox visual on failure.
                const checkbox = li.querySelector('.js-toggle');
                checkbox.checked = !checkbox.checked;
                return;
            }

            li.replaceWith(createTodoElement(data.data));
        } catch (err) {
            showGlobalError('サーバーに接続できませんでした。 / Could not reach the server.');
            const checkbox = li.querySelector('.js-toggle');
            checkbox.checked = !checkbox.checked;
        }
    }

    /**
     * 削除。成功したら DOM から取り除く。
     * Delete. On success, remove from the DOM.
     */
    async function deleteTodo(id, li) {
        clearGlobalError();

        // 誤操作防止の確認（日英併記）。
        // Confirmation to prevent accidental deletion (bilingual).
        const confirmed = window.confirm('このタスクを削除しますか？ / Delete this task?');
        if (!confirmed) {
            return;
        }

        try {
            const { ok } = await apiRequest('DELETE', `${API_BASE}/${id}`);

            if (!ok) {
                showGlobalError('削除に失敗しました。 / Failed to delete the task.');
                return;
            }

            li.remove();
            els.emptyState.hidden = els.list.children.length > 0;
        } catch (err) {
            showGlobalError('サーバーに接続できませんでした。 / Could not reach the server.');
        }
    }

    // 初期化: 一覧を読み込み、作成フォームを結線する。
    // Initialize: load the list and wire the create form.
    document.addEventListener('DOMContentLoaded', () => {
        els.createForm.addEventListener('submit', createTodo);
        loadTodos();
    });
})();
