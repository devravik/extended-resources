<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources;

use Devravik\ExtendedResources\Exceptions\NoDefinedFormatsException;
use Devravik\ExtendedResources\Exceptions\NoFormatSelectedException;
use Devravik\ExtendedResources\Formatting\FormatManager;
use Devravik\ExtendedResources\Traits\SetsResponseStatus;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

abstract class ExtendedResource extends JsonResource
{
    use SetsResponseStatus;

    protected FormatManager $formatRegistry;

    protected Collection $transformations;

    public function __construct($resource)
    {
        parent::__construct($resource);

        $this->formatRegistry = new FormatManager($this);
        $this->transformations = new Collection;

        if ($this->formatRegistry->formats()->isEmpty()) {
            throw new NoDefinedFormatsException($this);
        }
    }

    public static function collection($resource): ExtendedAnonymousResourceCollection
    {
        return tap(resolve(ExtendedAnonymousResourceCollection::class, ['resource' => $resource, 'collects' => static::class]), function ($collection) {
            if (property_exists(static::class, 'preserveKeys')) {
                $collection->preserveKeys = (new static([]))->preserveKeys === true;
            }
        });
    }

    public function format(string $name): static
    {
        $this->formatRegistry->select($name);

        return $this;
    }

    public function modify(callable|array $modification): static
    {
        $wrappedModification = ! is_callable($modification)
            ? fn (array $data) => array_merge($data, $modification)
            : $modification;

        $this->transformations->push($wrappedModification);

        return $this;
    }

    public function toArray($request)
    {
        $currentFormat = $this->formatRegistry->current();

        if ($currentFormat === null) {
            throw new NoFormatSelectedException($this);
        }

        $data = $currentFormat->invoke($this, $request);

        return is_array($data)
            ? $this->applyTransformations($data)
            : $data;
    }

    protected function applyTransformations(array $data): array
    {
        return $this->transformations->reduce(
            fn ($carry, $modification) => $modification($carry, $this),
            $data
        );
    }
}
