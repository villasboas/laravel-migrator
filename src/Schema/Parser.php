<?php

namespace Migrator\Schema;

use Illuminate\Support\Str;
use Migrator\Schema\Exceptions\ParseFailure;
use RuntimeException;

/**
 * Class Parser.
 *
 * @see
 */
class Parser
{
    private $currentNameSpace = '\\App\\';
    private $bufferForComma = '';
    private $baseIndent = null;

    /** @var ModelCommand */
    private $currentModel;

    /**
     * @param $text
     *
     * @return Schema
     */
    public function parse($text)
    {
        $schema = new Schema();
        $schema->addNamespace(new NamespaceCommand($this->currentNameSpace, 'app/'));

        foreach ($this->getLines($text) as $i => $line) {
            $result = $this->parseLine($line);

            if ($result === null) {
                continue;
            }

            if ($result instanceof ParseFailure) {
                $i++;

                throw new RuntimeException("Cannot parse: line $i: ".trim($line));
            }

            if ($result instanceof Command) {
                $result->setModel($this->currentModel);
                $result->setSchema($schema);
            }

            if ($result->getCommandType() == 'Default') {
                $schema->updateDefaults($result);
            }

            if ($result->getCommandType() == 'Namespace') {
                /* @var NamespaceCommand $result */
                $this->currentNameSpace = $result->getNamespace();
                $schema->addNamespace($result);
            }

            if ($result->getCommandType() == 'Model') {
                /** @var ModelCommand $result */
                if ($oldModel = $schema->getModelByTableName($result->getTableName())) {
                    throw new RuntimeException("There are two models with table_name is `{$result->getTableName()}`: {$oldModel->getShortName()} and {$result->getShortName()}. You must have exactly 1 Model for 1 Table.");
                }
                $this->currentModel = $schema->addModel($result);
            }

            if ($result->getCommandType() == 'Field') {
                /* @var FieldCommand $result */
                $this->currentModel->addField($result);
            }

            if ($result->getCommandType() == 'Method') {
                /* @var MethodCommand $result */
                $this->currentModel->addMethod($result);
            }

            if ($result->getCommandType() == 'Command') {
                /* @var CommandCommand $result */
                $this->currentModel->addCommand($result);
            }
        }

        foreach ($schema->getModels() as $model) {
            $model->addImplicitFields();
        }

        return $schema;
    }

    private function getLines($text)
    {
        return preg_split('#\r?\n#', $text);
    }

    /**
     * @param $line
     *
     * @return null|ParseFailure|Command
     */
    private function parseLine($line)
    {
        if ($this->isEmptyLine($line) || $this->isCommentLine($line)) {
            return;
        }

        $indent = $this->getIndent($line);

        $this->seeIndent($indent);

        if ($this->isBaseIndent($indent)) {
            return $this->parseAtBaseIndent($line);
        } else {
            return $this->parseAtBiggerIndent($line);
        }
    }

    private function isEmptyLine($line)
    {
        return trim($line) == '';
    }

    private function isCommentLine($line)
    {
        return Str::startsWith(ltrim($line), '#');
    }

    private function getIndent($line)
    {
        return strlen($line) - strlen(ltrim($line));
    }

    private function seeIndent($indent)
    {
        if ($this->baseIndent === null) {
            $this->baseIndent = $indent;
        }
    }

    private function isBaseIndent($indent)
    {
        return $indent == $this->baseIndent;
    }

    private function parseAtBaseIndent($line)
    {
        $line = trim($line);

        return $this->parseDefault($line) ?: $this->parseNameSpace($line) ?: $this->parseModel($line) ?:
            new ParseFailure($line);
    }

    private function parseAtBiggerIndent($line)
    {
        $line = trim($line);
        $this->bufferForComma .= $line;

        if ($this->endsWithComma($line)) {
            return;
        }

        $line = $this->bufferForComma;
        $this->bufferForComma = '';

        return $this->parseField($line) ?: $this->parseMethod($line) ?: $this->parseCommand($line) ?:
            new ParseFailure($line);
    }

    private function parseDefault($line)
    {
        return DefaultCommand::fromString($line);
    }

    private function parseNameSpace($line)
    {
        return NamespaceCommand::fromString($line);
    }

    public function parseModel($line)
    {
        $m = ModelCommand::fromString($line, $this->currentNameSpace);

        if ($m) {
            $name = $m->getShortName();

            if ($name == str_plural($name)) {
                throw new RuntimeException("You are using plural \"{$name}\" as model name");
            }
        }

        return $m;
    }

    private function endsWithComma($line)
    {
        return preg_match('#,$#', $line);
    }

    /**
     * @param $line
     *
     * @return FieldCommand
     */
    public function parseField($line)
    {
        return FieldCommand::fromString($line, $this->currentModel);
    }

    /**
     * @param $line
     *
     * @return MethodCommand
     */
    public function parseMethod($line)
    {
        return MethodCommand::fromString($line, $this->currentModel);
    }

    /**
     * @param $line
     *
     * @return CommandCommand
     */
    public function parseCommand($line)
    {
        return CommandCommand::fromString($line);
    }
}
