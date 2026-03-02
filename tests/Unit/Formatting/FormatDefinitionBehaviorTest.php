<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Tests\Unit\Formatting;

use Closure;
use Devravik\ExtendedResources\Formatting\Attributes\Format;
use Devravik\ExtendedResources\Formatting\Attributes\IsDefault;
use Devravik\ExtendedResources\Formatting\FormatDefinition;
use Devravik\ExtendedResources\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

class FormatDefinitionBehaviorTest extends TestCase
{
    #[DataProvider('nameDetectionProvider')]
    public function test_it_resolves_primary_format_name(ReflectionMethod $method, Closure $assertions): void
    {
        // Act
        $definition = new FormatDefinition($method);

        // Assert
        $assertions($definition);
    }

    #[DataProvider('defaultDetectionProvider')]
    public function test_it_detects_explicit_default_flag(ReflectionMethod $method, bool $expected): void
    {
        // Arrange
        $definition = new FormatDefinition($method);

        // Act
        $actual = $definition->isExplicitlyDefault();

        // Assert
        $this->assertSame($expected, $actual);
    }

    // region Data Providers

    public static function defaultDetectionProvider(): array
    {
        $subject = new class
        {
            #[Format('bar')]
            public function barFormat() {}

            #[IsDefault, Format, Format('fooAlias')]
            public function foo() {}
        };

        return [
            'default' => [
                'method' => new ReflectionMethod($subject, 'foo'),
                'expected' => true,
            ],
            'non-default' => [
                'method' => new ReflectionMethod($subject, 'barFormat'),
                'expected' => false,
            ],
        ];
    }

    public static function nameDetectionProvider(): array
    {
        $subject = new class
        {
            #[Format('bar')]
            public function barFormat() {}

            #[Format, Format('fooAlias')]
            public function foo() {}
        };

        return [
            'implicit_name_uses_method_name' => [
                'method' => new ReflectionMethod($subject, 'foo'),
                'assertions' => function (FormatDefinition $definition) {
                    static::assertSame('foo', $definition->name());
                },
            ],
            'explicit_name_uses_attribute_value' => [
                'method' => new ReflectionMethod($subject, 'barFormat'),
                'assertions' => function (FormatDefinition $definition) {
                    static::assertSame('bar', $definition->name());
                },
            ],
            'aliases_are_included_in_name_list' => [
                'method' => new ReflectionMethod($subject, 'foo'),
                'assertions' => function (FormatDefinition $definition) {
                    static::assertContains('fooAlias', $definition->names());
                },
            ],
        ];
    }

    // endregion
}
