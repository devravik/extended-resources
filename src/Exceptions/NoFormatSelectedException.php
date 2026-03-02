<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Exceptions;

use Exception;

class NoFormatSelectedException extends Exception
{
    protected const MESSAGE_FORMAT = '\'%s\' does not have a default format, and no format was specified.';

    public function __construct(object $object)
    {
        $message = vsprintf(static::MESSAGE_FORMAT, [
            $object::class,
        ]);

        parent::__construct($message);
    }
}
