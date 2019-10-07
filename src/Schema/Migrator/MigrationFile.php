<?php

namespace Migrator\Schema\Migrator;

use Illuminate\Support\Str;

class MigrationFile
{
    private static $migrationNumber = 0;

    public static function build(TableDefinition $t, $isChange = false)
    {
        $classes = self::getMigrationClasses();

        $n = 0;
        do {
            $class = 'Create' . Str::studly($t->getName()) . 'Table' . ($n > 0 ? "{$n}" : '');
            if ($isChange) {
                $class = 'Update' . Str::studly($t->getName()) . 'Table' . ($n > 0 ? "{$n}" : '');
            }
            $n++;
        } while (isset($classes[$class]) || class_exists($class) || self::classFileExists($class));

        $filename = sprintf('%s%03d_%s.php', date('Y_m_d_His'), self::$migrationNumber, Str::snake($class));
        $contents = require __DIR__.'/migration_template.php';
        self::$migrationNumber++;

        return [$filename, $contents];
    }

    /**
     * @param $class
     *
     * @return bool
     */
    public static function classFileExists($class): bool
    {
        return count(glob(database_path('migrations/*_' . Str::snake($class) . '.php'))) > 0;
    }

    /**
     * @return array
     */
    public static function getMigrationClasses(): array
    {
        $classes = [];
        $m = [];
        foreach (glob(database_path('migrations/*_*.php')) as $file) {
            if (preg_match('/class\s*(.*?)\s/', file_get_contents($file), $m)) {
                $classes[$m[1]] = true;
            }
        }

        return $classes;
    }
}
