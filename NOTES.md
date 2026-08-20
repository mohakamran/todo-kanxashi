# 提出コメント / Submission Notes

日本語のセクションの後に、同等の英語セクションを記載しています。
The Japanese section is followed by an equivalent English section.

---

# 日本語

## 1. 設計・実装時に配慮した点

### 安全性 (Safety)
- **バリデーション**: 入力検証は `StoreTodoRequest` / `UpdateTodoRequest`（FormRequest）に集約。`title` は必須・最大255文字、`description` は任意。失敗時は **422 + JSON エラーボディ** を返し、フロントで項目別に表示。
- **CSRF 対策**: API を `routes/web.php` に置き、web ミドルウェアグループ（セッション + CSRF 検証）を適用。フロントは `<meta name="csrf-token">` の値を `X-CSRF-TOKEN` ヘッダーで送信。
- **マスアサインメント対策**: `Todo` モデルに `$fillable` を明示し、`create()`/`update()` には `validated()` の値のみを渡す。
- **XSS 対策**: フロントは `innerHTML` を使わず `textContent` で描画。Blade も既定でエスケープ。
- **SQL インジェクション対策**: DB アクセスは Eloquent のみ（パラメータバインド）。生 SQL は不使用。
- **セキュリティヘッダー**: `SecurityHeaders` ミドルウェアで `X-Frame-Options` / `X-Content-Type-Options` / `Referrer-Policy` / `Permissions-Policy` を全レスポンスに付与。
- **入力サイズ制限**: `description` を最大 5000 文字に制限し、過大なペイロードを防ぐ。
- **既知のフレームワーク勧告**: `composer audit` は Laravel の勧告を 3 件検出する（署名付き URL の混同、`email` バリデーションルールの CRLF インジェクション）。いずれも本アプリの利用機能外（署名付き URL・`email` ルール・メール送信のいずれも未使用）。修正は Laravel 12.60 以降のみで、本課題は Laravel 11.x 固定のため、`composer.json` の `config.audit.ignore` で理由を明記して個別に除外している（新規勧告は引き続きブロックされる）。
- **本番向け設定**: Nginx で `.env` などの隠しファイルへのアクセスを拒否。デプロイ時は `APP_DEBUG=false` を想定。

### 保守性 (Maintainability)
- **薄いコントローラ**: `TodoController` は検証を FormRequest に、出力整形を `TodoResource` に委譲。各メソッドは数行。
- **API リソース**: `TodoResource` で JSON の形状を一元管理し、返却構造を一定に保つ。
- **RESTful 設計 + ルートモデルバインディング**: `{todo}` を自動解決し、未存在は 404。適切なステータスコード（200/201/204/404/422）。
- **単一の情報源**: スキーマはマイグレーションのみを正とする。
- **テスト**: 各エンドポイントの契約を検証するフィーチャーテストを用意（インメモリ SQLite）。

### なぜバニラ Ajax か (Why vanilla Ajax)
課題ではフレームワークは任意。バニラ `fetch` はビルドステップが不要で、第三者環境でそのまま動作し、リスクが少ない。求められている「リロードなしの Ajax CRUD」というスキルを直接的に示せるため採用。この方針に合わせ、Laravel 標準の Vite/Node ツールチェイン（`package.json`・`vite.config.js` 等）は不要なため削除し、フロントは `public/js`・`public/css` の素の資産のみで構成している（npm ビルド不要）。

## 2. 判断に迷った点・トレードオフ
- **完了トグル専用エンドポイント vs 汎用 update**: 「完了/未完了の切替」という意図を URL に明示できる点を重視し、専用の `PATCH /complete` を採用（課題の指定にも合致）。汎用 `update` に寄せる案もあったが意図が不明瞭になる。
- **ハード削除 vs ソフト削除**: v1 はシンプルさ優先でハード削除。復元要件が出た場合はソフトデリート（`deleted_at`）を追加する想定（改善案参照）。
- **単一テーブル vs 正規化**: 単一ユーザー・単純要件のため `todos` 一枚に集約。カテゴリ/タグ等が必要になれば正規化。
- **API を `web.php` に置く vs `api.php`(Sanctum)**: Cookie セッション + CSRF で完結させたく、SPA と同一オリジンのため `web.php` を選択。トークン認証が必要になれば `install:api` + Sanctum に移行。
- **バニラ JS vs React**: 前述の通りバニラを選択。UI が複雑化すれば SPA フレームワーク導入を検討。
- **一覧の並び順**: `created_at` は同一秒で順序が不定になり得るため、主キー（`id`）降順で確定的にした。

## 3. 外部資料・生成AIの利用
- Laravel 11 の標準スキャフォールドは `composer create-project` で生成。ルーティング/コントローラ/FormRequest/Resource/Eloquent は Laravel 公式ドキュメントの標準的な用法に沿って実装。
- 本提出物の作成にあたり生成 AI（コーディング支援）を利用した。ただし全コードの構造・設計判断・各行の意図を理解しており、面接で説明可能。定型のスキャフォールドと日英コメントの整形に補助を使い、設計判断は自分で行った。

## 4. 開発に要したおおよその時間
約 3〜4 時間（設計・実装・テスト・Docker 化・日英ドキュメント作成を含む）。

## 5. 改善案（複数）
1. **認証・マルチユーザー化**（Laravel Breeze/Sanctum、`todos.user_id`）。
2. **カテゴリ/タグ**による分類。
3. **期限・リマインダー**（`due_at` + 通知）。
4. **ソフトデリート + 復元**（`SoftDeletes`）。
5. **ページネーション・検索・フィルタ**（完了/未完了、キーワード）。
6. **自動テストの拡充**（バリデーション境界、CI 連携）。
7. **React などの SPA フロントエンド**化。
8. **i18n の本格化**（Laravel のローカライズファイルへ日英を外出し）。
9. **楽観的 UI 更新**（リクエスト前に DOM 更新し、失敗時にロールバック）。
10. **並び替え・ドラッグ&ドロップ**による優先度管理。

---

# English

## 1. Design & implementation considerations

### Safety
- **Validation** is centralized in `StoreTodoRequest` / `UpdateTodoRequest` (FormRequests): `title` required/max 255, `description` optional. Failures return **422 + a JSON error body** shown per-field in the UI.
- **CSRF**: the API lives in `routes/web.php` so the `web` middleware group (session + CSRF) applies; the frontend sends `<meta name="csrf-token">` via the `X-CSRF-TOKEN` header.
- **Mass assignment**: `Todo` declares an explicit `$fillable`, and only `validated()` input reaches `create()`/`update()`.
- **XSS**: the frontend renders with `textContent` (never `innerHTML`); Blade escapes by default.
- **SQL injection**: all DB access is Eloquent (parameter-bound); no raw SQL.
- **Security headers**: a `SecurityHeaders` middleware adds `X-Frame-Options` / `X-Content-Type-Options` / `Referrer-Policy` / `Permissions-Policy` to every response.
- **Input size limit**: `description` is capped at 5000 characters to guard against oversized payloads.
- **Known framework advisories**: `composer audit` reports 3 Laravel advisories (signed-URL path confusion; CRLF injection in the default `email` validation rule). None are reachable here — the app uses no signed URLs, no `email` rule, and sends no mail. The fixes ship only in Laravel 12.60+, and the stack is pinned to Laravel 11.x, so each is individually excluded with a written reason via `config.audit.ignore` in `composer.json` (any new advisory still blocks).
- **Production hygiene**: Nginx denies access to hidden files such as `.env`; `APP_DEBUG=false` is expected in deployment.

### Maintainability
- **Thin controller**: `TodoController` delegates validation to FormRequests and output shaping to `TodoResource`; each method is a few lines.
- **API Resource**: `TodoResource` centralizes the JSON shape for a consistent contract.
- **RESTful design + route-model binding**: `{todo}` auto-resolves, missing ids 404, with proper status codes (200/201/204/404/422).
- **Single source of truth**: the schema is defined only by the migration.
- **Tests**: feature tests verify each endpoint's contract on in-memory SQLite.

### Why vanilla Ajax
A framework is optional per the spec. Vanilla `fetch` needs no build step, runs as-is in any third-party environment, and carries less risk, while directly demonstrating the requested "Ajax CRUD without reload" skill. In line with this, Laravel's default Vite/Node toolchain (`package.json`, `vite.config.js`, etc.) was removed as unnecessary; the frontend is served entirely from plain assets in `public/js` and `public/css` (no npm build).

## 2. Points of uncertainty & trade-offs
- **Dedicated complete endpoint vs general update**: chose a dedicated `PATCH /complete` so the intent (toggle done/undone) is explicit in the URL (also matches the spec). Folding it into `update` would blur intent.
- **Hard vs soft delete**: v1 uses hard delete for simplicity; soft delete (`deleted_at`) would be added if restore is required (see improvements).
- **Single table vs normalization**: a single `todos` table fits the single-user, simple requirements; normalize once categories/tags appear.
- **API in `web.php` vs `api.php` (Sanctum)**: kept it cookie-session + CSRF on the same origin as the SPA, so `web.php` was the right fit; move to `install:api` + Sanctum if token auth is needed.
- **Vanilla JS vs React**: chose vanilla as above; revisit a SPA framework if the UI grows complex.
- **List ordering**: ordered by primary key (`id`) descending because `created_at` can tie within the same second.

## 3. External resources & AI usage
- The Laravel 11 baseline was generated with `composer create-project`. Routing/controller/FormRequest/Resource/Eloquent follow standard usage from the official Laravel docs.
- Generative AI (coding assistance) was used to produce this submission. I understand the structure, the design decisions, and the intent of every line, and can explain them in an interview. Assistance helped with boilerplate scaffolding and formatting the bilingual comments; the design decisions are my own.

## 4. Approximate development time
Roughly 3–4 hours, including design, implementation, tests, Dockerization, and the bilingual documentation.

## 5. Improvement ideas (multiple)
1. **Authentication & multi-user** (Laravel Breeze/Sanctum, `todos.user_id`).
2. **Categories / tags** for classification.
3. **Due dates & reminders** (`due_at` + notifications).
4. **Soft delete & restore** (`SoftDeletes`).
5. **Pagination, search & filter** (completed/active, keyword).
6. **Expanded automated tests** (validation edges, CI integration).
7. **A SPA frontend** such as React.
8. **Proper i18n** (move JP/EN into Laravel localization files).
9. **Optimistic UI updates** (update the DOM before the request, roll back on failure).
10. **Reordering / drag-and-drop** for priority management.
