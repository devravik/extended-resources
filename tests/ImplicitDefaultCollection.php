<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Tests;

use Devravik\ExtendedResources\ExtendedResourceCollection;

class ImplicitDefaultCollection extends ExtendedResourceCollection
{
    public $collects = ImplicitDefaultResource::class;
}
