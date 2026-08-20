# ToDo App / ToDo リストアプリ

<p>
シングルユーザー向けの ToDo リスト SPA。ページ遷移・リロードなしで CRUD を行います。<br>
A single-user ToDo list SPA that performs all CRUD without any page reload or navigation.
</p>

**Stack:** PHP 8.2 ・ Laravel 11 ・ MySQL 8 ・ Blade + Vanilla JS (fetch) ・ Docker

---

## 目次 / Table of Contents
- [日本語](#日本語)
  - [概要](#概要)
  - [アーキテクチャ](#アーキテクチャ)
  - [機能](#機能)
  - [必要環境](#必要環境)
  - [セットアップ A: Docker（推奨）](#セットアップ-a-docker推奨)
  - [セットアップ B: XAMPP / WAMP（ローカル）](#セットアップ-b-xampp--wampローカル)
  - [セットアップ C: Railway へのデプロイ（本番デモ）](#セットアップ-c-railway-へのデプロイ本番デモ)
  - [テスト実行](#テスト実行)
  - [API エンドポイント](#api-エンドポイント)
  - [データベーススキーマ](#データベーススキーマ)
  - [プロジェクト構成](#プロジェクト構成)
  - [トラブルシューティング](#トラブルシューティング)
- [English](#english)

---

# 日本語

## 概要
ブラウザでアプリの URL を開くと、1 枚の ToDo ページが表示されます。一覧の取得・作成・編集・削除・完了トグルはすべて **Ajax（fetch API）** で行い、**ページのリロードや画面遷移は発生しません**。バックエンドは **Laravel の JSON API**、データは **MySQL** に保存します（ログイン機能なし・単一ユーザー前提）。

## アーキテクチャ
```
[ブラウザ]  fetch (JSON, X-CSRF-TOKEN)    [Laravel]              [MySQL]
 app.js  ────────────────────────────▶  routes/web.php
   ▲                                      └▶ TodoController
   │        JSON (TodoResource)              ├▶ FormRequest（入力検証）
   └──────────────────────────────────────  ├▶ Eloquent (Todo モデル) ──▶ todos テーブル
                                             └▶ TodoResource（出力整形）
```
リクエストの流れ:
1. `GET /` が単一の Blade ビュー（SPA の器）を返す。
2. `app.js` が `DOMContentLoaded` で `GET /api/todos` を呼び、一覧を描画する。
3. 作成 / 編集 / 削除 / 完了トグルは対応する API を fetch で呼び、返ってきた JSON で DOM を部分更新する。
4. 更新系リクエストには `<meta name="csrf-token">` の値を `X-CSRF-TOKEN` ヘッダーで送る。

## 機能
- ページ読み込み時に保存済み ToDo を一覧表示（Ajax 取得）
- ToDo の作成（タイトル必須・説明任意）
- ToDo の編集（上部フォームに読み込み、ボタンが「追加」→「保存」に切替、「破棄」で取消）
- ToDo の削除（プロフェッショナルな確認モーダル。Esc/Enter・フォーカス対応）
- 完了状態のトグル（カスタムチェックボックス）
- すべてページ遷移・リロードなし
- 未完了件数のライブカウンター、控えめなアニメーション
- バリデーションエラー（422）とネットワークエラーの画面表示
- レスポンシブ対応、ライト/ダークテーマ（OS 設定に追従）
- セキュリティヘッダー付与（クリックジャッキング/MIME スニッフィング対策）
- UI ラベル・コードコメント・本 README は日英併記

## 必要環境
| 用途 | 必要なもの |
|------|-----------|
| Docker で動かす | Docker Desktop（または Docker Engine + Compose v2） |
| ローカルで動かす | PHP **8.2 以上** / Composer / MySQL 8（XAMPP・WAMP 等） |

---

## セットアップ A: Docker（推奨）
最も簡単で、環境に依存せず動作します。事前に **Docker Desktop を起動**しておいてください。

```bash
# 1. リポジトリを取得
git clone <repository-url>
cd Todo-App

# 2. ビルドして起動（app: Laravel+Nginx / db: MySQL 8）
docker compose up --build -d

# 3. ブラウザで開く
#    http://localhost:8000
```

コンテナ起動時、`docker/entrypoint.sh` が以下を自動実行します:
`.env` 生成 → `php artisan key:generate` → **MySQL の起動待ち** → `php artisan migrate --seed` → 設定/ルートのキャッシュ → Nginx + PHP-FPM 起動。

停止・後片付け:
```bash
docker compose down        # コンテナ停止・削除（DB データは残る）
docker compose down -v      # DB データ（ボリューム）も削除
```

> MySQL の接続情報は `docker-compose.yml` で定義され、`.env` より優先されます（DB_HOST=db / DB_DATABASE=todo_app / DB_USERNAME=todo / DB_PASSWORD=secret）。個別の設定は不要です。

---

## セットアップ B: XAMPP / WAMP（ローカル）
XAMPP や WAMP に付属する MySQL を使ってローカルで動かす手順です。

### 手順 1. XAMPP / WAMP を起動し MySQL を開始
- **XAMPP**: コントロールパネルで **MySQL**（と必要なら Apache）の「Start」を押す。
- **WAMP**: タスクトレイのアイコンから MySQL サービスを開始する。

> PHP は Laravel が要求する **8.2 以上**が必要です。XAMPP 同梱の PHP が 8.2 以上か確認してください（`php -v`）。古い場合は最新の XAMPP を使うか、別途 PHP 8.2 を用意して PATH を通してください。

### 手順 2. データベースを作成
phpMyAdmin（`http://localhost/phpmyadmin`）を開き、新しいデータベース **`todo_app`**（照合順序 `utf8mb4_unicode_ci`）を作成します。

または SQL で:
```sql
CREATE DATABASE todo_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 手順 3. 依存関係のインストールと環境設定
```bash
cd Todo-App

# PHP の依存関係をインストール
composer install

# .env を作成し、アプリキーを生成
cp .env.example .env      # Windows のコマンドプロンプトの場合: copy .env.example .env
php artisan key:generate
```

### 手順 4. `.env` の DB 設定を編集
`.env` を開き、DB 設定を **お使いの MySQL** に合わせます。XAMPP / WAMP の初期状態では **ユーザー `root`・パスワードなし**です。

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=todo_app
DB_USERNAME=root
DB_PASSWORD=
```
> パスワードを設定している場合は `DB_PASSWORD=あなたのパスワード` としてください。

### 手順 5. マイグレーションとサンプルデータ投入
```bash
php artisan migrate --seed
```
`todos` テーブルが作成され、確認用のサンプルが 3 件投入されます。

### 手順 6. 開発サーバーを起動
```bash
php artisan serve
```
ブラウザで **http://localhost:8000** を開きます。

> Apache（`http://localhost/...`）ではなく、`php artisan serve` の利用を推奨します（設定が最小で確実です）。Apache で配信する場合はドキュメントルートを本プロジェクトの `public/` ディレクトリに向けてください。

---

## セットアップ C: Railway へのデプロイ（本番デモ）
本リポジトリは Railway 上に Docker でそのままデプロイできます。`railway.json` と `Dockerfile` を同梱済みです。

### 手順 1. MySQL サービスを追加
1. [Railway](https://railway.app) で **New Project** → **Deploy from GitHub repo** から本リポジトリを選択。
2. 同じプロジェクト内で **+ New** → **Database** → **Add MySQL** を追加。

### 手順 2. アプリキーを生成
ローカルで以下を実行し、出力された `base64:...` をコピーします。
```bash
php artisan key:generate --show
```

### 手順 3. 環境変数を設定
アプリ側サービスの **Variables** に以下を設定します。

| 変数 | 値 | 説明 |
|------|-----|------|
| `APP_KEY` | `base64:...`（手順 2 の値） | 固定しないとデプロイ毎にセッションが無効化される |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | **本番では必須**（スタックトレース露出の防止） |
| `APP_URL` | `https://<発行されたドメイン>` | |
| `DB_CONNECTION` | `mysql` | |
| `DB_URL` | `${{MySQL.MYSQL_URL}}` | MySQL サービスへの参照。Laravel が `DB_URL` を解釈する |
| `LOG_CHANNEL` | `stderr` | Railway のログ画面に出力を流すため |

> `DB_URL` は Railway の変数参照構文です。MySQL サービス名が `MySQL` 以外の場合は読み替えてください。

### 手順 4. 公開ドメインを発行
アプリ側サービスの **Settings** → **Networking** → **Generate Domain** を実行します。発行された URL を `APP_URL` に設定し直してください。

### 動作の仕組み
- Railway は `$PORT` を動的に割り当てるため、`entrypoint.sh` が nginx の待ち受けポートを起動時に置換します。
- 起動時に自動でマイグレーションとシードが実行されます（シーダーは冪等なので再デプロイしても重複しません）。
- ヘルスチェックは `/up`（Laravel 標準）を使用します。
- TLS は Railway 側で終端されるため、`trustProxies` を有効化して HTTPS を正しく認識させています。

---

## テスト実行
```bash
php artisan test        # インメモリ SQLite でフィーチャーテストを実行（MySQL 不要）
```

## API エンドポイント
| Method | URI                          | Action   | 用途 / 成功時ステータス          |
|--------|------------------------------|----------|----------------------------------|
| GET    | `/`                          | page     | 単一の Blade ビューを返す (200)  |
| GET    | `/api/todos`                 | index    | 全 ToDo を JSON で返す (200)     |
| POST   | `/api/todos`                 | store    | ToDo を作成 (201)                |
| PUT    | `/api/todos/{todo}`          | update   | タイトル/説明を更新 (200)        |
| PATCH  | `/api/todos/{todo}/complete` | complete | 完了状態をトグル (200)           |
| DELETE | `/api/todos/{todo}`          | destroy  | ToDo を削除 (204)                |

入力エラーは **422**（JSON のエラーボディ付き）、存在しない ID は **404** を返します。

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
app/Models/Todo.php                             Eloquent モデル（$fillable）
app/Http/Controllers/TodoController.php         リソースコントローラ（薄いメソッド）
app/Http/Requests/StoreTodoRequest.php          作成用バリデーション
app/Http/Requests/UpdateTodoRequest.php         更新用バリデーション
app/Http/Resources/TodoResource.php             JSON 出力の整形
app/Http/Middleware/SecurityHeaders.php         セキュリティヘッダー付与
database/migrations/..._create_todos_table.php  スキーマ定義（唯一の情報源）
database/seeders/TodoSeeder.php                 サンプルデータ
routes/web.php                                  ルート定義（web ミドルウェア + CSRF）
resources/views/todos.blade.php                 単一の Blade ビュー
public/js/app.js                                fetch による CRUD（リロードなし）
public/css/app.css                              スタイル（レスポンシブ・ダーク対応）
tests/Feature/TodoApiTest.php                   API のフィーチャーテスト
tests/Unit/TodoModelTest.php                    モデルの単体テスト
Dockerfile / docker-compose.yml                 Docker 構成
docker/                                         nginx / supervisor / entrypoint
```

## トラブルシューティング
| 症状 | 対処 |
|------|------|
| `SQLSTATE[HY000] [2002]` 接続拒否 | MySQL が起動しているか、`.env` の `DB_HOST` / `DB_PORT` が正しいか確認。 |
| `Access denied for user` | `.env` の `DB_USERNAME` / `DB_PASSWORD` を MySQL の設定に合わせる。 |
| `Unknown database 'todo_app'` | phpMyAdmin 等で `todo_app` データベースを作成する（手順 2）。 |
| `No application encryption key` | `php artisan key:generate` を実行する。 |
| PHP バージョンエラー | PHP を 8.2 以上にする（`php -v` で確認）。 |
| ポート 8000 が使用中 | `php artisan serve --port=8080` など別ポートを指定。 |

> 設計判断や実装上の配慮点は [`NOTES.md`](NOTES.md) を参照してください。

---

# English

## Overview
Opening the app URL shows a single ToDo page. Listing, creating, editing, deleting and toggling completion all happen via **Ajax (the fetch API)** with **no page reload or navigation**. The backend is a **Laravel JSON API** and data is stored in **MySQL** (no login; single-user assumption).

## Architecture
```
[Browser]   fetch (JSON, X-CSRF-TOKEN)     [Laravel]              [MySQL]
 app.js  ─────────────────────────────▶  routes/web.php
   ▲                                       └▶ TodoController
   │        JSON (TodoResource)               ├▶ FormRequest (validation)
   └───────────────────────────────────────  ├▶ Eloquent (Todo model) ──▶ todos table
                                              └▶ TodoResource (output shaping)
```
Request flow:
1. `GET /` returns the single Blade view (the SPA shell).
2. `app.js` calls `GET /api/todos` on `DOMContentLoaded` and renders the list.
3. Create / edit / delete / toggle call the matching API via fetch and patch the DOM in place with the returned JSON.
4. Mutating requests send the `<meta name="csrf-token">` value in the `X-CSRF-TOKEN` header.

## Features
- Displays saved ToDos on load (fetched via Ajax)
- Create a ToDo (title required, description optional)
- Edit a ToDo (loaded into the top form; the button switches Add → Save, with a Discard option)
- Delete a ToDo (professional confirmation modal with Esc/Enter and focus handling)
- Toggle completion state (custom checkbox)
- Everything without page reload / navigation
- Live counter of active tasks, subtle animations
- Validation (422) and network errors shown in the UI
- Responsive layout, light/dark theme (follows OS setting)
- Security headers (clickjacking / MIME-sniffing protection)
- Bilingual (Japanese + English) UI labels, code comments, and this README

## Requirements
| Purpose | Needed |
|---------|--------|
| Run with Docker | Docker Desktop (or Docker Engine + Compose v2) |
| Run locally | PHP **8.2+** / Composer / MySQL 8 (e.g. XAMPP or WAMP) |

---

## Setup A: Docker (recommended)
The easiest, environment-independent way. Make sure **Docker Desktop is running** first.

```bash
# 1. Get the repository
git clone <repository-url>
cd Todo-App

# 2. Build & start (app: Laravel+Nginx / db: MySQL 8)
docker compose up --build -d

# 3. Open in a browser
#    http://localhost:8000
```

On start, `docker/entrypoint.sh` automatically runs:
create `.env` → `php artisan key:generate` → **wait for MySQL** → `php artisan migrate --seed` → cache config/routes → start Nginx + PHP-FPM.

Stop / clean up:
```bash
docker compose down        # stop & remove containers (DB data kept)
docker compose down -v      # also remove the DB volume
```

> The MySQL credentials are defined in `docker-compose.yml` and take precedence over `.env` (DB_HOST=db / DB_DATABASE=todo_app / DB_USERNAME=todo / DB_PASSWORD=secret). No manual config is needed.

---

## Setup B: XAMPP / WAMP (local)
Use the MySQL bundled with XAMPP or WAMP to run the app locally.

### Step 1. Start XAMPP / WAMP and MySQL
- **XAMPP**: in the Control Panel, click **Start** for **MySQL** (and Apache if you want it).
- **WAMP**: start the MySQL service from the tray icon.

> Laravel requires **PHP 8.2+**. Check that the PHP bundled with XAMPP is 8.2 or newer (`php -v`). If it's older, use the latest XAMPP or install PHP 8.2 separately and add it to your PATH.

### Step 2. Create the database
Open phpMyAdmin (`http://localhost/phpmyadmin`) and create a new database named **`todo_app`** with collation `utf8mb4_unicode_ci`.

Or via SQL:
```sql
CREATE DATABASE todo_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Step 3. Install dependencies & set up environment
```bash
cd Todo-App

# Install PHP dependencies
composer install

# Create .env and generate the app key
cp .env.example .env      # Windows Command Prompt: copy .env.example .env
php artisan key:generate
```

### Step 4. Edit the `.env` DB settings
Open `.env` and match the DB settings to **your MySQL**. XAMPP / WAMP default to user **`root`** with **no password**.

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=todo_app
DB_USERNAME=root
DB_PASSWORD=
```
> If you set a password, use `DB_PASSWORD=your_password`.

### Step 5. Run migrations and seed sample data
```bash
php artisan migrate --seed
```
This creates the `todos` table and inserts 3 sample rows for verification.

### Step 6. Start the dev server
```bash
php artisan serve
```
Open **http://localhost:8000** in your browser.

> Using `php artisan serve` is recommended over Apache (`http://localhost/...`) — minimal, reliable config. If you prefer Apache, point the document root at this project's `public/` directory.

---

## Setup C: Deploying to Railway (live demo)
This repository deploys to Railway as-is via Docker; `railway.json` and the `Dockerfile` are included.

### Step 1. Add the app and a MySQL service
1. On [Railway](https://railway.app), choose **New Project** → **Deploy from GitHub repo** and select this repository.
2. In the same project, add **+ New** → **Database** → **Add MySQL**.

### Step 2. Generate an application key
Run this locally and copy the resulting `base64:...` value:
```bash
php artisan key:generate --show
```

### Step 3. Set environment variables
Set the following under the app service's **Variables** tab.

| Variable | Value | Why |
|----------|-------|-----|
| `APP_KEY` | `base64:...` (from step 2) | Must be fixed, or sessions break on every deploy |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | **Required in production** (prevents leaking stack traces) |
| `APP_URL` | `https://<your-generated-domain>` | |
| `DB_CONNECTION` | `mysql` | |
| `DB_URL` | `${{MySQL.MYSQL_URL}}` | Reference to the MySQL service; Laravel parses `DB_URL` natively |
| `LOG_CHANNEL` | `stderr` | So logs appear in Railway's log viewer |

> `DB_URL` uses Railway's variable-reference syntax. Adjust the name if your MySQL service isn't called `MySQL`.

### Step 4. Generate a public domain
Go to the app service's **Settings** → **Networking** → **Generate Domain**, then update `APP_URL` with the issued URL.

### How it works
- Railway assigns `$PORT` dynamically, so `entrypoint.sh` rewrites the nginx listening port at start-up.
- Migrations and seeding run automatically on boot (the seeder is idempotent, so redeploys never duplicate rows).
- The healthcheck uses `/up` (Laravel's built-in endpoint).
- Railway terminates TLS at its proxy, so `trustProxies` is enabled to detect HTTPS correctly.

---

## Running tests
```bash
php artisan test        # feature tests on in-memory SQLite (no MySQL needed)
```

## API endpoints
| Method | URI                          | Action   | Purpose / success status       |
|--------|------------------------------|----------|--------------------------------|
| GET    | `/`                          | page     | Serve the single Blade view (200) |
| GET    | `/api/todos`                 | index    | Return all todos as JSON (200) |
| POST   | `/api/todos`                 | store    | Create a todo (201)            |
| PUT    | `/api/todos/{todo}`          | update   | Update title/description (200) |
| PATCH  | `/api/todos/{todo}/complete` | complete | Toggle completion (200)        |
| DELETE | `/api/todos/{todo}`          | destroy  | Delete a todo (204)            |

Validation errors return **422** (with a JSON error body); a missing id returns **404**.

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
app/Models/Todo.php                             Eloquent model ($fillable)
app/Http/Controllers/TodoController.php         Resource controller (thin methods)
app/Http/Requests/StoreTodoRequest.php          Create validation
app/Http/Requests/UpdateTodoRequest.php         Update validation
app/Http/Resources/TodoResource.php             JSON output shaping
app/Http/Middleware/SecurityHeaders.php         Adds security headers
database/migrations/..._create_todos_table.php  Schema definition (source of truth)
database/seeders/TodoSeeder.php                 Sample data
routes/web.php                                  Routes (web middleware + CSRF)
resources/views/todos.blade.php                 The single Blade view
public/js/app.js                                fetch-based CRUD (no reload)
public/css/app.css                              Styling (responsive, dark-mode)
tests/Feature/TodoApiTest.php                   API feature tests
tests/Unit/TodoModelTest.php                    Model unit tests
Dockerfile / docker-compose.yml                 Docker setup
docker/                                         nginx / supervisor / entrypoint
```

## Troubleshooting
| Symptom | Fix |
|---------|-----|
| `SQLSTATE[HY000] [2002]` connection refused | Ensure MySQL is running and `.env` `DB_HOST` / `DB_PORT` are correct. |
| `Access denied for user` | Match `.env` `DB_USERNAME` / `DB_PASSWORD` to your MySQL setup. |
| `Unknown database 'todo_app'` | Create the `todo_app` database (Step 2). |
| `No application encryption key` | Run `php artisan key:generate`. |
| PHP version error | Use PHP 8.2+ (check with `php -v`). |
| Port 8000 already in use | Use another port, e.g. `php artisan serve --port=8080`. |

> See [`NOTES.md`](NOTES.md) for design decisions and implementation considerations.
