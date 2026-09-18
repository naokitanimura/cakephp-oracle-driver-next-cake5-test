<?php
declare(strict_types=1);

namespace App\Test\TestCase\Datasource;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use PHPUnit\Framework\TestCase;

/**
 * Shared Create/Read/Update/Delete assertions run against a real Oracle
 * Database container, writing raw SQL through the Connection obtained from
 * ConnectionManager (no ORM/Table layer involved). Concrete subclasses only
 * pick which connection (OCI8 or PDO_OCI) the assertions run over.
 */
abstract class BooksRawSqlCrudTestCase extends TestCase
{
    protected Connection $connection;

    abstract protected function connectionName(): string;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = ConnectionManager::get($this->connectionName());
        // "books" is quoted because it was created quoted lowercase (see
        // docker/oracle/startup/02_create_schema.sql); unquoted here would
        // resolve to the folded-uppercase "BOOKS", which doesn't exist.
        $this->connection->execute('TRUNCATE TABLE "books"');
    }

    /**
     * @param array{title: string, author: string, price: float|int} $data
     */
    protected function insertBook(array $data): int
    {
        $sql = 'INSERT INTO "books" ("title", "author", "price", "created", "modified") '
            . 'VALUES (:title, :author, :price, SYSTIMESTAMP, SYSTIMESTAMP)';
        $statement = $this->connection->execute($sql, [
            'title' => $data['title'],
            'author' => $data['author'],
            'price' => $data['price'],
        ], [
            'title' => 'string',
            'author' => 'string',
            'price' => 'float',
        ]);

        // lastInsertId() lives on the Statement (backed by the Driver), not
        // on the Connection, in this CakePHP version.
        return (int)$statement->lastInsertId('books', 'id');
    }

    /**
     * @return array<string, mixed>|false
     */
    protected function fetchBook(int $id): array|false
    {
        $sql = 'SELECT "id", "title", "author", "price", "created", "modified" '
            . 'FROM "books" WHERE "id" = :id';
        $statement = $this->connection->execute($sql, ['id' => $id], ['id' => 'integer']);

        return $statement->fetch('assoc');
    }

    protected function countBooks(): int
    {
        $statement = $this->connection->execute('SELECT COUNT(*) AS "cnt" FROM "books"');
        $row = $statement->fetch('assoc');

        return (int)$row['cnt'];
    }

    public function testCreate(): void
    {
        $id = $this->insertBook([
            'title' => 'Oracle Database Fundamentals',
            'author' => 'CakeDC',
            'price' => 39.99,
        ]);

        $this->assertNotEmpty($id);

        $book = $this->fetchBook($id);
        $this->assertNotFalse($book);
        $this->assertNotEmpty($book['created']);
        $this->assertNotEmpty($book['modified']);
    }

    public function testRead(): void
    {
        $id = $this->insertBook([
            'title' => 'CQRS with CakePHP',
            'author' => 'Jane Doe',
            'price' => 19.5,
        ]);

        $book = $this->fetchBook($id);

        $this->assertNotFalse($book);
        $this->assertSame('CQRS with CakePHP', $book['title']);
        $this->assertSame('Jane Doe', $book['author']);
        $this->assertEqualsWithDelta(19.5, (float)$book['price'], 0.001);
    }

    public function testUpdate(): void
    {
        $id = $this->insertBook([
            'title' => 'Original title',
            'author' => 'Author A',
            'price' => 10,
        ]);
        $original = $this->fetchBook($id);
        $this->assertNotFalse($original);
        $originalModified = $original['modified'];

        // Force a visible tick so the SQL-side SYSTIMESTAMP "modified" update
        // is distinguishable from the create-time value.
        sleep(1);

        $sql = 'UPDATE "books" SET "title" = :title, "modified" = SYSTIMESTAMP WHERE "id" = :id';
        $this->connection->execute($sql, [
            'title' => 'Updated title',
            'id' => $id,
        ], [
            'title' => 'string',
            'id' => 'integer',
        ]);

        $fetched = $this->fetchBook($id);
        $this->assertNotFalse($fetched);
        $this->assertSame('Updated title', $fetched['title']);
        $this->assertGreaterThanOrEqual(
            strtotime((string)$originalModified),
            strtotime((string)$fetched['modified']),
        );
    }

    public function testDelete(): void
    {
        $id = $this->insertBook([
            'title' => 'To be deleted',
            'author' => 'Author B',
            'price' => 5,
        ]);
        $this->assertNotFalse($this->fetchBook($id));

        $this->connection->execute(
            'DELETE FROM "books" WHERE "id" = :id',
            ['id' => $id],
            ['id' => 'integer'],
        );

        $this->assertFalse($this->fetchBook($id));
        $this->assertSame(0, $this->countBooks());
    }

    public function testFullCrudCycle(): void
    {
        $this->assertSame(0, $this->countBooks());

        $id = $this->insertBook([
            'title' => 'Full Cycle',
            'author' => 'Author C',
            'price' => 12.34,
        ]);
        $this->assertSame(1, $this->countBooks());

        $this->connection->execute(
            'UPDATE "books" SET "price" = :price, "modified" = SYSTIMESTAMP WHERE "id" = :id',
            ['price' => 99.99, 'id' => $id],
            ['price' => 'float', 'id' => 'integer'],
        );

        $fetched = $this->fetchBook($id);
        $this->assertNotFalse($fetched);
        $this->assertEqualsWithDelta(99.99, (float)$fetched['price'], 0.001);

        $this->connection->execute(
            'DELETE FROM "books" WHERE "id" = :id',
            ['id' => $id],
            ['id' => 'integer'],
        );
        $this->assertSame(0, $this->countBooks());
    }

    public function testBeginCommit(): void
    {
        $this->connection->begin();
        $this->assertTrue($this->connection->inTransaction());

        $id = $this->insertBook([
            'title' => 'Transactional Commit',
            'author' => 'Author D',
            'price' => 15,
        ]);

        $this->connection->commit();

        $this->assertFalse($this->connection->inTransaction());
        $this->assertNotFalse($this->fetchBook($id));
        $this->assertSame(1, $this->countBooks());
    }

    public function testBeginRollback(): void
    {
        $this->connection->begin();

        $this->insertBook([
            'title' => 'Transactional Rollback',
            'author' => 'Author E',
            'price' => 20,
        ]);
        // Visible to reads within the same, still-open transaction.
        $this->assertSame(1, $this->countBooks());

        $this->connection->rollback();

        $this->assertFalse($this->connection->inTransaction());
        $this->assertSame(0, $this->countBooks());
    }
}
