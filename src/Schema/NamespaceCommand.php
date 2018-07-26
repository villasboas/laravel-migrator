<?php

namespace Migrator\Schema;

class NamespaceCommand extends Command
{
    public $namespace;
    private $path;

    public function __construct($namespace, $path)
    {
        $this->namespace = self::normalize($namespace);
        $this->path = self::normalizePath($path);
    }

    /**
     * @param $namespace
     *
     * @return string
     */
    public static function normalize($namespace): string
    {
        $namespace = trim($namespace, '\\');

        return '\\'.$namespace.'\\';
    }

    public static function fromString($line)
    {
        $regex = '#^namespace\s+(?P<namespace>\S*)(\s+(?P<path>\S*)\s*)?$#';

        if (!preg_match($regex, $line, $m)) {
            return;
        }

        $ns = self::normalize($m['namespace']);

        if (!isset($m['path'])) {
            if ($ns == '\\App\\') {
                $m['path'] = 'app/';
            }
            if (starts_with($ns, '\\App\\')) {
                $path = preg_replace('/^\\\\App\\\\/', 'app\\', $ns);
                $m['path'] = str_replace('\\', '/', $path);
            } else {
                throw new \RuntimeException("Cannot infer what path should be for namespace {$m['namespace']}, use form `namespace {$m['namespace']} app/Path/Path`");
            }
        }

        $d = new self($m['namespace'], $m['path']);

        return $d;
    }

    private static function normalizePath($path)
    {
        $path = trim($path, DIRECTORY_SEPARATOR);
        $path .= DIRECTORY_SEPARATOR;

        return $path;
    }

    public function getCommandType()
    {
        return 'Namespace';
    }

    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        return $this->path;
    }
}
