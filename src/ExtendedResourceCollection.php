<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources;

use Devravik\ExtendedResources\Exceptions\BaseResourceIsNotExtendedException;
use Devravik\ExtendedResources\Traits\SetsResponseStatus;
use Illuminate\Http\Resources\Json\ResourceCollection as BaseResourceCollection;
use ReflectionClass;

/**
 * @method $this modify(callable|array $modification)
 */
abstract class ExtendedResourceCollection extends BaseResourceCollection
{
    use SetsResponseStatus;

    public function __construct($resource)
    {
        parent::__construct($resource);

        if (! is_a($this->collects, ExtendedResource::class, true)) {
            throw new BaseResourceIsNotExtendedException($this->collects);
        }
    }

    public function __call($method, $parameters): mixed
    {
        if ($this->resourceHasProxyableMethod($method)) {
            $this->collection->map(fn (ExtendedResource $resource) => $resource->{$method}(...$parameters));

            return $this;
        }

        return parent::__call($method, $parameters);
    }

    public function format(string $name): static
    {
        $this->collection->each(fn (ExtendedResource $resource) => $resource->format($name));

        return $this;
    }

    protected function resourceHasProxyableMethod(string $method): bool
    {
        return (new ReflectionClass($this->collects))->hasMethod($method);
    }
}
