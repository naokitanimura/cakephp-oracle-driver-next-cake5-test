<?php
declare(strict_types=1);

namespace App\Test\TestCase\Datasource;

class BooksRawSqlCrudOci8Test extends BooksRawSqlCrudTestCase
{
    protected function connectionName(): string
    {
        return 'oracle_oci8';
    }
}
