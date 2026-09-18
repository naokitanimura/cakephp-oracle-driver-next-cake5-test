<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\BooksTable;
use Cake\Datasource\ConnectionManager;
use PHPUnit\Framework\TestCase;

/**
 * Shared Create/Read/Update/Delete assertions run against a real Oracle
 * Database container. Concrete subclasses only pick which connection
 * (OCI8 or PDO_OCI) the assertions run over.
 */
abstract class BooksCrudTestCase extends TestCase
{
    protected BooksTable $Books;

    abstract protected function connectionName(): string;

    protected function setUp(): void
    {
        parent::setUp();

        $this->Books = new BooksTable([
            'table' => 'books',
            'connection' => ConnectionManager::get($this->connectionName()),
        ]);
        // "books" is quoted because it was created quoted lowercase (see
        // docker/oracle/startup/02_create_schema.sql); unquoted here would
        // resolve to the folded-uppercase "BOOKS", which doesn't exist.
        $this->Books->getConnection()->execute('TRUNCATE TABLE "books"');
    }

    public function testCreate(): void
    {
        $book = $this->Books->newEntity([
            'title' => 'Oracle Database Fundamentals',
            'author' => 'CakeDC',
            'price' => 39.99,
        ]);
        $result = $this->Books->save($book);

        $this->assertNotFalse($result);
        $this->assertNotEmpty($result->id);
        $this->assertNotEmpty($result->created);
        $this->assertNotEmpty($result->modified);
    }

    public function testRead(): void
    {
        $saved = $this->Books->save($this->Books->newEntity([
            'title' => 'CQRS with CakePHP',
            'author' => 'Jane Doe',
            'price' => 19.5,
        ]));
        $this->assertNotFalse($saved);

        $fetched = $this->Books->get($saved->id);

        $this->assertSame('CQRS with CakePHP', $fetched->title);
        $this->assertSame('Jane Doe', $fetched->author);
        $this->assertEqualsWithDelta(19.5, (float)$fetched->price, 0.001);
    }

    public function testUpdate(): void
    {
        $saved = $this->Books->save($this->Books->newEntity([
            'title' => 'Original title',
            'author' => 'Author A',
            'price' => 10,
        ]));
        $this->assertNotFalse($saved);
        $originalModified = $saved->modified;

        // Force a visible tick so the Timestamp behavior's "modified"
        // update is distinguishable from the create-time value.
        sleep(1);

        $saved->title = 'Updated title';
        $updated = $this->Books->save($saved);
        $this->assertNotFalse($updated);

        $fetched = $this->Books->get($saved->id);
        $this->assertSame('Updated title', $fetched->title);
        $this->assertTrue($fetched->modified->greaterThanOrEquals($originalModified));
    }

    public function testDelete(): void
    {
        $saved = $this->Books->save($this->Books->newEntity([
            'title' => 'To be deleted',
            'author' => 'Author B',
            'price' => 5,
        ]));
        $this->assertNotFalse($saved);

        $result = $this->Books->delete($saved);

        $this->assertTrue($result);
        $this->assertFalse($this->Books->exists(['id' => $saved->id]));
    }

    public function testFullCrudCycle(): void
    {
        $this->assertSame(0, $this->Books->find()->count());

        $book = $this->Books->save($this->Books->newEntity([
            'title' => 'Full Cycle',
            'author' => 'Author C',
            'price' => 12.34,
        ]));
        $this->assertNotFalse($book);
        $this->assertSame(1, $this->Books->find()->count());

        $book->price = 99.99;
        $updated = $this->Books->save($book);
        $this->assertNotFalse($updated);

        $fetched = $this->Books->get($book->id);
        $this->assertEqualsWithDelta(99.99, (float)$fetched->price, 0.001);

        $this->Books->delete($fetched);
        $this->assertSame(0, $this->Books->find()->count());
    }
}
