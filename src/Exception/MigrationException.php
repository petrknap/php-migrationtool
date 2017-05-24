<?php

namespace PetrKnap\Php\MigrationTool\Exception;

abstract class MigrationException extends \Exception
{
    /**
     * @inheritdoc
     * @param string $code
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        // Hot-fixed "A non well formed numeric value encountered"
        parent::__construct($message, 0, $previous);
        $this->code = $code;
    }
}
