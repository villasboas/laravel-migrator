<?php

namespace Migrator\Schema;

use Migrator\Schema\Migrator\Field;

class FieldCommand extends Command
{
    protected $name;
    private $param1;
    private $param2;
    private $fieldType;

    private $isPrimaryKey = false;
    private $isNullable = null;
    private $isGuarded = null;
    private $indexNames = [];
    private $uniqueIndexNames = [];
    private $defaultValue;

    public function __construct($name, $fieldType)
    {
        $this->name = $name;
        $this->fieldType = $fieldType;
    }

    public static function fromString($line, ModelCommand $model)
    {
        $line = ltrim($line);
        $line = preg_replace('#,\s*\r?\n\s+#', ', ', $line);

        // id_field: big_integer PrimaryKey, Index, Unique, Index(idxi), Unique(idxu), NotNull
        $nameRx = '(?P<name>[A-Za-z0-9_]+)';

        $numbersRx = '(?P<param1>\d+(,\s*(?P<param2>\d+))?)';
        $enumRx = '(?P<enum_type>enum)\((?P<enum_param>.*?)\)'; // this is too naive, ) could be inside of parentheses
        $typeRx = "($enumRx|(?P<numbered_type>[A-Za-z_]+)\($numbersRx\)|(?P<type>[A-Za-z_]+))";
        $tagsRx = '(?P<tags>(.*))';
        $regex = "#^{$nameRx}\\:\s*{$typeRx}(\s+$tagsRx)?$#";

        if (!preg_match($regex, $line, $m)) {
            return;
        }

        $fieldType = array_get($m, 'type', '').array_get($m, 'numbered_type', '').array_get($m, 'enum_type', '');

        $d = new self($m['name'], $fieldType);
        $d->setModel($model);
        $d->parseTags(array_get($m, 'tags'));
        if (isset($m['param1'])) {
            $d->setParam1((int) $m['param1']);
        }
        if (isset($m['param2'])) {
            $d->setParam2((int) $m['param2']);
        }
        if (!empty($m['enum_param'])) {
            $d->setParam1($m['enum_param']);
        }

        return $d;
    }

    private function parseTags($tagString)
    {
        if (empty($tagString)) {
            return;
        }

        $indexWithoutNameRx = '/^Index$/';
        $uniqueWithoutNameRx = '/^Unique$/';
        $indexWithNameRx = '/^Index\((?P<name>[a-zA-Z0-9_]+)\)$/';
        $uniqueWithNameRx = '/^Unique\((?P<name>[a-zA-Z0-9_]+)\)$/';
        $defaultRx = '/^Default\((?P<default_value>(".*?"|false|true|[0-9]+|[0-9]+\.[0-9]+))\)$/';

        $primaryKeyRx = '/^PrimaryKey$/';
        $notNullRx = '/^NotNull$/';
        $nullRx = '/^(Null|Nullable)$/';
        $guardedRx = '/^Guarded$/';

        $tags = preg_split('#,\s*#', $tagString); // TODO: probably too naive
        foreach ($tags as $tag) {
            if (preg_match($indexWithoutNameRx, $tag, $m1)) {
                $this->indexNames[] = $this->getModel()->getTableName().'_'.$this->name.'_idx';
            } elseif (preg_match($uniqueWithoutNameRx, $tag, $m1)) {
                $this->uniqueIndexNames[] = $this->getModel()->getTableName().'_'.$this->name.'_unique_idx';
            } elseif (preg_match($indexWithNameRx, $tag, $m1)) {
                $this->indexNames[] = $m1['name'];
            } elseif (preg_match($uniqueWithNameRx, $tag, $m1)) {
                $this->uniqueIndexNames[] = $m1['name'];
            } elseif (preg_match($primaryKeyRx, $tag, $m1)) {
                $this->isPrimaryKey = true;
            } elseif (preg_match($notNullRx, $tag, $m1)) {
                $this->isNullable = false;
            } elseif (preg_match($nullRx, $tag, $m1)) {
                $this->isNullable = true;
            } elseif (preg_match($guardedRx, $tag, $m1)) {
                $this->isGuarded = true;
            } elseif (preg_match($defaultRx, $tag, $m1)) {
                $this->defaultValue = $m1['default_value'];
            } else {
                throw new \RuntimeException("Cannot parse tag for field \"{$this->getModel()->getShortName()}.{$this->name}\": '$tag'");
            }
        }
    }

    public function getCommandType()
    {
        return 'Field';
    }

    /**
     * @return bool
     */
    public function isPrimaryKey(): bool
    {
        return $this->isPrimaryKey;
    }

    /**
     * @param bool $isPrimaryKey
     */
    public function setIsPrimaryKey(bool $isPrimaryKey): void
    {
        $this->isPrimaryKey = $isPrimaryKey;
    }

    /**
     * @return array
     */
    public function getIndexNames(): array
    {
        return $this->indexNames;
    }

    /**
     * @return array
     */
    public function getUniqueIndexNames(): array
    {
        return $this->uniqueIndexNames;
    }

    /**
     * @return null
     */
    public function isNullable()
    {
        return $this->isNullable;
    }

    public function isGuarded()
    {
        return $this->isGuarded;
    }

    public function isGuardedConsideringDefaults()
    {
        $guarded = $this->isGuarded !== null ? $this->isGuarded : $this->getSchema()->getDefaultIsGuarded();

        return $guarded;
    }

    public function getTableDefinitionField()
    {
        $nullable = $this->isNullable !== null ? $this->isNullable : $this->getSchema()->getDefaultIsNullable();

        return new Field(
            $this->getModel()->getTableName(),
            $this->getName(),
            $this->getFieldType(),
            $nullable,
            $this->param1,
            $this->param2,
            $this->defaultValue
        );
    }

    public function getName()
    {
        return $this->name;
    }

    public function getFieldType()
    {
        return $this->fieldType;
    }

    public function setIsNullable($bool)
    {
        $this->isNullable = $bool;
    }

    public function getParam1()
    {
        return $this->param1;
    }

    /**
     * @param mixed $param1
     */
    public function setParam1($param1): void
    {
        $this->param1 = $param1;
    }

    public function getParam2()
    {
        return $this->param2;
    }

    /**
     * @param mixed $param2
     */
    public function setParam2($param2): void
    {
        $this->param2 = $param2;
    }

    /**
     * @return mixed
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @param mixed $defaultValue
     */
    public function setDefaultValue($defaultValue): void
    {
        $this->defaultValue = $defaultValue;
    }

    public function getQuotedName()
    {
        return "'".addslashes($this->name)."'";
    }

    public function humanName()
    {
        return $this->getModel()->getShortName().'.'.$this->name;
    }
}
