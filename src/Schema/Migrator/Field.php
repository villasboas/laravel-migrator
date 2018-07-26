<?php

namespace Migrator\Schema\Migrator;

class Field
{
    private $exists = false;
    private $name;
    private $type;
    private $nullable;
    private $typeParam1;
    private $typeParam2;
    private $default;
    private $should = 'create';
    private $tableName;

    public function __construct(
        $tableName,
        $name,
        $type,
        $nullable,
        $typeParam1 = null,
        $typeParam2 = null,
        $default = null
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->nullable = $nullable;
        $this->typeParam1 = $typeParam1;
        $this->typeParam2 = $typeParam2;
        $this->default = $default;
        $this->tableName = $tableName;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    public function getLaravelMigrationCall()
    {
        $params = '';
        if ($this->typeParam1) {
            $params = ', '.$this->typeParam1;
        }
        if ($this->typeParam2) {
            $params .= ', '.$this->typeParam2;
        }
        $type = $this->type;
        if ($type == 'collection') {
            $type = 'text';
        }
        if ($type == 'array') {
            $type = 'text';
        }
        $def = "->{$type}('{$this->name}'{$params})";
        if ($this->nullable && !$this->exists) {
            $def .= '->nullable()';
        } elseif ($this->exists) {
            $def .= '->nullable('.($this->nullable ? 'true' : 'false').')';
        }
        if ($this->default) {
            $def .= '->default('.$this->default.')';
        }
        if ($this->exists) {
            $def .= '->change()';
        }

        return $def;
    }

    public function getLaravelDownCall()
    {
        if ($this->exists) {
            $f2 = new self(
                $this->tableName,
                $this->getName(),
                $this->type,
                $this->isCurrentlyNullable(),
                $this->typeParam1,
                $this->typeParam2,
                $this->default
            );
            $f2->setExists(true);

            return $f2->getLaravelMigrationCall();
        }

        return "->dropColumn('".addslashes($this->getName())."')";
    }

    /**
     * @return string
     */
    public function getShould(): string
    {
        return $this->should;
    }

    /**
     * @param string $should
     */
    public function setShould(string $should): void
    {
        $this->should = $should;
    }

    /**
     * @return mixed
     */
    public function getNullable()
    {
        return $this->nullable;
    }

    public function getNotnull()
    {
        return !$this->nullable;
    }

    public function setExists($bool)
    {
        $this->exists = $bool;
    }

    /**
     * @return bool
     */
    public function isExists(): bool
    {
        return $this->exists;
    }

    public function isCurrentlyNullable()
    {
        $column = $this->getFieldDoctrine();

        return !$column->getNotnull();
    }

    /**
     * @param $tableName
     * @param $fieldName
     *
     * @return \Doctrine\DBAL\Schema\Column
     */
    private function getFieldDoctrine(): \Doctrine\DBAL\Schema\Column
    {
        $tableName = $this->tableName;

        return $this->getSchemaManager()->listTableColumns($tableName)[$this->getName()];
    }

    /**
     * @return \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    private function getSchemaManager()
    {
        return \DB::getDoctrineSchemaManager();
    }
}
