<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

class BooksQueryPdoOciTest extends BooksQueryTestCase
{
    protected function connectionName(): string
    {
        return 'oracle_pdo';
    }
}
