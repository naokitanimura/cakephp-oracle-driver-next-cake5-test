<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

class BooksQueryOci8Test extends BooksQueryTestCase
{
    protected function connectionName(): string
    {
        return 'oracle_oci8';
    }
}
