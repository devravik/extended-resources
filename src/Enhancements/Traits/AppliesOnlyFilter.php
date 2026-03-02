<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Enhancements\Traits;

use Devravik\ExtendedResources\Enhancements\Only;

trait AppliesOnlyFilter
{
    public function only(...$keys): static
    {
        return $this->modify(new Only($keys));
    }
}
