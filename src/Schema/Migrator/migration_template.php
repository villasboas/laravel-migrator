<?php

/** @var Migrator\Schema\Migrator\TableDefinition $t */
/** @var string $class */
/** @var boolean $isChange */

$commands = '';
if ($t->getCommands()) {
    // commands seem to require separate ::table call
    $commands .= "\n        Schema::table('{$t->getName()}', function (Blueprint \$table) {\n";
    foreach ($t->getCommands() as $command) {
        $commands .= "            \$table{$command->schemaCommand()};\n";
    }
    $commands .= "        });\n";
}

$fields = '';
foreach ($t->getFields() as $field) {
    $fields .= "            \$table{$field->getLaravelMigrationCall()};\n";
}

$timestamps = '';
if (!$isChange) {
    $timestamps = "            \$table->timestamps();\n";
}

$down = '';
if (!$isChange) {
    $down = "        Schema::drop('{$t->getName()}');";
} else {
    $down .= "        Schema::table('{$t->getName()}', function (Blueprint \$table) {\n";
    foreach ($t->getCommands() as $command) {
        $down .= "            \$table{$command->downCall()};\n";
    }
    foreach ($t->getFields() as $field) {
        $down .= "            \$table{$field->getLaravelDownCall()};\n";
    }
    if ($t->getFields()) {
        $down .= "\n";
    }
    foreach ($t->getIndexes() as $name => $fields1) {
        $names = collect($fields1)->map->getQuotedName()->implode(', ');
        $down .= "            \$table->dropIndex('$name');\n";
    }
    foreach ($t->getUnique() as $name => $fields1) {
        $names = collect($fields1)->map->getQuotedName()->implode(', ');
        $down .= "            \$table->dropUnique('$name');\n";
    }

    $down .= "        });\n";
}

$action = $isChange ? 'table' : 'create';

$createIndexes = '';
foreach ($t->getIndexes() as $name => $fields1) {
    if (empty($createIndexes)) {
        $createIndexes .= "\n";
    }
    $names = collect($fields1)->map->getQuotedName()->implode(', ');
    $createIndexes .= "            \$table->index([$names], '$name');\n";
}
foreach ($t->getUnique() as $name => $fields1) {
    if (empty($createIndexes)) {
        $createIndexes .= "\n";
    }
    $names = collect($fields1)->map->getQuotedName()->implode(', ');
    $createIndexes .= "            \$table->unique([$names], '$name');\n";
}

$schemaStart = "";
$schemaEnd = "";

if ("{$fields}{$timestamps}{$createIndexes}" != "") {
    $schemaStart = "        Schema::{$action}('{$t->getName()}', function (Blueprint \$table) {\n";
    $schemaEnd = "\n        });";
}

return "<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class $class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
{$schemaStart}{$fields}{$timestamps}{$createIndexes}{$schemaEnd}{$commands}
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
{$down}
    }
    
}
";
