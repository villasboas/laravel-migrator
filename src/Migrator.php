<?php

namespace Migrator;

use Illuminate\Database\Schema\MySqlBuilder;
use Illuminate\Database\Schema\PostgresBuilder;
use Illuminate\Support\Facades\Artisan;
use Migrator\Schema\Exceptions\InverseMethodNotFound;
use Migrator\Schema\Exceptions\MultipleModelsWithSameShortName;
use Migrator\Schema\Migrator\Field;
use Migrator\Schema\Migrator\MergeModelFiles;
use Migrator\Schema\Migrator\MigrationFile;
use Migrator\Schema\Migrator\ModelBuilder;
use Migrator\Schema\Migrator\ModelFile;
use Migrator\Schema\Migrator\TableDefinition;
use Migrator\Schema\Parser;
use Migrator\Schema\Schema;

/**
 * Compares the current state and generates migration and creates/updates models files.
 */
class Migrator
{
    const CREATE_TABLE = 'create-table';
    const UPDATE_TABLE = 'update-table';

    public $modelsCreated = [];
    public $modelsUpdated = [];
    public $migrationsCreated = [];
    public $warnings = [];
    private $overwriteModels = false;

    /** @var MySqlBuilder|PostgresBuilder */
    protected $db;

    private $schemaChanges = [];

    /** @var Schema */
    private $schema;

    public function __construct()
    {
        $this->db = \DB::getSchemaBuilder();
        $this->schemaChanges = [];
        $this->appPath = base_path('app/');
    }

    public function migrate($schemaText, $apply = true, $migrate = true)
    {
        Artisan::call('migrate');

        $p = new Parser();
        $this->schema = $p->parse($schemaText);
        if ($apply) {
            $this->applySchema($migrate);
        }
    }

    public function applySchema($migrate = true)
    {
        $this->compareSchema();
        $this->createModels();
        $this->createMigrations();
        if ($migrate) {
            $this->runMigrations();
        }
    }

    /**
     * @throws InverseMethodNotFound
     * @throws MultipleModelsWithSameShortName
     */
    private function compareSchema()
    {
        foreach ($this->schema->getModels() as $model) {
            foreach ($model->getTables() as $table) {
                if (!$this->db->hasTable($table->getName())) {
                    $this->schemaChanges[] = [self::CREATE_TABLE, $table];
                } else {
                    if ($table = $this->filterTableForUpdate($table)) {
                        $this->schemaChanges[] = [self::UPDATE_TABLE, $table];
                    }
                }
            }
        }

        foreach ($this->schema->getModels() as $model) {
            foreach ($model->getImplicitPivotTables() as $table) {
                if (!$this->db->hasTable($table->getName())) {
                    $this->schemaChanges[] = [self::CREATE_TABLE, $table];
                }
                // no else, we don't update implicit table
            }
        }
    }

    protected $createdFileNames = [];

    private function createMigrations()
    {
        foreach ($this->schemaChanges as $change) {
            if ($change[0] == self::CREATE_TABLE || $change[0] == self::UPDATE_TABLE) {
                [$filename, $contents] = MigrationFile::build($change[1], $change[0] == self::UPDATE_TABLE);
                $fullPath = realpath(database_path('migrations/')).DIRECTORY_SEPARATOR.$filename;
                $change['filename'] = $fullPath;
                file_put_contents($fullPath, $contents);
                $this->createdFileNames[] = $change['filename'];
                $this->migrationsCreated[$fullPath] = $contents;
            }
        }
    }

    public function runMigrations()
    {
        //print_r(glob(database_path("migrations/*")));
        Artisan::call('migrate');
    }

    /**
     * @return MySqlBuilder|PostgresBuilder
     */
    public function getDb()
    {
        return $this->db;
    }

    private function createModels()
    {
        foreach ($this->schema->getModels() as $model) {
            $modelFile = ModelFile::build($model);

            $fullPath = $this->schema->getPathForNamespace($model->getNamespace()).$modelFile->getFilename();
            $path = dirname($fullPath);
            if (!file_exists($path)) {
                if (!mkdir($path, 0755, true) && !is_dir($path)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
                }
            }

            $contents = $modelFile->render();
            if (!file_exists($fullPath) || $this->overwriteModels) {
                $this->createdFileNames[] = $fullPath;
                file_put_contents($fullPath, $contents);
                $this->modelsCreated[$fullPath] = $contents;
            } else {
                if (!$this->mergeModelFiles($fullPath, $modelFile)) {
                    $this->warnings[] = "Can't merge `$fullPath`";
                } else {
                    $this->modelsUpdated[$fullPath] = file_get_contents($fullPath);
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getCreatedFileNames(): array
    {
        return $this->createdFileNames;
    }

    /**
     * @return array
     */
    public function getSchemaChanges(): array
    {
        return $this->schemaChanges;
    }

    /**
     * @return Schema
     */
    public function getSchema()
    {
        return $this->schema;
    }

    private function filterTableForUpdate(TableDefinition $table)
    {
        foreach ($table->getFields() as $field) {
            if (!$this->shouldUpdateField($table, $field)) {
                $table->dropField($field->getName());
            }
        }

        foreach ($table->getIndexes() as $indexName => $fields) {
            if ($this->indexExists($table->getName(), $indexName)) {
                $table->dropIndex($indexName);
            }
        }

        foreach ($table->getUnique() as $indexName => $fields) {
            if ($this->uniqueExists($table->getName(), $indexName)) {
                $table->dropUnique($indexName);
            }
        }

        if (count($table->getFields()) > 0 || count($table->getIndexes()) > 0 || count($table->getUnique()) > 0 ||
            count($table->getCommands()) > 0) {
            return $table;
        }
    }

    private function shouldUpdateField(TableDefinition $table, Field $field)
    {
        if (!$this->db->hasColumn($table->getName(), $field->getName())) {
            return true;
        }

        $field->setExists(true);

        if ($field->isCurrentlyNullable() != $field->getNullable()) {
            return true;
        }

        return false;
    }

    public function indexExists($tableName, $indexName)
    {
        $indexes = $this->getSchemaManager()->listTableIndexes($tableName);

        return isset($indexes[$indexName]);
    }

    public function uniqueExists($tableName, $indexName)
    {
        $indexes = $this->getSchemaManager()->listTableIndexes($tableName);

        return isset($indexes[$indexName]);
    }

    /**
     * @return \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    private function getSchemaManager()
    {
        return \DB::getDoctrineSchemaManager();
    }

    public function overwriteModels()
    {
        $this->overwriteModels = true;
    }

    private function mergeModelFiles($fullPath, ModelBuilder $model)
    {
        try {
            (new MergeModelFiles($fullPath, $model))->merge();

            return true;
        } catch (\RuntimeException $e) {
            $this->warnings[] = "$fullPath: ".$e->getMessage();

            return false;
        }
    }
}
