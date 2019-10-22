<?php

namespace Migrator\Tests;

use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Migrator\Migrator;
use ParseError;

trait GenerateAndRun
{
    public static $namespaceNumber = 0;
    public $ns = '!!!';
    public $overwriteModels = false;

    /** @var Migrator */
    private $migrator;

    protected function setUp(): void
    {
        parent::setUp();
        self::$namespaceNumber++;
        $this->cleanupMigrationFiles();
        $this->migrator = new Migrator();
        $this->migrator->appPath = dirname(__DIR__).'/';

        $f = new Filesystem();
        $f->deleteDirectory($this->migrator->appPath.'app/Models');

        DB::beginTransaction();
    }

    private function cleanupMigrationFiles()
    {
        foreach (glob(database_path('migrations/*')) as $file) {
            unlink($file);
        }
    }

    protected function tearDown():void
    {
        DB::rollBack();
        foreach ($this->migrator->getCreatedFileNames() as $file) {
            //print "Delete $file\n";
            unlink($file);
        }
        $f = new Filesystem();
        $f->deleteDirectory($this->migrator->appPath.'app/Models');
        parent::tearDown();
    }

    private function generateButDontRun($string)
    {
        $this->migrator = new Migrator();
        $this->migrator->appPath = dirname(__DIR__).'/';
        if ($this->overwriteModels) {
            $this->migrator->overwriteModels();
        }
        $string = $this->replaceNamespace($string);

        try {
            $this->migrator->migrate($string, true, false);
        } catch (ParseError $e) {
            $this->dump();

            throw $e;
        }

        return $this->ns;
    }

    /**
     * @param $string
     *
     * @return mixed
     */
    private function replaceNamespace($string)
    {
        if (Str::contains($string, '{namespace}')) {
            $this->ns = "App\Models\TestNs".(self::$namespaceNumber);
            $string = str_replace('{namespace}', $this->ns, $string);
        }

        return $string;
    }

    private function dump(): void
    {
        $this->dumpMigrations();
        $this->dumpModels();
    }

    private function dumpMigrations($filter = null)
    {
        if (empty($this->migrator->migrationsCreated)) {
            echo "[!!] No migrations were created!\n\n";
        }
        foreach ($this->migrator->migrationsCreated as $file => $contents) {
            if ($filter === null || Str::contains(strtolower($file), strtolower($filter))) {
                echo "-------\n$file\n-------\n$contents\n-------\n\n";
            }
        }
    }

    private function dumpModels($filterNames = null)
    {
        if (empty($this->migrator->modelsCreated) && empty($this->migrator->modelsUpdated)) {
            echo "[!!] No models were created!\n\n";
        }
        foreach ($this->migrator->modelsCreated as $file => $contents) {
            if ($filterNames === null || Str::contains(strtolower($file), strtolower($filterNames))) {
                echo "-------\n$file\n-------\n$contents\n-------\n\n";
            }
        }
        foreach ($this->migrator->modelsUpdated as $file => $contents) {
            if ($filterNames === null || Str::contains(strtolower($file), strtolower($filterNames))) {
                echo "-------\n$file\n-------\n$contents\n-------\n\n";
            }
        }
    }

    private function generateAndRun($string)
    {
        $this->migrator = new Migrator();
        $this->migrator->appPath = dirname(__DIR__).'/';
        if ($this->overwriteModels) {
            $this->migrator->overwriteModels();
        }
        $string = $this->replaceNamespace($string);

        try {
            $this->migrator->migrate($string);
        } catch (ParseError $e) {
            $this->dump();

            throw $e;
        }

        return $this->ns;
    }

    /**
     * @param $className
     *
     * @return mixed|Model
     */
    private function newInstanceOf($className)
    {
        $cls = "{$this->ns}\\$className";

        return new $cls();
    }

    private function runGenerated(): void
    {
        $this->migrator->runMigrations();
    }

    private function assertIndexExists($table, $index)
    {
        $this->assertTrue($this->migrator->indexExists($table, $index), "Index $index does not exist on table $table");
    }

    private function assertIndexNotExists($table, $index)
    {
        $this->assertFalse(
            $this->migrator->indexExists($table, $index),
            "Index $index does exist on table $table, but should not"
        );
    }

    private function overwriteModels()
    {
        $this->overwriteModels = true;
        $this->migrator->overwriteModels();
    }

    /**
     * @param $model
     *
     * @return bool|string
     */
    private function getModelContents($model)
    {
        return file_get_contents($this->currentModelDir("{$model}.php"));
    }

    /**
     * @param string $filename
     *
     * @return string
     */
    private function currentModelDir($filename = ''): string
    {
        return dirname(__DIR__).DIRECTORY_SEPARATOR.lcfirst(str_replace(
                '\\',
                '/',
                $this->ns
            )).DIRECTORY_SEPARATOR.$filename;
    }

    private function assertFieldExists($table, $field): void
    {
        $this->assertContains($field, $this->migrator->getDb()->getColumnListing($table));
    }

    private function assertFieldNotExists($table, $field): void
    {
        $this->assertNotContains($field, $this->migrator->getDb()->getColumnListing($table));
    }

    private function assertTableExists($table): void
    {
        $this->assertTrue($this->migrator->getDb()->hasTable($table), "Table '$table' does not exist");
    }

    private function assertTableNotExists($table): void
    {
        $this->assertFalse($this->migrator->getDb()->hasTable($table), "Table '$table' does exist, but should not");
    }

    private function assertClassExists($class)
    {
        $this->assertTrue(class_exists($class), "Class '$class' does not exist");
    }

    private function assertFileExistsInApp($file)
    {
        return $this->assertTrue(file_exists(__DIR__.'/../app/'.$file));
    }

    private function assertMigrationsNotContain($what, $filterNames = null)
    {
        $found = false;
        foreach ($this->migrator->migrationsCreated as $file => $contents) {
            if ($filterNames === null || Str::contains(strtolower($file), strtolower($filterNames))) {
                $found = true;
                if (Str::contains($contents, $what)) {
                    $this->dumpMigrations($filterNames);
                    $this->fail("Migrations (filter: '$filterNames') do contain: '$what' (but should not)");

                    return;
                }
            }
        }

        if (!$found) {
            $this->fail("None of migrations pass the filter '$filterNames' (none generated?)");
        }

        $this->assertTrue(true);
    }

    private function assertMigrationsContain($what, $filterNames = null)
    {
        $found = false;
        foreach ($this->migrator->migrationsCreated as $file => $contents) {
            if ($filterNames === null || Str::contains(strtolower($file), strtolower($filterNames))) {
                $found = true;
                if (Str::contains($contents, $what)) {
                    $this->assertTrue(true);

                    return;
                }
            }
        }

        if (!$found) {
            $this->fail("None of models pass the filter '$filterNames' (none generated?)'");
        }

        $this->dumpMigrations($filterNames);
        $this->fail("Migrations (filter: '$filterNames') do not contain: '$what'");
    }

    private function assertModelsContain($what, $filterNames = null)
    {
        $found = false;
        $models = array_merge($this->migrator->modelsCreated, $this->migrator->modelsUpdated);
        foreach ($models as $file => $contents) {
            if ($filterNames === null || Str::contains(strtolower($file), strtolower($filterNames))) {
                $found = true;
                if (Str::contains($contents, $what)) {
                    $this->assertTrue(true);

                    return;
                }
            }
        }

        if (!$found) {
            $this->fail("None of models pass the filter $filterNames");
        }

        $this->dumpModels($filterNames);
        $this->fail("Models (filter: '$filterNames') do not contain: '$what'");
    }
}
