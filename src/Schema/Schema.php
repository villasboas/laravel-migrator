<?php

namespace Migrator\Schema;

use Illuminate\Support\Collection;
use Migrator\Schema\Exceptions\MultipleModelsWithSameShortName;
use Traversable;

class Schema
{
    /** @var bool // by default all fields are nullable (like in databases) */
    private $defaultIsNullable = true;

    /** @var bool // by default everything is unguarded (_most_ of the fields shouldn't be protected from mass assignment) */
    private $defaultIsGuarded = false;

    /** @var ModelCommand[] */
    private $models = [];

    /** @var NamespaceCommand[] */
    private $namespaces = [];

    /**
     * @return ModelCommand[]
     */
    public function getModels()
    {
        return $this->models;
    }

    public function addModel($result)
    {
        $this->models [] = $result;
        return $result;
    }

    /**
     * @param $name
     * @return ModelCommand
     * @throws MultipleModelsWithSameShortName
     */
    public function getModel($name)
    {
        $results = [];
        foreach ($this->models as $model) {
            if ($model->is($name)) {
                $results [] = $model;
            }
        }

        if (count($results) == 1) {
            return $results[0];
        }

        if (count($results) == 0) {
            return null;
        }

        throw new MultipleModelsWithSameShortName("Multiple models match $name, use fully qualified name");
    }

    public function getTables()
    {
        /** @var Collection|string[] $tables */
        $tables = [];
        foreach ($this->models as $model) {
            foreach ($model->getTables() as $table) {
                $tables [] = $table->getName();
            }
            foreach ($model->getImplicitPivotTables() as $table) {
                $tables [] = $table->getName();
            }
        }

        return $tables;
    }

    /**
     * @return array|Traversable
     */
    private function findPivotTables()
    {
        $tables = [];

        foreach ($this->models as $model) {
            foreach ($model->getMethods() as $method) {
                if ($method->isManyToMany()) {
                    $pivotTableOrModelName = $method->getPivotTableName();
                    if (!$this->getModel($pivotTableOrModelName)) {
                        $tables [] = $pivotTableOrModelName;
                    }
                }
            }
        }

        return array_unique($tables);
    }

    public function getDefaultIsNullable()
    {
        return $this->defaultIsNullable;
    }

    public function getDefaultIsGuarded()
    {
        return $this->defaultIsGuarded;
    }

    public function updateDefaults(DefaultCommand $d)
    {
        if ($d->hasTag('null') || $d->hasTag('nullable')) {
            $this->defaultIsNullable = true;
        }
        if ($d->hasTag('not null') || $d->hasTag('not nullable')) {
            $this->defaultIsNullable = false;
        }
        if ($d->hasTag('guarded')) {
            $this->defaultIsGuarded = true;
        }
        if ($d->hasTag('unguarded')) {
            $this->defaultIsGuarded = false;
        }
    }

    public function getPathForNamespace($ns)
    {
        return $this->namespaces[NamespaceCommand::normalize($ns)]->getPath();
    }

    public function addNamespace(NamespaceCommand $ns)
    {
        $this->namespaces[$ns->getNamespace()] = $ns;
    }

    /**
     * @param $table
     * @return ModelCommand
     */
    public function getModelByTableName($table)
    {
        // table names are unique
        foreach ($this->getModels() as $model) {
            if ($model->getTableName() == $table) {
                return $model;
            }
        }
    }
}
