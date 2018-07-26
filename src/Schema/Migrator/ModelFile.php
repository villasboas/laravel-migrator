<?php

namespace Migrator\Schema\Migrator;

use Migrator\Schema\ModelCommand;

class ModelFile
{
    public static function build(ModelCommand $model)
    {
        $name = $model->getShortName();
        return new ModelBuilder("$name.php", $name, $model);
    }
}
