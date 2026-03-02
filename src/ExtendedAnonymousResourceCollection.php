<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources;

class ExtendedAnonymousResourceCollection extends ExtendedResourceCollection
{
    public $collects;

    public function __construct($resource, $collects)
    {
        $this->collects = $collects;

        parent::__construct($resource);
    }
}
