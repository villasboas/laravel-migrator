<?php

namespace Migrator\Schema\Exceptions;

class ParseFailure extends \Exception
{
    private $error;

    public function __construct($error)
    {
        $this->error = $error;
    }

    /**
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }
}
