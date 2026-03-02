<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Tests;

use Devravik\ExtendedResources\ExtendedResource;
use Devravik\ExtendedResources\Formatting\Attributes\Format;
use Devravik\ExtendedResources\Formatting\Attributes\IsDefault;

class ExplicitDefaultResource extends ExtendedResource
{
    #[Format]
    public function bar(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => [
                'first' => $this->resource->firstName,
                'last' => $this->resource->lastName,
            ],
        ];
    }

    #[IsDefault, Format]
    public function foo(): array
    {
        return [
            'first_name' => $this->resource->firstName,
            'id' => $this->resource->id,
            'last_name' => $this->resource->lastName,
        ];
    }
}
