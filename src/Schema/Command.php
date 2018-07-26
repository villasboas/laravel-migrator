<?php

namespace Migrator\Schema;

abstract class Command
{
    /** @var ModelCommand */
    private $model;

    /** @var Schema */
    private $schema;

    /**
     * @return ModelCommand
     */
    public function getModel()
    {
        if (empty($this->model)) {
            $name = isset($this->name) ? $this->name : '(no name)';
            throw new \RuntimeException("Model hasn't been set for {$this->getCommandType()} named '$name'");
        }
        return $this->model;
    }

    /**
     * @param ModelCommand $model
     */
    public function setModel($model): void
    {
        $this->model = $model;
    }

    public function getCommandType()
    {
    }

    /**
     * @return Schema
     */
    public function getSchema(): Schema
    {
        if (empty($this->schema)) {
            $name = isset($this->name) ? $this->name : '(no name)';
            throw new \RuntimeException("Schema hasn't been set for {$this->getCommandType()} named '$name'");
        }
        return $this->schema;
    }

    /**
     * @param Schema $schema
     */
    public function setSchema(Schema $schema): void
    {
        $this->schema = $schema;
    }
}
