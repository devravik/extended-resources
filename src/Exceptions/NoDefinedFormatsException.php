<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Exceptions;

use Exception;

class NoDefinedFormatsException extends Exception
{
    protected const MESSAGE_FORMAT = '\'%s\' has no defined formats.';

    public function __construct(object $object)
    {
        $message = vsprintf(static::MESSAGE_FORMAT, [
            $object::class,
        ]);

        parent::__construct($message);
    }
}
