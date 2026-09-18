# cakephp-oracle-driver 実DB CRUD検証プロジェクト

[CakeDC/cakephp-oracle-driver](https://github.com/CakeDC/cakephp-oracle-driver) の
`6.next-cake5` ブランチ (CakePHP 5.3+, PHP 8.2+) を、実際の Oracle Database に接続して
Create / Read / Update / Delete を検証するためのプロジェクトです。

**PHP・Composer・Oracle Instant Client は一切ホストPCにインストールしません。**
すべて Docker コンテナ内 (`app` サービス) に構築し、Oracle Database も同じ
`docker-compose.yml` で管理します。ホストに必要なのは Docker だけです。

## 構成

- `oracle` サービス: `container-registry.oracle.com/database/free:23.26.3.0-lite`
  (Oracle公式の Oracle Database Free 23ai lite イメージ)
- `app` サービス: `docker/app/Dockerfile` でビルドする PHP 8.3 + OCI8 + PDO_OCI コンテナ
  (Oracle Instant Client を含む。ソースコードはbind mountするが、`vendor/` は
  named volume で分離しているため、ホスト側に `vendor/` は生成されません)

## 前提条件

- Docker / Docker Compose
- `container-registry.oracle.com` からのイメージ取得には Oracle アカウントでの
  ログインとライセンス同意が必要です（初回のみ）:

  ```sh
  docker login container-registry.oracle.com
  ```

## セットアップ

```sh
# 1. 環境変数ファイルを用意（デフォルト値のままでも動作します）
cp .env.example .env

# 2. app コンテナをビルド（PHP + OCI8 + PDO_OCI を内部で構築）
docker compose build app

# 3. Oracle コンテナを起動し、healthy になるまで待つ
docker compose up -d oracle
docker compose ps   # oracle が (healthy) になるまで待機

# 4. 依存パッケージのインストール（コンテナ内で実行、ホストは汚しません）
docker compose run --rm app composer install

# 5. CRUD テスト実行（OCI8 / PDO_OCI 両方のテストクラスが実行されます）
docker compose run --rm app vendor/bin/phpunit
```

起動時、`docker/oracle/startup/` 配下の SQL スクリプトが自動実行され、
アプリ用ユーザー `testuser` と検証用テーブル `books` が作成されます
（`container-registry.oracle.com/database/free:*-lite` は事前構築済みDBイメージのため
`/opt/oracle/scripts/setup`(初回のみ)ではなく `/opt/oracle/scripts/startup`(毎回起動時)
が使われます。スクリプト側は`CREATE USER`の存在チェックや`CREATE TABLE IF NOT EXISTS`で
再実行に対して安全になっています）。データを作り直したい場合は
`docker compose down -v` でボリュームを含めて削除してください。

## テスト内容

`tests/TestCase/Model/Table/` に以下のテストがあります。いずれも同じCRUDシナリオ
(`BooksCrudTestCase`) を共有し、接続だけが異なります。

- `BooksCrudOci8Test` — `CakeDC\OracleDriver\Database\Driver\OracleOCI` 経由
- `BooksCrudPdoOciTest` — `CakeDC\OracleDriver\Database\Driver\OraclePDO` 経由

各テストクラスで以下を検証します。

- `testCreate` — レコード作成、自動採番ID・`created`/`modified`の設定
- `testRead` — `get()`によるレコード取得
- `testUpdate` — 更新と`modified`タイムスタンプの更新
- `testDelete` — 削除と`exists()`によるレコード消失の確認
- `testFullCrudCycle` — 作成→更新→削除を一連の流れで検証

## 個別テストの実行例

```sh
docker compose run --rm app vendor/bin/phpunit --filter BooksCrudOci8Test
docker compose run --rm app vendor/bin/phpunit --filter BooksCrudPdoOciTest
```

## 後片付け

```sh
docker compose down       # コンテナ停止（Oracleデータは保持）
docker compose down -v    # コンテナ停止 + Oracleデータボリュームも削除
```

## トラブルシューティング

- Oracle コンテナの起動・初期化ログ: `docker compose logs oracle`
- `app` コンテナのビルドで Oracle Instant Client のダウンロードに失敗する場合、
  `docker/app/Dockerfile` 内のダウンロードURL (`download.oracle.com`) が
  変更されている可能性があります。最新のURLに置き換えてください。
- 接続情報を変更する場合は `.env` の値と `docker/oracle/startup/01_create_user.sql`
  の値を必ず一致させてください。
