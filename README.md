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
アプリ用ユーザー `testuser` と検証用テーブル `books`/`authors` が作成されます
（`container-registry.oracle.com/database/free:*-lite` は事前構築済みDBイメージのため
`/opt/oracle/scripts/setup`(初回のみ)ではなく `/opt/oracle/scripts/startup`(毎回起動時)
が使われます。スクリプト側は`CREATE USER`の存在チェックや`CREATE TABLE IF NOT EXISTS`で
再実行に対して安全になっています）。データを作り直したい場合、あるいは
`docker/oracle/startup/02_create_schema.sql` のテーブル定義を変更した場合は、
`CREATE TABLE IF NOT EXISTS`がテーブル存在時は何もしないため
`docker compose down -v` でボリュームを含めて削除し、スキーマを作り直してください。

## テスト内容

### ORM経由のCRUDテスト

`tests/TestCase/Model/Table/` に以下のテストがあります。いずれも同じCRUDシナリオ
(`BooksCrudTestCase`) を共有し、接続だけが異なります。CakePHPのORM (`Table::save()` /
`get()` / `delete()`) を経由してCRUDを検証します。

- `BooksCrudOci8Test` — `CakeDC\OracleDriver\Database\Driver\OracleOCI` 経由
- `BooksCrudPdoOciTest` — `CakeDC\OracleDriver\Database\Driver\OraclePDO` 経由

各テストクラスで以下を検証します。

- `testCreate` — レコード作成、自動採番ID・`created`/`modified`の設定
- `testRead` — `get()`によるレコード取得
- `testUpdate` — 更新と`modified`タイムスタンプの更新
- `testDelete` — 削除と`exists()`によるレコード消失の確認
- `testFullCrudCycle` — 作成→更新→削除を一連の流れで検証

### ORM経由のQuery Builderテスト

`tests/TestCase/Model/Table/` には、上記CRUDテストとは別に、`Table::find()`の
Query Builderのみ（生SQLやアソシエーションJOINは使わない）を対象とした検証があります。
同じシナリオ(`BooksQueryTestCase`)を共有し、接続だけが異なります。各テストの`setUp()`で
著者・価格の異なる5件の書籍を投入し、既知のデータセットに対してアサートします。

- `BooksQueryOci8Test` — `CakeDC\OracleDriver\Database\Driver\OracleOCI` 経由
- `BooksQueryPdoOciTest` — `CakeDC\OracleDriver\Database\Driver\OraclePDO` 経由

各テストクラスで以下を検証します。

- `testSelectSpecificFields` — `select()`で指定したフィールドのみ取得されること
- `testWhereComparisonOperator` — `where(['price >' => ...])`などの比較演算子
- `testWhereLike` — `where(['title LIKE' => ...])`による部分一致検索
- `testWhereIn` — `where(['author IN' => [...]])`による複数値の絞り込み
- `testOrderBy` — `orderBy()`による並び替え
- `testLimitAndOffset` — `limit()`/`offset()`によるページネーション
  (OracleにはネイティブのLIMIT/OFFSETがなく、ドライバーがROWNUM/FETCH FIRSTへ変換する)
- `testCountWithCondition` — 条件付き`count()`
- `testGroupByWithAggregateFunctions` — `groupBy()`と`func()->count()`/`func()->avg()`による集計
- `testWhereInSubquery` — `where(['author IN' => $subquery])`によるサブクエリ(IN句)
- `testWhereComparisonSubquery` — `where(['price >' => $subquery])`によるスカラサブクエリ比較
- `testContainAssociation` — `contain('Authors')`による`books.author_id -> authors.id`の
  `belongsTo`関連読み込み(`BooksTable`に定義。関連先エンティティは既存の`author`文字列カラムと
  衝突しないよう`propertyName: 'author_ref'`で`author_ref`プロパティに載る)

### ConnectionManager + 生SQLによるCRUDテスト

`tests/TestCase/Datasource/` には、ORM (Table) を介さず `ConnectionManager::get()` で
取得した接続に対して直接SQLを実行するCRUDテストがあります。同じCRUDシナリオ
(`BooksRawSqlCrudTestCase`) を共有し、接続だけが異なります。

- `BooksRawSqlCrudOci8Test` — `CakeDC\OracleDriver\Database\Driver\OracleOCI` 経由
- `BooksRawSqlCrudPdoOciTest` — `CakeDC\OracleDriver\Database\Driver\OraclePDO` 経由

各テストクラスで以下を検証します（いずれも `Connection::execute()` によるプレースホルダ
付き生SQLで実装）。

- `testCreate` — `INSERT`文の実行と`lastInsertId()`によるID取得
- `testRead` — `SELECT`文によるレコード取得
- `testUpdate` — `UPDATE`文による更新と`modified`(`SYSTIMESTAMP`)の更新
- `testDelete` — `DELETE`文による削除とその後の`SELECT`結果消失の確認
- `testFullCrudCycle` — `INSERT`→`UPDATE`→`DELETE`を一連の流れで検証
- `testBeginCommit` — `Connection::begin()`でトランザクション開始後`INSERT`し、
  `commit()`で確定されることを確認
- `testBeginRollback` — `begin()`後の`INSERT`が、コミット前は同一トランザクション内から
  見えること、`rollback()`後は破棄されて残らないことを確認

## 個別テストの実行例

```sh
docker compose run --rm app vendor/bin/phpunit --filter BooksCrudOci8Test
docker compose run --rm app vendor/bin/phpunit --filter BooksCrudPdoOciTest
docker compose run --rm app vendor/bin/phpunit --filter BooksQueryOci8Test
docker compose run --rm app vendor/bin/phpunit --filter BooksQueryPdoOciTest
docker compose run --rm app vendor/bin/phpunit --filter BooksRawSqlCrudOci8Test
docker compose run --rm app vendor/bin/phpunit --filter BooksRawSqlCrudPdoOciTest
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
