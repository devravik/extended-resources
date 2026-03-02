<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Enhancements\Traits;

use Devravik\ExtendedResources\Enhancements\Except;

trait AppliesExceptFilter
{
    public function except(...$keys): static
    {
        return $this->modify(new Except($keys));
    }
}
