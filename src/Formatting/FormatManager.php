<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Formatting;

use Closure;
use Devravik\ExtendedResources\Exceptions\FormatNameCollisionException;
use Devravik\ExtendedResources\Exceptions\InvalidFormatException;
use Devravik\ExtendedResources\Exceptions\MultipleDefaultFormatsException;
use Devravik\ExtendedResources\Formatting\Attributes\Format;
use Illuminate\Support\Collection;
use ReflectionMethod;
use ReflectionObject;

class FormatManager
{
    protected ?string $currentFormatName;

    protected ?FormatDefinition $defaultFormat;

    protected Collection $formatMap;

    protected ReflectionObject $inspectedClass;

    protected object $subject;

    public function __construct(object $subject)
    {
        $this->inspectedClass = new ReflectionObject($subject);
        $this->subject = $subject;

        $definitions = (new Collection($this->inspectedClass->getMethods()))
            ->filter(fn (ReflectionMethod $method) => ! empty($method->getAttributes(Format::class)))
            ->mapInto(FormatDefinition::class);

        $this->formatMap = $this->buildFormatMap($definitions);
        $this->defaultFormat = $this->resolveDefaultFormat($definitions);
        $this->currentFormatName = $this->defaultFormat?->name();
    }

    public function current(): ?FormatDefinition
    {
        return $this->formatMap->get($this->currentFormatName);
    }

    public function currentName(): ?string
    {
        return $this->currentFormatName;
    }

    public function default(): FormatDefinition
    {
        return $this->defaultFormat;
    }

    public function formats(): Collection
    {
        return $this->formatMap;
    }

    public function hasFormat(string $name): bool
    {
        return $this->formatMap->has($name);
    }

    public function lacksFormat(string $name): bool
    {
        return ! $this->hasFormat($name);
    }

    public function select(string $name): static
    {
        if ($this->lacksFormat($name)) {
            throw new InvalidFormatException($this->subject, $name);
        }

        $this->currentFormatName = $name;

        return $this;
    }

    protected function buildFormatMap(Collection $definitions): Collection
    {
        return $definitions
            ->tap(Closure::fromCallable([$this, 'guardAgainstDuplicateFormatNames']))
            ->flatMap(function (FormatDefinition $definition) {
                return $definition->names()->mapWithKeys(
                    fn (string $name) => [$name => $definition]
                );
            });
    }

    protected function resolveDefaultFormat(Collection $definitions): ?FormatDefinition
    {
        if ($definitions->containsOneItem()) {
            return $definitions->first();
        }

        $definitions = $definitions->filter(fn (FormatDefinition $definition) => $definition->isExplicitlyDefault());
        $class = $this->inspectedClass;

        do {
            $default = $definitions
                ->filter(function (FormatDefinition $definition) use ($class) {
                    return $definition->reflection()->getDeclaringClass()->getName() === $class->getName();
                })
                ->tap(Closure::fromCallable([$this, 'guardAgainstMultipleDefaultFormats']))
                ->first();

            $class = $class->getParentClass();
        } while ($class && $default === null);

        return $default;
    }

    protected function guardAgainstDuplicateFormatNames(Collection $formatMethods): void
    {
        $formatMethods->flatMap(fn (FormatDefinition $definition) => $definition->names())
            ->countBy()
            ->filter(fn (int $count) => $count > 1)
            ->whenNotEmpty(fn (Collection $collisions) => throw new FormatNameCollisionException(
                $this->subject,
                $collisions->keys()->first(),
            ));
    }

    protected function guardAgainstMultipleDefaultFormats(Collection $defaultMethods): void
    {
        if ($defaultMethods->count() > 1) {
            throw new MultipleDefaultFormatsException($this->subject);
        }
    }
}
