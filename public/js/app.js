/**
 * ToDo SPA のフロントエンドロジック。
 * Frontend logic for the ToDo SPA.
 *
 * すべての CRUD を fetch (Ajax) で行い、ページ遷移・リロードなしに DOM を更新する。
 * All CRUD is done via fetch (Ajax); the DOM is updated in place with no navigation/reload.
 *
 * 主な UX:
 *  - 追加/編集は上部の単一フォームで兼用（編集時はボタンが「保存」に変わり「破棄」を表示）。
 *    A single top form handles both add and edit (in edit mode the button becomes "Save" and a "Discard" button appears).
 *  - 削除は専用の確認モーダルで確認する。
 *    Deletion is confirmed via a dedicated modal dialog.
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
        form: document.getElementById('todo-form'),
        formCard: document.getElementById('form-card'),
        formHeading: document.getElementById('form-heading'),
        id: document.getElementById('todo-id'),
        title: document.getElementById('form-title'),
        description: document.getElementById('form-description'),
        submitBtn: document.getElementById('submit-btn'),
        discardBtn: document.getElementById('discard-btn'),
        list: document.getElementById('todo-list'),
        template: document.getElementById('todo-item-template'),
        loading: document.getElementById('loading'),
        emptyState: document.getElementById('empty-state'),
        globalError: document.getElementById('global-error'),
        counter: document.getElementById('counter'),
        // モーダル / modal
        modal: document.getElementById('delete-modal'),
        modalTarget: document.getElementById('modal-target'),
        modalCancel: document.getElementById('modal-cancel'),
        modalConfirm: document.getElementById('modal-confirm'),
    };

    // クライアント側の状態。id → todo のマップで最新データを保持する。
    // Client-side state. A Map of id → todo keeps the latest data.
    const state = {
        todos: new Map(),
        editingId: null, // 編集中の ID（null なら新規追加モード）/ id being edited (null = create mode)
        deletingId: null, // 削除確認中の ID / id pending delete confirmation
    };

    // -------------------------------------------------------------------------
    // 通信ヘルパー / networking helper
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // エラー表示 / error display
    // -------------------------------------------------------------------------

    function showGlobalError(message) {
        els.globalError.textContent = message;
        els.globalError.hidden = false;
    }
    function clearGlobalError() {
        els.globalError.textContent = '';
        els.globalError.hidden = true;
    }
    function clearFieldErrors() {
        els.form.querySelectorAll('[data-error-for]').forEach((el) => {
            el.textContent = '';
        });
    }
    // 422 のバリデーションエラーを各項目の下に表示する。
    // Render 422 validation errors under each field.
    function applyValidationErrors(errors) {
        clearFieldErrors();
        Object.entries(errors || {}).forEach(([field, messages]) => {
            const target = els.form.querySelector(`[data-error-for="${field}"]`);
            if (target && messages.length) {
                target.textContent = messages[0];
            }
        });
    }

    // -------------------------------------------------------------------------
    // 描画 / rendering
    // -------------------------------------------------------------------------

    /**
     * 1 件の ToDo を表す <li> を生成する。
     * Build the <li> element for a single ToDo.
     * ユーザー入力は textContent で挿入するため XSS 安全。
     * User input is inserted via textContent, so it is XSS-safe.
     */
    function createTodoElement(todo) {
        const fragment = els.template.content.cloneNode(true);
        const li = fragment.querySelector('.todo');
        li.dataset.id = todo.id;

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

        // 個別のイベント結線は行わない。クリック/変更は <ul> 側の委譲で処理する。
        // No per-item listeners here: clicks/changes are handled by delegation on the <ul>.
        return li;
    }

    // 指定 ID の <li> を取得する。
    // Look up the <li> for a given id.
    function findNode(id) {
        return els.list.querySelector(`.todo[data-id="${id}"]`);
    }

    // 一覧全体を描画する（初期ロード用）。
    // Render the entire list (used for the initial load).
    function renderList() {
        const todos = [...state.todos.values()].sort((a, b) => b.id - a.id);
        // DocumentFragment にまとめてから一度だけ挿入し、レイアウトの再計算を最小化する。
        // Build into a DocumentFragment and insert once to minimize layout thrashing.
        const frag = document.createDocumentFragment();
        todos.forEach((todo) => frag.appendChild(createTodoElement(todo)));
        els.list.replaceChildren(frag);
        syncListChrome();
    }

    /**
     * 1 件だけを差分更新する（全再描画を避ける）。
     * Update a single item in place, avoiding a full re-render.
     * 既存ノードがあれば置換し、無ければ（新規作成時）先頭に挿入する。
     * Replaces the existing node, or prepends it when the item is new.
     */
    function upsertTodoNode(todo) {
        const existing = findNode(todo.id);
        const node = createTodoElement(todo);

        if (existing) {
            existing.replaceWith(node);
        } else {
            // 一覧は ID 降順のため、新規作成は常に先頭になる。
            // The list is sorted by id descending, so a new item always goes first.
            els.list.prepend(node);
        }
        syncListChrome();
        return node;
    }

    // 1 件を DOM と状態から取り除く。
    // Remove a single item from the DOM and from state.
    function removeTodoNode(id) {
        findNode(id)?.remove();
        syncListChrome();
    }

    // 空状態表示とカウンターを現在の状態に同期する。
    // Sync the empty-state message and the counter with the current state.
    function syncListChrome() {
        const todos = [...state.todos.values()];
        els.emptyState.hidden = todos.length > 0;

        if (todos.length === 0) {
            els.counter.textContent = '';
            return;
        }
        const active = todos.filter((t) => ! t.is_completed).length;
        els.counter.textContent = `未完了 ${active} / ${todos.length} 件  ·  ${active} of ${todos.length} active`;
    }

    // -------------------------------------------------------------------------
    // フォームのモード切替（追加 ⇄ 編集）/ form mode switching (add ⇄ edit)
    // -------------------------------------------------------------------------

    // ボタン/見出しの日英ラベルを差し替えるヘルパー。
    // Helper to swap the bilingual (JP/EN) labels on a button/heading.
    function setBilingual(el, jp, en) {
        el.querySelector('.jp').textContent = jp;
        el.querySelector('.en').textContent = en;
    }

    // 指定 ID の ToDo を上部フォームに読み込み、編集モードに切り替える。
    // Load the given ToDo into the top form and switch to edit mode.
    function enterEditMode(id) {
        const todo = state.todos.get(id);
        if (!todo) return;

        state.editingId = id;
        els.id.value = id;
        els.title.value = todo.title;
        els.description.value = todo.description || '';

        setBilingual(els.formHeading, 'タスクを編集', 'Edit Task');
        setBilingual(els.submitBtn, '保存', 'Save');
        els.discardBtn.hidden = false;
        els.formCard.classList.add('card--editing');
        clearFieldErrors();

        // 該当アイテムを視覚的に強調し、フォームへスクロールする。
        // Highlight the item being edited and scroll to the form.
        highlightEditing(id);
        els.formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        els.title.focus();
    }

    // 編集モードを解除し、通常の追加フォームに戻す。
    // Leave edit mode and reset back to the normal add form.
    function resetForm() {
        state.editingId = null;
        els.form.reset();
        els.id.value = '';
        setBilingual(els.formHeading, '新しいタスク', 'New Task');
        setBilingual(els.submitBtn, '追加', 'Add');
        els.discardBtn.hidden = true;
        els.formCard.classList.remove('card--editing');
        clearFieldErrors();
        highlightEditing(null);
    }

    // 編集中アイテムに強調クラスを付与する。
    // Apply a highlight class to the item currently being edited.
    function highlightEditing(id) {
        els.list.querySelectorAll('.todo--editing').forEach((li) => li.classList.remove('todo--editing'));
        if (id !== null) {
            const li = els.list.querySelector(`.todo[data-id="${id}"]`);
            if (li) li.classList.add('todo--editing');
        }
    }

    // -------------------------------------------------------------------------
    // CRUD 操作 / CRUD operations
    // -------------------------------------------------------------------------

    // 一覧の初期読み込み。
    // Initial load of the list.
    async function loadTodos() {
        clearGlobalError();
        try {
            const { ok, data } = await apiRequest('GET', API_BASE);
            els.loading.hidden = true;

            if (!ok) {
                showGlobalError('一覧の取得に失敗しました。 / Failed to load tasks.');
                return;
            }

            state.todos.clear();
            (data.data || []).forEach((todo) => state.todos.set(todo.id, todo));
            renderList();
        } catch (err) {
            els.loading.hidden = true;
            showGlobalError('サーバーに接続できませんでした。 / Could not reach the server.');
        }
    }

    // フォーム送信: 編集モードなら更新、そうでなければ作成。
    // Form submit: update in edit mode, otherwise create.
    async function handleSubmit(event) {
        event.preventDefault();
        clearGlobalError();
        clearFieldErrors();

        const payload = {
            title: els.title.value,
            description: els.description.value,
        };

        const editing = state.editingId !== null;
        const method = editing ? 'PUT' : 'POST';
        const url = editing ? `${API_BASE}/${state.editingId}` : API_BASE;

        // 二重送信防止 / prevent double submit
        els.submitBtn.disabled = true;

        try {
            const { ok, status, data } = await apiRequest(method, url, payload);

            if (status === 422) {
                applyValidationErrors(data.errors);
                return;
            }
            if (!ok) {
                showGlobalError(editing
                    ? '更新に失敗しました。 / Failed to update the task.'
                    : '作成に失敗しました。 / Failed to create the task.');
                return;
            }

            // 状態を更新し、該当の 1 件だけを差分描画する。編集後はフォームを追加モードに戻す。
            // Update state and re-render only the affected item. After editing, reset the form to add mode.
            const saved = data.data;
            state.todos.set(saved.id, saved);
            const newlyCreated = ! editing;
            resetForm();
            upsertTodoNode(saved);

            if (newlyCreated) {
                flashItem(saved.id);
                els.title.focus();
            }
        } catch (err) {
            showGlobalError('サーバーに接続できませんでした。 / Could not reach the server.');
        } finally {
            els.submitBtn.disabled = false;
        }
    }

    // 完了状態のトグル。
    // Toggle completion.
    async function toggleTodo(id) {
        clearGlobalError();
        const known = state.todos.get(id);
        if (! known) return;

        try {
            const { ok, data } = await apiRequest('PATCH', `${API_BASE}/${id}/complete`);
            if (! ok) {
                showGlobalError('状態の更新に失敗しました。 / Failed to update status.');
                // 保持している状態から該当行を描き直し、チェックボックスの見た目を戻す。
                // Repaint the row from the state we hold, reverting the checkbox.
                upsertTodoNode(known);
                return;
            }
            state.todos.set(data.data.id, data.data);
            upsertTodoNode(data.data);
        } catch (err) {
            showGlobalError('サーバーに接続できませんでした。 / Could not reach the server.');
            upsertTodoNode(known);
        }
    }

    // -------------------------------------------------------------------------
    // 削除モーダル / delete modal
    // -------------------------------------------------------------------------

    // 削除確認モーダルを開く。
    // Open the delete confirmation modal.
    function openDeleteModal(id) {
        const todo = state.todos.get(id);
        if (!todo) return;
        state.deletingId = id;
        els.modalTarget.textContent = todo.title; // textContent で安全 / safe via textContent
        els.modal.hidden = false;
        // 一度リフローを強制してから開クラスを付与し、確実に開始状態→表示状態のトランジションを再生する。
        // Force a reflow before adding the open class so the enter transition reliably plays
        // (does not depend on requestAnimationFrame, which can be throttled in background tabs).
        void els.modal.offsetWidth;
        els.modal.classList.add('modal--open');
        els.modalCancel.focus();
        document.addEventListener('keydown', onModalKeydown);
    }

    // 削除確認モーダルを閉じる。
    // Close the delete confirmation modal.
    function closeDeleteModal() {
        state.deletingId = null;
        els.modal.classList.remove('modal--open');
        document.removeEventListener('keydown', onModalKeydown);
        // トランジション終了後に hidden にする / hide after the transition ends
        setTimeout(() => { els.modal.hidden = true; }, 150);
    }

    // Esc で閉じ、Enter で確定する。
    // Esc closes, Enter confirms.
    function onModalKeydown(e) {
        if (e.key === 'Escape') closeDeleteModal();
        if (e.key === 'Enter') confirmDelete();
    }

    // モーダルの「削除する」を確定したときの処理。
    // Handle confirming deletion in the modal.
    async function confirmDelete() {
        const id = state.deletingId;
        if (id === null) return;
        clearGlobalError();
        els.modalConfirm.disabled = true;

        try {
            const { ok } = await apiRequest('DELETE', `${API_BASE}/${id}`);
            if (!ok) {
                showGlobalError('削除に失敗しました。 / Failed to delete the task.');
                return;
            }
            state.todos.delete(id);
            // 削除対象を編集中だった場合はフォームを戻す。
            // If we were editing the deleted item, reset the form.
            if (state.editingId === id) resetForm();
            removeTodoNode(id);
            closeDeleteModal();
        } catch (err) {
            showGlobalError('サーバーに接続できませんでした。 / Could not reach the server.');
        } finally {
            els.modalConfirm.disabled = false;
        }
    }

    // -------------------------------------------------------------------------
    // 小さな演出 / small flourishes
    // -------------------------------------------------------------------------

    // 新規追加されたアイテムを一瞬ハイライトする。
    // Briefly highlight a newly created item.
    function flashItem(id) {
        const li = els.list.querySelector(`.todo[data-id="${id}"]`);
        if (!li) return;
        li.classList.add('todo--flash');
        setTimeout(() => li.classList.remove('todo--flash'), 900);
    }

    // -------------------------------------------------------------------------
    // 初期化 / initialization
    // -------------------------------------------------------------------------

    /**
     * 一覧のイベントを <ul> 1 箇所で受け取る（イベント委譲）。
     * Handle all list events with a single listener on the <ul> (event delegation).
     * 行を再描画してもリスナーを貼り直す必要がなく、リスナー数も常に一定に保てる。
     * Rows can be re-rendered without re-binding, and the listener count stays constant.
     */
    function wireListDelegation() {
        // 編集・削除ボタンのクリック / clicks on the edit & delete buttons
        els.list.addEventListener('click', (event) => {
            const button = event.target.closest('.js-edit, .js-delete');
            if (! button) return;

            const id = Number(button.closest('.todo').dataset.id);
            if (button.classList.contains('js-edit')) {
                enterEditMode(id);
            } else {
                openDeleteModal(id);
            }
        });

        // 完了トグルの変更 / changes on the completion toggle
        els.list.addEventListener('change', (event) => {
            const checkbox = event.target.closest('.js-toggle');
            if (! checkbox) return;

            toggleTodo(Number(checkbox.closest('.todo').dataset.id));
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        els.form.addEventListener('submit', handleSubmit);
        els.discardBtn.addEventListener('click', resetForm);
        wireListDelegation();

        // モーダルの各操作 / modal interactions
        els.modalCancel.addEventListener('click', closeDeleteModal);
        els.modalConfirm.addEventListener('click', confirmDelete);
        els.modal.querySelector('[data-close="true"]').addEventListener('click', closeDeleteModal);

        loadTodos();
    });
})();
