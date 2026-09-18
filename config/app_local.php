<?php
declare(strict_types=1);

use function Cake\Core\env;

/**
 * Datasource configuration for the two connections this project's PHPUnit
 * suite exercises: one using the OCI8 extension, one using PDO_OCI. Both
 * point at the same Oracle Database Free container / FREEPDB1 service and
 * the same "testuser" schema created by docker/oracle/startup/01_create_user.sql.
 *
 * See vendor/cakedc/cakephp-oracle-driver/README.md for the full list of
 * supported Datasource options.
 */
return [
    'Datasources' => [
        'oracle_oci8' => [
            'className' => 'CakeDC\OracleDriver\Database\OracleConnection',
            'driver' => 'CakeDC\OracleDriver\Database\Driver\OracleOCI',
            'persistent' => false,
            'host' => env('DB_HOST', 'oracle'),
            'port' => env('DB_PORT', '1521'),
            'username' => env('DB_USERNAME', 'testuser'),
            'password' => env('DB_PASSWORD', 'TestUserPass123'),
            'database' => env('DB_SERVICE', 'FREEPDB1'),
            'encoding' => 'AL32UTF8',
            'cacheMetadata' => false,
            'server_version' => 23,
            'autoincrement' => true,
            // Our DDL (docker/oracle/startup/02_create_schema.sql) quotes
            // identifiers lowercase; without this, generated SQL leaves
            // "books" unquoted and Oracle folds it to "BOOKS" (ORA-00942).
            'quoteIdentifiers' => true,
        ],
        'oracle_pdo' => [
            'className' => 'CakeDC\OracleDriver\Database\OracleConnection',
            'driver' => 'CakeDC\OracleDriver\Database\Driver\OraclePDO',
            'persistent' => false,
            'host' => env('DB_HOST', 'oracle'),
            'port' => env('DB_PORT', '1521'),
            'username' => env('DB_USERNAME', 'testuser'),
            'password' => env('DB_PASSWORD', 'TestUserPass123'),
            'database' => env('DB_SERVICE', 'FREEPDB1'),
            'encoding' => 'AL32UTF8',
            'cacheMetadata' => false,
            'server_version' => 23,
            'autoincrement' => true,
            'quoteIdentifiers' => true,
        ],
    ],
];
