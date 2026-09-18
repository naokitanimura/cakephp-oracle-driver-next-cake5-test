<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\FactoryLocator;
use Cake\ORM\Locator\TableLocator;

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
define('TMP', ROOT . DS . 'tmp' . DS);
define('CACHE', TMP . 'cache' . DS);
define('LOGS', TMP . 'logs' . DS);

require ROOT . '/vendor/autoload.php';

Configure::write('App', [
    'namespace' => 'App',
    'encoding' => 'UTF-8',
    'paths' => [
        'plugins' => [],
    ],
]);
Configure::write('debug', true);

Cache::setConfig([
    'default' => [
        'className' => 'File',
        'path' => CACHE,
    ],
    // Used by Cake\I18n\I18n to cache translators (loaded by the ORM
    // validator's default error messages), and by other Core internals.
    '_cake_core_' => [
        'className' => 'File',
        'path' => CACHE,
        'prefix' => 'oracle_driver_crud_test_core_',
    ],
]);

FactoryLocator::add('Table', new TableLocator());

$datasources = require ROOT . '/config/app_local.php';
foreach ($datasources['Datasources'] as $name => $config) {
    ConnectionManager::setConfig($name, $config);
}
