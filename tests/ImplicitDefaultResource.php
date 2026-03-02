<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Tests;

use Devravik\ExtendedResources\ExtendedResource;
use Devravik\ExtendedResources\Formatting\Attributes\Format;

class ImplicitDefaultResource extends ExtendedResource
{
    #[Format]
    public function foo(): array
    {
        return [
            'first_name' => $this->resource->firstName,
            'id' => $this->resource->id,
            'last_name' => $this->resource->lastName,
        ];
    }
}
