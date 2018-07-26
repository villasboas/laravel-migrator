<?php

namespace Migrator\Tests;

use Migrator\MigratorServiceProvider;
use Orchestra\Testbench\TestCase;

class BaseTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $this->useSqliteMemory($app);
//        $this->usePostgres($app);
//        $this->useMysql($app);
//        $this->useMssql($app);
    }

    protected function getPackageProviders($app)
    {
        return [
            MigratorServiceProvider::class,
        ];
    }

    /**
     * @param $app
     */
    protected function useSqliteMemory($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * @param $app
     */
    protected function usePostgres($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'pgsql',
            'database' => 'testbench',
            'host' => '127.0.0.1',
            'username' => getenv('USER'),
            'password' => '',
            'charset' => 'utf8',
        ]);
    }

    /**
     * @param $app
     */
    protected function useMysql($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'mysql',
            'database' => 'testbench',
            'host' => '127.0.0.1',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
        ]);
    }

    private function useMssql($app)
    {
        // https://medium.com/@reverentgeek/sql-server-running-on-a-mac-3efafda48861
        // docker pull microsoft/mssql-server-linux:2017-latest
        // docker run --name mssql -e 'ACCEPT_EULA=Y' -e 'SA_PASSWORD=aVeryComplexPassword99!' -e 'MSSQL_PID=Developer' -p 1433:1433 microsoft/mssql-server-linux:2017-latest
        // create database testbench
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlsrv',
            'host' => '127.0.0.1',
            'database' => 'testbench',
            'username' => 'sa',
            'password' => 'aVeryComplexPassword99!', // MS requires complex password
            'prefix' => '',
            'options' => [\PDO::ATTR_PERSISTENT => true],
        ]);
    }
}
