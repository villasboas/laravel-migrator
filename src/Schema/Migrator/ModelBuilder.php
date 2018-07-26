<?php

namespace Migrator\Schema\Migrator;

use Migrator\Schema\ModelCommand;

/** @var string $name */

/** @var ModelCommand $model */
class ModelBuilder
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var ModelCommand
     */
    private $model;

    /**
     * @var string
     */
    private $filename;

    public function __construct(string $filename, string $name, ModelCommand $model)
    {
        $this->name = $name;
        $this->model = $model;
        $this->filename = $filename;
    }

    public function __toString()
    {
        return $this->render();
    }

    public function render()
    {
        return "<?php\n" .
            "namespace {$this->namespaceStmt()};\n" .
            "\n" .
            $this->use1() . "\n" .
            "\n" .
            "class {$this->name} extends {$this->extendsStmt()}\n{\n" .
            $this->guarded() . $this->primaryKey() . $this->casts() . $this->dates() . "\n" .
            $this->methods() . "\n" .
            "}";
    }

    private function namespaceStmt()
    {
        return $namespace = trim($this->model->getNamespace(), '\\');
    }

    private function use1()
    {
        $use = 'use Illuminate\Database\Eloquent\Model;';
        if ($this->model->extendsPivot()) {
            $use = 'use Illuminate\Database\Eloquent\Relations\Pivot;';
        }

        return $use;
    }

    private function extendsStmt()
    {
        $extends = 'Model';
        if ($this->model->extendsPivot()) {
            $extends = 'Pivot';
        }

        return $extends;
    }

    public function guardedFieldNames()
    {
        return collect($this->model->getGuardedFields())->map->getName()->all();
    }

    private function quote($fields)
    {
        return array_map(function ($i) {
            return "'" . addslashes($i) . "'";
        }, $fields);
    }

    public function guarded($fields = null)
    {
        $guardedFields = join(', ', $this->quote($fields ?? $this->guardedFieldNames()));
        return $guarded = "    protected \$guarded = [$guardedFields];\n";
    }

    private function primaryKey()
    {
        $primaryKey = "";
        if ($this->model->getPrimaryKeyFieldNames() != ['id']) {
            $pk = $this->model->getPrimaryKeyFieldNamesExpectOne("model generation of `{$this->model->getShortName()}` requires simple Primary Key");
            $primaryKey = "    protected \$primaryKey = '$pk';\n";
        }

        return $primaryKey;
    }

    public function castFields()
    {
        $res = [];
        if ($fields = $this->model->getFieldsWithType(['json', 'jsonb', 'collection', 'array'])) {
            $map = ['jsonb' => 'json'];
            foreach ($fields as $field) {
                $type = array_get($map, $field->getFieldType(), $field->getFieldType());
                $res[$field->getName()] = $type;
            }
        }
        return $res;
    }

    public function casts($castFields = null)
    {
        $casts = '';
        $toCast = $castFields ?: $this->castFields();
        if ($toCast) {
            $casts = "\n    protected \$casts = [\n";
            foreach ($toCast as $name => $type) {
                $casts .= "        '$name' => '$type',\n";
            }
            $casts .= "    ];";
        }

        return $casts;
    }

    private function dates()
    {
        $dates = '';
        if ($fields = $this->model->getFieldsWithType(['datetime', 'date', 'dateTimeTz'])) {
            $dates = "\n    protected \$dates = [\n";
            foreach ($fields as $field) {
                $dates .= "        '{$field->getName()}',\n";
            }
            $dates .= "    ];";
        }

        return $dates;
    }

    public function methods()
    {
        return join("\n\n", $this->singleMethods());
    }

    public function singleMethods()
    {
        $methods = [];

        foreach ($this->model->getMethods() as $method) {
            $txt = "    public function {$method->getName()}()\n    {\n";
            $txt .= "        return \$this->{$method->laravelRelationCall()};\n";
            $txt .= "    }";
            $methods[$method->getName()] = $txt;
        }

        return $methods;
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }
}
