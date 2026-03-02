<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Exceptions;

use Exception;

class BaseResourceIsNotExtendedException extends Exception
{
    protected const MESSAGE_FORMAT = 'Cannot create an extended collection for \'%s\' because it does not use ExtendedResource.';

    public function __construct(string $resourceName)
    {
        $message = vsprintf(static::MESSAGE_FORMAT, [
            $resourceName,
        ]);

        parent::__construct($message);
    }
}
