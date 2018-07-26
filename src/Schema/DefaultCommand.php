<?php

namespace Migrator\Schema;

class DefaultCommand extends Command
{
    private $tags;

    public function __construct($tags)
    {
        $this->tags = $tags;
    }

    public static function fromString($line)
    {
        $regex = '#^default (?P<tags>.*)$#i';

        if (!preg_match($regex, $line, $m)) {
            return null;
        }

        $tags = preg_split('#\s*,\s*#', strtolower($m['tags']));
        $d = new self($tags);
        return $d;
    }

    public function getCommandType()
    {
        return 'Default';
    }

    public function hasTag($tag)
    {
        return in_array(strtolower($tag), $this->tags);
    }
}
