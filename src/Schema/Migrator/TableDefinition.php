<?php

namespace Migrator\Schema\Migrator;

use Migrator\Schema\CommandCommand;

class TableDefinition
{
    /** @var Field[] */
    private $fields;
    private $indexes = [];
    private $unique = [];
    private $name;

    /** @var CommandCommand[] */
    private $commands = [];

    /**
     * TableDefinition constructor.
     */
    public function __construct($table)
    {
        $this->fields = [];
        $this->name = $table;
    }

    public function addField(Field $field)
    {
        $this->fields[] = $field;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Field[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    public function dropField($name)
    {
        foreach ($this->fields as $i => $field) {
            if ($field->getName() == $name) {
                unset($this->fields[$i]);
            }
        }
    }

    public function dropIndex($name)
    {
        unset($this->indexes[$name]);
    }

    public function addIndex($name, $fields)
    {
        $this->indexes[$name] = $fields;
    }

    public function dropUnique($name)
    {
        unset($this->unique[$name]);
    }

    public function addUnique($name, $fields)
    {
        $this->unique[$name] = $fields;
    }

    /**
     * @return array
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    public function getUnique()
    {
        return $this->unique;
    }

    /**
     * @return CommandCommand[]
     */
    public function getCommands()
    {
        return $this->commands;
    }

    public function addCommand(CommandCommand $command)
    {
        $this->commands[] = $command;
    }

    /**
     * @param array $commands
     */
    public function setCommands(array $commands): void
    {
        $this->commands = $commands;
    }
}
