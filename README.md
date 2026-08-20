# ToDo App / ToDo リストアプリ

シングルユーザー向けの ToDo リスト SPA。ページ遷移なしで CRUD を行う。
A single-user ToDo list SPA that performs all CRUD without any page reload.

---

# 日本語

## 概要
ブラウザでアプリの URL を開くと 1 枚の ToDo ページが表示されます。一覧の取得・作成・編集・削除・完了トグルはすべて **Ajax（fetch API）** で行い、**ページのリロードや遷移は発生しません**。バックエンドは **Laravel の JSON API**、データは **MySQL** に保存します（ログイン機能なし・単一ユーザー前提）。

## アーキテクチャ概要
```
[ブラウザ]  fetch (JSON, X-CSRF-TOKEN)   [Laravel]            [MySQL]
 app.js  ───────────────────────────▶  routes/web.php
   ▲                                     └▶ TodoController
   │        JSON (TodoResource)             ├▶ FormRequest（検証）
   └─────────────────────────────────────── ├▶ Eloquent (Todo モデル) ──▶ todos テーブル
                                             └▶ TodoResource（出力整形）
```
リクエストの流れ:
1. `GET /` が単一の Blade ビュー（SPA の器）を返す。
2. `app.js` が `DOMContentLoaded` で `GET /api/todos` を呼び、一覧を描画する。
3. 作成/編集/削除/完了トグルは対応する API を fetch で呼び、返ってきた JSON で DOM を部分更新する。
4. 更新系リクエストには `<meta name="csrf-token">` の値を `X-CSRF-TOKEN` ヘッダーで送る。

## 技術スタック / バージョン
- PHP 8.2
- Laravel 11.x（framework 11.55）
- MySQL 8.0
- フロントエンド: Blade + 素の JavaScript（fetch API、ビルドステップなし）
- Docker / docker-compose

## 機能
- ページ読み込み時に保存済み ToDo を一覧表示（Ajax 取得）
- ToDo の作成（タイトル必須・説明任意）
- ToDo の編集（インライン編集フォーム）
- ToDo の削除（確認ダイアログ付き）
- 完了状態のトグル
- すべてページ遷移・リロードなし
- バリデーションエラー（422）とネットワークエラーの画面表示
- UI ラベルは日英併記

## セットアップ手順

### A. Docker（推奨）
前提: Docker Desktop（または Docker Engine + Compose v2）。

```bash
# 1. リポジトリを取得
git clone <repository-url>
cd Todo-App

# 2. 起動（ビルド → コンテナ起動）
docker compose up --build -d

# 3. ブラウザで開く
#    http://localhost:8000
```
コンテナ起動時に自動で以下が実行されます（`docker/entrypoint.sh`）:
`.env` 生成 → `php artisan key:generate` → DB 起動待ち → `php artisan migrate --seed` → 設定/ルートのキャッシュ → nginx + php-fpm 起動。

停止:
```bash
docker compose down          # コンテナ停止・削除
docker compose down -v       # DB データ（ボリューム）も削除
```

### B. ローカル手動起動（フォールバック）
前提: PHP 8.2 / Composer / ローカルの MySQL 8。

```bash
# 1. 依存関係のインストール
composer install

# 2. 環境設定
cp .env.example .env
php artisan key:generate

# 3. .env の DB 設定をローカル環境に合わせる
#    DB_HOST=127.0.0.1 / DB_DATABASE=todo_app / DB_USERNAME / DB_PASSWORD
#    事前に MySQL 側で todo_app データベースを作成しておく

# 4. マイグレーション + シード
php artisan migrate --seed

# 5. 開発サーバー起動
php artisan serve
#    http://localhost:8000
```

### テスト実行
```bash
php artisan test        # インメモリ SQLite でフィーチャーテストを実行
```

## API エンドポイント
| Method | URI                          | Action   | 用途                         |
|--------|------------------------------|----------|------------------------------|
| GET    | `/`                          | page     | 単一の Blade ビューを返す    |
| GET    | `/api/todos`                 | index    | 全 ToDo を JSON で返す       |
| POST   | `/api/todos`                 | store    | ToDo を作成（201）           |
| PUT    | `/api/todos/{todo}`          | update   | タイトル/説明を更新（200）   |
| PATCH  | `/api/todos/{todo}/complete` | complete | 完了状態をトグル（200）      |
| DELETE | `/api/todos/{todo}`          | destroy  | ToDo を削除（204）           |

入力エラーは **422** と JSON のエラーボディ、存在しない ID は **404** を返します。

## データベーススキーマ（`todos` テーブル）
| カラム        | 型                | 制約                              |
|---------------|-------------------|-----------------------------------|
| id            | BIGINT UNSIGNED   | 主キー・オートインクリメント      |
| title         | VARCHAR(255)      | NOT NULL                          |
| description   | TEXT              | NULL 許可                         |
| is_completed  | BOOLEAN           | NOT NULL・デフォルト false        |
| created_at    | TIMESTAMP         | Laravel 管理                      |
| updated_at    | TIMESTAMP         | Laravel 管理                      |

## プロジェクト構成（主要ファイル）
```
app/Models/Todo.php                         Eloquent モデル（$fillable）
app/Http/Controllers/TodoController.php     リソースコントローラ（薄いメソッド）
app/Http/Requests/StoreTodoRequest.php      作成用バリデーション
app/Http/Requests/UpdateTodoRequest.php     更新用バリデーション
app/Http/Resources/TodoResource.php         JSON 出力の整形
database/migrations/..._create_todos_table.php  スキーマ定義
database/seeders/TodoSeeder.php             サンプルデータ
routes/web.php                              ルート定義（web ミドルウェア + CSRF）
resources/views/todos.blade.php             単一の Blade ビュー
public/js/app.js                            fetch による CRUD（リロードなし）
public/css/app.css                          最小限のスタイル
tests/Feature/TodoApiTest.php               API のフィーチャーテスト
Dockerfile / docker-compose.yml             Docker 構成
docker/                                     nginx / supervisor / entrypoint
```

## 補足（レビュアー向け）
- API ルートはあえて `routes/web.php` に定義しています。web ミドルウェアグループ（セッション + CSRF）を適用し、`X-CSRF-TOKEN` によるトークン検証を成立させるためです（Sanctum は不要）。
- セッション/キャッシュ/キューはファイル・同期ドライバにしているため、DB は `todos` の 1 テーブルのみです。
- 詳細な設計判断は [`NOTES.md`](NOTES.md) を参照してください。

---

# English

## Overview
Opening the app URL shows a single ToDo page. Listing, creating, editing, deleting and toggling completion all happen via **Ajax (the fetch API)** with **no page reload or navigation**. The backend is a **Laravel JSON API** and data is stored in **MySQL** (no login; single-user assumption).

## Architecture
```
[Browser]   fetch (JSON, X-CSRF-TOKEN)    [Laravel]            [MySQL]
 app.js  ───────────────────────────────▶ routes/web.php
   ▲                                        └▶ TodoController
   │        JSON (TodoResource)                ├▶ FormRequest (validation)
   └────────────────────────────────────────  ├▶ Eloquent (Todo model) ──▶ todos table
                                               └▶ TodoResource (output shaping)
```
Request flow:
1. `GET /` returns the single Blade view (the SPA shell).
2. `app.js` calls `GET /api/todos` on `DOMContentLoaded` and renders the list.
3. Create/edit/delete/toggle call the matching API via fetch and patch the DOM in place with the returned JSON.
4. Mutating requests send the `<meta name="csrf-token">` value in the `X-CSRF-TOKEN` header.

## Tech stack / versions
- PHP 8.2
- Laravel 11.x (framework 11.55)
- MySQL 8.0
- Frontend: Blade + vanilla JavaScript (fetch API, no build step)
- Docker / docker-compose

## Features
- Displays saved ToDos on load (fetched via Ajax)
- Create a ToDo (title required, description optional)
- Edit a ToDo (inline edit form)
- Delete a ToDo (with a confirmation dialog)
- Toggle completion state
- Everything without page reload / navigation
- Validation (422) and network errors shown in the UI
- Bilingual (Japanese + English) UI labels

## Setup

### A. Docker (recommended)
Requires Docker Desktop (or Docker Engine + Compose v2).

```bash
# 1. Get the repository
git clone <repository-url>
cd Todo-App

# 2. Build and start
docker compose up --build -d

# 3. Open in a browser
#    http://localhost:8000
```
On start the container automatically runs (`docker/entrypoint.sh`):
create `.env` → `php artisan key:generate` → wait for DB → `php artisan migrate --seed` → cache config/routes → start nginx + php-fpm.

Stop:
```bash
docker compose down          # stop & remove containers
docker compose down -v       # also remove the DB volume
```

### B. Manual / local (fallback)
Requires PHP 8.2 / Composer / a local MySQL 8.

```bash
# 1. Install dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Point .env DB_* at your local MySQL
#    DB_HOST=127.0.0.1 / DB_DATABASE=todo_app / DB_USERNAME / DB_PASSWORD
#    Create the todo_app database in MySQL beforehand

# 4. Migrate + seed
php artisan migrate --seed

# 5. Start the dev server
php artisan serve
#    http://localhost:8000
```

### Running tests
```bash
php artisan test        # feature tests on in-memory SQLite
```

## API endpoints
| Method | URI                          | Action   | Purpose                       |
|--------|------------------------------|----------|-------------------------------|
| GET    | `/`                          | page     | Serve the single Blade view   |
| GET    | `/api/todos`                 | index    | Return all todos as JSON      |
| POST   | `/api/todos`                 | store    | Create a todo (201)           |
| PUT    | `/api/todos/{todo}`          | update   | Update title/description (200)|
| PATCH  | `/api/todos/{todo}/complete` | complete | Toggle completion (200)       |
| DELETE | `/api/todos/{todo}`          | destroy  | Delete a todo (204)           |

Validation errors return **422** with a JSON error body; a missing id returns **404**.

## Database schema (`todos` table)
| Column        | Type              | Constraints                       |
|---------------|-------------------|-----------------------------------|
| id            | BIGINT UNSIGNED   | Primary key, auto-increment       |
| title         | VARCHAR(255)      | NOT NULL                          |
| description   | TEXT              | NULLABLE                          |
| is_completed  | BOOLEAN           | NOT NULL, default false           |
| created_at    | TIMESTAMP         | Laravel managed                   |
| updated_at    | TIMESTAMP         | Laravel managed                   |

## Project structure (key files)
```
app/Models/Todo.php                         Eloquent model ($fillable)
app/Http/Controllers/TodoController.php     Resource controller (thin methods)
app/Http/Requests/StoreTodoRequest.php      Create validation
app/Http/Requests/UpdateTodoRequest.php     Update validation
app/Http/Resources/TodoResource.php         JSON output shaping
database/migrations/..._create_todos_table.php  Schema definition
database/seeders/TodoSeeder.php             Sample data
routes/web.php                              Routes (web middleware + CSRF)
resources/views/todos.blade.php             The single Blade view
public/js/app.js                            fetch-based CRUD (no reload)
public/css/app.css                          Minimal styling
tests/Feature/TodoApiTest.php               API feature tests
Dockerfile / docker-compose.yml             Docker setup
docker/                                     nginx / supervisor / entrypoint
```

## Notes (for reviewers)
- API routes live in `routes/web.php` on purpose: this applies the `web` middleware group (session + CSRF) so `X-CSRF-TOKEN` verification works (no Sanctum needed).
- Session/cache/queue use file & sync drivers, so the DB has a single table (`todos`).
- See [`NOTES.md`](NOTES.md) for the detailed design rationale.
