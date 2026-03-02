<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Formatting;

use Devravik\ExtendedResources\Formatting\Attributes\Format;
use Devravik\ExtendedResources\Formatting\Attributes\IsDefault;
use Illuminate\Support\Collection;
use ReflectionAttribute;
use ReflectionMethod;

class FormatDefinition
{
    protected Collection $formatAttributes;

    protected bool $explicitDefaultFlag;

    protected ReflectionMethod $reflectedMethod;

    public function __construct(ReflectionMethod $reflection)
    {
        $this->reflectedMethod = $reflection;

        $this->formatAttributes = (new Collection($this->reflectedMethod->getAttributes(Format::class)))
            ->map(fn (ReflectionAttribute $attribute) => $attribute->newInstance());
        $this->explicitDefaultFlag = ! empty($this->reflectedMethod->getAttributes(IsDefault::class));
    }

    public function invoke(object $object, $request): mixed
    {
        return $this->reflectedMethod->invoke($object, $request);
    }

    public function isExplicitlyDefault(): bool
    {
        return $this->explicitDefaultFlag;
    }

    public function name(): string
    {
        return $this->names()->first();
    }

    public function names(): Collection
    {
        return $this->formatAttributes
            ->map(fn (Format $format) => $format->name() ?? $this->reflectedMethod->getName())
            ->unique();
    }

    public function reflection(): ReflectionMethod
    {
        return $this->reflectedMethod;
    }
}
