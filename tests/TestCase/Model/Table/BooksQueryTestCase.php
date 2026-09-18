<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\BooksTable;
use Cake\Database\Expression\IdentifierExpression;
use Cake\Datasource\ConnectionManager;
use PHPUnit\Framework\TestCase;

/**
 * Shared Query Builder assertions (Table::find() only -- no raw SQL, no
 * association JOINs) run against a real Oracle Database container.
 * Concrete subclasses only pick which connection (OCI8 or PDO_OCI) the
 * assertions run over.
 */
abstract class BooksQueryTestCase extends TestCase
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

        $this->seedBooks();
    }

    /**
     * Fixed dataset shared by the Query Builder assertions below: five
     * books across three authors and a spread of prices, inserted in a
     * known order so ordering/pagination/grouping assertions are stable.
     */
    private function seedBooks(): void
    {
        $rows = [
            ['title' => 'Oracle Database Fundamentals', 'author' => 'CakeDC', 'price' => 39.99],
            ['title' => 'CQRS with CakePHP', 'author' => 'Jane Doe', 'price' => 19.50],
            ['title' => 'Advanced Oracle SQL', 'author' => 'CakeDC', 'price' => 49.00],
            ['title' => 'PHP for Beginners', 'author' => 'John Smith', 'price' => 9.99],
            ['title' => 'Oracle Performance Tuning', 'author' => 'Jane Doe', 'price' => 59.99],
        ];

        foreach ($rows as $row) {
            $book = $this->Books->newEntity($row);
            $saved = $this->Books->save($book);
            $this->assertNotFalse($saved);
        }
    }

    public function testSelectSpecificFields(): void
    {
        $book = $this->Books->find()
            ->select(['id', 'title'])
            ->where(['title' => 'CQRS with CakePHP'])
            ->firstOrFail();

        $this->assertSame('CQRS with CakePHP', $book->title);
        // "author" was not selected, so it must not have been hydrated.
        $this->assertNull($book->author);
    }

    public function testWhereComparisonOperator(): void
    {
        $titles = $this->Books->find()
            ->select(['title'])
            ->where(['price >' => 40])
            ->orderBy(['title' => 'ASC'])
            ->all()
            ->extract('title')
            ->toList();

        $this->assertSame(['Advanced Oracle SQL', 'Oracle Performance Tuning'], $titles);
    }

    public function testWhereLike(): void
    {
        $titles = $this->Books->find()
            ->select(['title'])
            ->where(['title LIKE' => '%Oracle%'])
            ->orderBy(['title' => 'ASC'])
            ->all()
            ->extract('title')
            ->toList();

        $this->assertSame(
            ['Advanced Oracle SQL', 'Oracle Database Fundamentals', 'Oracle Performance Tuning'],
            $titles,
        );
    }

    public function testWhereIn(): void
    {
        $titles = $this->Books->find()
            ->select(['title'])
            ->where(['author IN' => ['CakeDC', 'John Smith']])
            ->orderBy(['title' => 'ASC'])
            ->all()
            ->extract('title')
            ->toList();

        $this->assertSame(
            ['Advanced Oracle SQL', 'Oracle Database Fundamentals', 'PHP for Beginners'],
            $titles,
        );
    }

    public function testOrderBy(): void
    {
        $prices = $this->Books->find()
            ->select(['price'])
            ->orderBy(['price' => 'ASC'])
            ->all()
            ->extract('price')
            ->map(fn ($price) => (float)$price)
            ->toList();

        $this->assertSame([9.99, 19.50, 39.99, 49.00, 59.99], $prices);
    }

    public function testLimitAndOffset(): void
    {
        // Oracle has no native LIMIT/OFFSET -- the driver must translate
        // this into ROWNUM/FETCH FIRST, which this test exercises.
        $titles = $this->Books->find()
            ->select(['title'])
            ->orderBy(['price' => 'ASC'])
            ->limit(2)
            ->offset(1)
            ->all()
            ->extract('title')
            ->toList();

        $this->assertSame(['CQRS with CakePHP', 'Oracle Database Fundamentals'], $titles);
    }

    public function testCountWithCondition(): void
    {
        $count = $this->Books->find()
            ->where(['price <' => 50])
            ->count();

        $this->assertSame(4, $count);
    }

    public function testGroupByWithAggregateFunctions(): void
    {
        $rows = $this->Books->find();
        $rows
            ->select([
                'author' => 'author',
                'book_count' => $rows->func()->count('*'),
                // Must be an IdentifierExpression, not the bare string
                // 'price' -- FunctionsBuilder treats a bare string argument
                // as a literal (unquoted), which Oracle then folds to
                // uppercase PRICE and fails with ORA-00904.
                'avg_price' => $rows->func()->avg(new IdentifierExpression('price')),
            ])
            ->groupBy('author')
            ->orderBy(['author' => 'ASC']);

        $results = $rows->all()->toList();

        $this->assertCount(3, $results);

        $byAuthor = [];
        foreach ($results as $row) {
            $byAuthor[$row->author] = [
                'book_count' => (int)$row->book_count,
                'avg_price' => (float)$row->avg_price,
            ];
        }

        $this->assertSame(2, $byAuthor['CakeDC']['book_count']);
        $this->assertEqualsWithDelta(44.495, $byAuthor['CakeDC']['avg_price'], 0.001);

        $this->assertSame(2, $byAuthor['Jane Doe']['book_count']);
        $this->assertEqualsWithDelta(39.745, $byAuthor['Jane Doe']['avg_price'], 0.001);

        $this->assertSame(1, $byAuthor['John Smith']['book_count']);
        $this->assertEqualsWithDelta(9.99, $byAuthor['John Smith']['avg_price'], 0.001);
    }

    public function testWhereInSubquery(): void
    {
        // Authors with at least one book priced above 40: CakeDC (49.00),
        // Jane Doe (59.99).
        $expensiveAuthors = $this->Books->find()
            ->select(['author'])
            ->where(['price >' => 40]);

        $titles = $this->Books->find()
            ->select(['title'])
            ->where(['author IN' => $expensiveAuthors])
            ->orderBy(['title' => 'ASC'])
            ->all()
            ->extract('title')
            ->toList();

        $this->assertSame(
            ['Advanced Oracle SQL', 'CQRS with CakePHP', 'Oracle Database Fundamentals', 'Oracle Performance Tuning'],
            $titles,
        );
    }

    public function testWhereComparisonSubquery(): void
    {
        // Books priced above the average price of all books (35.694),
        // computed via a scalar subquery rather than a PHP-side constant.
        $averagePriceQuery = $this->Books->find();
        $averagePriceQuery->select([
            'avg_price' => $averagePriceQuery->func()->avg(new IdentifierExpression('price')),
        ]);

        $titles = $this->Books->find()
            ->select(['title'])
            ->where(['price >' => $averagePriceQuery])
            ->orderBy(['title' => 'ASC'])
            ->all()
            ->extract('title')
            ->toList();

        $this->assertSame(
            ['Advanced Oracle SQL', 'Oracle Database Fundamentals', 'Oracle Performance Tuning'],
            $titles,
        );
    }
}
