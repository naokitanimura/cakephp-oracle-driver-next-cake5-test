<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

class BooksCrudOci8Test extends BooksCrudTestCase
{
    protected function connectionName(): string
    {
        return 'oracle_oci8';
    }
}
