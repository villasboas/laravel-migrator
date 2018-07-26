<?php

namespace Migrator\Schema;

class CommandCommand extends Command
{
    /** @var string */
    private $subType;

    /** @var ?string */
    private $arg1;

    /** @var ?string */
    private $arg2;

    public static function fromString($line)
    {
        // RENAME INDEX idx3 TO idx4
        $arg1Rx = '(?P<arg1>[0-9A-Za-z_]+)';
        $arg2Rx = '(?P<arg2>[0-9A-Za-z_]+)';
        $renameFieldRx = "/RENAME\s+FIELD\s+{$arg1Rx}\s+TO\s+{$arg2Rx}/";
        $deleteFieldRx = "/DELETE\s+FIELD\s+{$arg1Rx}/";
        $renameIndexRx = "/RENAME\s+INDEX\s+{$arg1Rx}\s+TO\s+{$arg2Rx}/";
        $deleteIndexRx = "/DELETE\s+INDEX\s+{$arg1Rx}/";

        $d = new self;

        if (preg_match($renameIndexRx, $line, $m)) {
            $d->setSubType('RenameIndex');
            $d->setArg1($m['arg1']);
            $d->setArg2($m['arg2']);
            return $d;
        }

        if (preg_match($deleteIndexRx, $line, $m)) {
            $d->setSubType('DeleteIndex');
            $d->setArg1($m['arg1']);
            return $d;
        }

        if (preg_match($renameFieldRx, $line, $m)) {
            $d->setSubType('RenameField');
            $d->setArg1($m['arg1']);
            $d->setArg2($m['arg2']);
            return $d;
        }

        if (preg_match($deleteFieldRx, $line, $m)) {
            $d->setSubType('DeleteField');
            $d->setArg1($m['arg1']);
            return $d;
        }

        return null;
    }

    public function getCommandType()
    {
        return 'Command';
    }

    /**
     * @return string
     */
    public function getSubType(): string
    {
        return $this->subType;
    }

    /**
     * @param string $subType
     */
    public function setSubType(string $subType): void
    {
        $this->subType = $subType;
    }

    /**
     * @return mixed
     */
    public function getArg1()
    {
        return $this->arg1;
    }

    /**
     * @param mixed $arg1
     */
    public function setArg1($arg1): void
    {
        $this->arg1 = $arg1;
    }

    /**
     * @return mixed
     */
    public function getArg2()
    {
        return $this->arg2;
    }

    /**
     * @param mixed $arg2
     */
    public function setArg2($arg2): void
    {
        $this->arg2 = $arg2;
    }

    public function schemaCommand()
    {
        if ($this->isRenameIndex()) {
            return "->renameIndex('{$this->arg1}', '{$this->arg2}')";
        }
        if ($this->isDeleteIndex()) {
            return "->dropIndex('{$this->arg1}')";
        }
        if ($this->isRenameField()) {
            return "->renameColumn('{$this->arg1}', '{$this->arg2}')";
        }
        if ($this->isDeleteField()) {
            return "->dropColumn('{$this->arg1}')";
        }
    }

    public function downCall()
    {
        if ($this->isRenameIndex()) {
            return "->renameIndex('{$this->arg2}', '{$this->arg1}')";
        }
        if ($this->isDeleteIndex()) {
            return "/* cannot automatically re-create index {$this->arg1} */";
        }
        if ($this->isRenameField()) {
            return "->renameColumn('{$this->arg2}', '{$this->arg1}')";
        }
        if ($this->isDeleteField()) {
            return "/* cannot restore deleted field {$this->arg1} */";
        }
    }

    public function needToRun()
    {
        if ($this->isRenameIndex() || $this->isDeleteIndex()) {
            $details = $this->getSchemaManager()->listTableDetails($this->getModel()->getTableName());
            if ($details->hasIndex($this->arg1)) {
                return true;
            }
        }

        if ($this->isRenameField() || $this->isDeleteField()) {
            $columns = $this->getSchemaManager()->listTableColumns($this->getModel()->getTableName());
            if (isset($columns[$this->arg1])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    private function getSchemaManager()
    {
        return \DB::getDoctrineSchemaManager();
    }

    /**
     * @return bool
     */
    public function isRenameIndex(): bool
    {
        return $this->subType == 'RenameIndex';
    }

    /**
     * @return bool
     */
    public function isDeleteIndex(): bool
    {
        return $this->subType == 'DeleteIndex';
    }

    /**
     * @return bool
     */
    public function isRenameField(): bool
    {
        return $this->subType == 'RenameField';
    }

    /**
     * @return bool
     */
    public function isDeleteField(): bool
    {
        return $this->subType == 'DeleteField';
    }
}
