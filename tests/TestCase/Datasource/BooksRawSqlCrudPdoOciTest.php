<?php
declare(strict_types=1);

namespace App\Test\TestCase\Datasource;

class BooksRawSqlCrudPdoOciTest extends BooksRawSqlCrudTestCase
{
    protected function connectionName(): string
    {
        return 'oracle_pdo';
    }
}
