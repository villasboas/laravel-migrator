<?php

namespace Migrator\Schema;

use Illuminate\Support\Str;
use Migrator\Schema\Exceptions\InverseMethodNotFound;
use Migrator\Schema\Exceptions\MethodNotFound;
use Migrator\Schema\Exceptions\MultipleModelsWithSameShortName;
use Migrator\Schema\Migrator\TableDefinition;
use RuntimeException;

class ModelCommand extends Command
{
    /** @var FieldCommand[] */
    private $fields = [];

    /** @var MethodCommand[] */
    private $methods = [];

    /** @var CommandCommand[] */
    private $commands = [];

    private $shortName;

    private $namespace;

    private $tableName;

    public function __construct($name, $namespace)
    {
        if (Str::contains($name, '\\')) {
            preg_match('#^(?P<namespace>.*?)\\\\(?P<short_name>[^\\\\]*?)$#', $name, $m);
            $this->name = $this->shortName = $m['short_name'];
            $this->namespace = $m['namespace'];
            $this->fullName = $name;
        } else {
            $this->shortName = $name;
            $this->namespace = $namespace;
            $this->fullName = $namespace.$name;
        }
        $this->tableName = str_plural(snake_case($this->shortName));
    }

    public static function fromString($line, $namespace)
    {
        $tableRx = '(\s*\((?P<table>[A-Za-z0-9_]+)\))?';
        $nameRx = '(?P<name>[A-Za-z0-9\\\\-]+)';
        $regex = "#^$nameRx$tableRx\$#";

        if (!preg_match($regex, $line, $m)) {
            return;
        }

        $d = new self($m['name'], $namespace);
        if (isset($m['table'])) {
            $d->tableName = $m['table'];
        }

        return $d;
    }

    public function getCommandType()
    {
        return 'Model';
    }

    public function addMethod(MethodCommand $method)
    {
        $this->methods[] = $method;
    }

    public function addImplicitFields()
    {
        $this->addImplicitFieldFromPrimaryKey();

        foreach ($this->methods as $method) {
            $this->addImplicitFieldFromVia($method);
            $this->addImplicitFieldFromJoin($method);
            $this->addImplicitFieldFromPolymorphic($method);
            $this->addImplicitFieldFromBelongsTo($method);
        }
    }

    private function addImplicitFieldFromPrimaryKey()
    {
        if (count($this->getPrimaryKeyFieldNames()) == 0) {
            $this->addImplicitField('id', 'increments', true, true);
        }
    }

    private function addImplicitFieldFromVia(MethodCommand $method)
    {
        if ($method->viaCreatesField()) {
            $this->addImplicitField($method->getVia(), 'integer');
        }
    }

    private function addImplicitFieldFromJoin(MethodCommand $method)
    {
        if ($field = $method->joinCreatesField()) {
            $this->addImplicitField($field, 'integer');
        }
    }

    private function addImplicitFieldFromPolymorphic(MethodCommand $method)
    {
        if ($method->isPolymorphic()) {
            $this->addImplicitField($method->getName().'_id', 'integer');
            $this->addImplicitField($method->getName().'_type', 'string');
        }
    }

    private function addImplicitFieldFromBelongsTo(MethodCommand $method)
    {
        if ($method->isBelongsTo()) {
            $this->addImplicitField($method->belongsToFieldName(), 'integer');
        }
    }

    /**
     * @return string[]
     */
    public function getPrimaryKeyFieldNames()
    {
        $names = [];
        foreach ($this->getFields() as $field) {
            if ($field->isPrimaryKey()) {
                $names[] = $field->getName();
            }
        }

        return $names;
    }

    /**
     * @param MethodCommand $method
     * @param $name
     * @param $fieldType
     */
    private function addImplicitField($name, $fieldType, $prepend = false, $isPrimary = false): void
    {
        if (!$this->hasField($name)) {
            $implicitField = new FieldCommand($name, $fieldType);
            $implicitField->setModel($this);
            $implicitField->setSchema($this->getSchema());
            if ($isPrimary) {
                $implicitField->setIsPrimaryKey(true);
                $implicitField->setIsNullable(false);
            }
            $this->addField($implicitField, $prepend);
        }
    }

    /**
     * @return FieldCommand[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    public function hasField($name)
    {
        foreach ($this->fields as $field) {
            if ($field->getName() == $name) {
                return true;
            }
        }

        return false;
    }

    public function addField(FieldCommand $command, $prepend = false)
    {
        if ($prepend) {
            $this->fields = array_merge([$command], $this->fields);
        } else {
            $this->fields[] = $command;
        }
    }

    public function addCommand(CommandCommand $command)
    {
        $this->commands[] = $command;
    }

    public function is($name)
    {
        return $this->shortName == $name || $this->fullName == $name;
    }

    public function getMethod($name)
    {
        foreach ($this->methods as $method) {
            if ($method->getName() == $name) {
                return $method;
            }
        }

        throw new MethodNotFound("Method \"$name\" in the model \"{$this->shortName}\" not found");
    }

    public function getFieldsNames()
    {
        return collect($this->fields)->map->getName()->all();
    }

    public function getCommands()
    {
        return $this->commands;
    }

    public function getField($name)
    {
        foreach ($this->fields as $field) {
            if ($field->getName() == $name) {
                return $field;
            }
        }

        throw new RuntimeException("Field \"$name\" in the model \"{$this->shortName}\" not found");
    }

    /**
     * @param $modelShortName
     *
     * @return MethodCommand[]
     */
    public function findMethodsByReturnType($modelShortName)
    {
        $result = [];
        foreach ($this->methods as $method) {
            if ($method->canReturn($modelShortName)) {
                $result[] = $method;
            }
        }

        return $result;
    }

    /**
     * @return TableDefinition[]
     */
    public function getTables()
    {
        $result = [];

        $t = new TableDefinition($this->getTableName());
        foreach ($this->getCommands() as $command) {
            if ($command->needToRun()) {
                $t->addCommand($command);
            }
        }

        foreach ($this->getFields() as $field) {
            if (!$this->isThereRenameFieldCommandPreventingThisField($field)) {
                $t->addField($field->getTableDefinitionField());
            }
        }

        foreach ($this->getIndexes() as $name => $fields) {
            $t->addIndex($name, $fields);
        }

        foreach ($this->getUnique() as $name => $fields) {
            $t->addUnique($name, $fields);
        }

        $result[] = $t;

        return $result;
    }

    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @throws InverseMethodNotFound
     * @throws MultipleModelsWithSameShortName
     *
     * @return TableDefinition[]
     */
    public function getImplicitPivotTables()
    {
        $result = [];

        foreach ($this->getMethods() as $method) {
            try {
                if ($method->isManyToMany() && $method->isManyToManyFirst() && !$method->explicitManyToManyModel()) {
                    $t = new TableDefinition($method->getPivotTableName());
                    foreach ($method->getPivotTableDefinitionFields() as $field) {
                        $t->addField($field);
                    }
                    $result[] = $t;
                }
            } catch (InverseMethodNotFound $e) {
                // definitely not many to many
            }
        }

        return $result;
    }

    /**
     * @return MethodCommand[]
     */
    public function getMethods()
    {
        return $this->methods;
    }

    /**
     * @return mixed
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param $but
     *
     * @return string
     */
    public function getPrimaryKeyFieldNamesExpectOne($but)
    {
        $pks = $this->getPrimaryKeyFieldNames();
        if (count($pks) == 0) {
            throw new RuntimeException("Model `{$this->getShortName()}` has no primary key, but $but");
        }
        if (count($pks) != 1) {
            $join = implode(',', $pks);

            throw new RuntimeException("Model `{$this->getShortName()}` has a complex primary key ($join), $but");
        }

        return $pks[0];
    }

    /**
     * @return mixed
     */
    public function getShortName()
    {
        return $this->shortName;
    }

    public function extendsPivot()
    {
        foreach ($this->getSchema()->getModels() as $model) {
            foreach ($model->methods as $method) {
                if ($method->isManyToMany() && $method->getPivotTableName() == $this->getTableName()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $type string|string[]
     *
     * @return FieldCommand[]
     */
    public function getFieldsWithType($type)
    {
        if (!is_array($type)) {
            $type = [$type];
        }

        $results = [];
        foreach ($this->fields as $field) {
            if (in_array($field->getFieldType(), $type)) {
                $results[] = $field;
            }
        }

        return $results;
    }

    private function getIndexes()
    {
        $results = [];
        foreach ($this->getFields() as $field) {
            foreach ($field->getIndexNames() as $indexName) {
                if (!$this->isThereRenameIndexCommandPreventingThisIndex($indexName, $field)) {
                    if (!isset($results[$indexName])) {
                        $results[$indexName] = [];
                    }
                    $results[$indexName][] = $field;
                }
            }
        }

        return $results;
    }

    private function getUnique()
    {
        $results = [];
        foreach ($this->getFields() as $field) {
            foreach ($field->getUniqueIndexNames() as $indexName) {
                if (!isset($results[$indexName])) {
                    $results[$indexName] = [];
                }
                $results[$indexName][] = $field;
            }
        }

        return $results;
    }

    /**
     * @param $indexName
     * @param $field
     *
     * @return bool
     */
    private function isThereRenameIndexCommandPreventingThisIndex($indexName, $field): bool
    {
        foreach ($this->getCommands() as $command) {
            if ($command->isRenameIndex() && $command->getArg2() == $indexName) {
                // we will rename some index into $indexName, so it doesn't make sense to create it
                return true;
            }
            if ($command->isRenameIndex() && $command->getArg1() == $indexName) {
                // we will rename $indexName to other name.. probably user forgot
                throw new RuntimeException("You have a `RENAME INDEX $indexName` command and you also try to ".
                    "create Index($indexName) on a field `{$field->humanName()}`. You probably want to ".
                    "update the name of index to Index({$command->getArg2()})");
            }
            if ($command->isDeleteIndex() && $command->getArg1() == $indexName) {
                // we will delete $indexName, so it's an error to try to create it
                throw new RuntimeException("You have a `DELETE INDEX $indexName` command and you also try to ".
                    "create Index($indexName) on a field `{$field->humanName()}`. You probably want to ".
                    'update delete that.');
            }
        }

        return false;
    }

    /**
     * @param $indexName
     * @param $field
     *
     * @return bool
     */
    private function isThereRenameFieldCommandPreventingThisField(FieldCommand $field): bool
    {
        foreach ($this->getCommands() as $command) {
            if ($command->isRenameField() && $command->getArg2() == $field->getName()) {
                // we will rename some field into this name, so it doesn't make sense to create it
                return true;
            }
            if ($command->isRenameField() && $command->getArg1() == $field->getName()) {
                throw new RuntimeException("You have a `RENAME FIELD {$field->getName()}` command and you also try to ".
                    "create the same field `{$field->getName()}` (in {$field->getModel()->getShortName()}). ".
                    "You probably want to change the field name to `{$command->getArg2()}`");
            }
        }

        return false;
    }

    /**
     * @return FieldCommand[]
     */
    public function getGuardedFields()
    {
        $result = [];
        foreach ($this->getFields() as $field) {
            if ($field->isGuardedConsideringDefaults()) {
                $result[] = $field;
            }
        }

        return $result;
    }
}
