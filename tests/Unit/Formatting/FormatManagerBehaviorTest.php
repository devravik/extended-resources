<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Tests\Unit\Formatting;

use Closure;
use Devravik\ExtendedResources\Exceptions\FormatNameCollisionException;
use Devravik\ExtendedResources\Exceptions\InvalidFormatException;
use Devravik\ExtendedResources\Exceptions\MultipleDefaultFormatsException;
use Devravik\ExtendedResources\Formatting\Attributes\Format;
use Devravik\ExtendedResources\Formatting\Attributes\IsDefault;
use Devravik\ExtendedResources\Formatting\FormatDefinition;
use Devravik\ExtendedResources\Formatting\FormatManager;
use Devravik\ExtendedResources\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class FormatManagerBehaviorTest extends TestCase
{
    public function test_it_discovers_all_format_definitions(): void
    {
        // Arrange
        $subject = new class
        {
            #[Format('bar')]
            public function barFormat() {}

            #[IsDefault, Format, Format('fooAlias')]
            public function foo() {}
        };

        // Act
        $formats = (new FormatManager($subject))->formats();

        // Assert
        $this->assertContainsOnlyInstancesOf(FormatDefinition::class, $formats);
        $this->assertSame(['bar', 'foo', 'fooAlias'], $formats->keys()->all());
    }

    #[DataProvider('formatNameCollisionProvider')]
    public function test_it_prevents_duplicate_format_names(object $subject): void
    {
        // Expect
        $this->expectException(FormatNameCollisionException::class);

        // Act
        new FormatManager($subject);
    }

    #[DataProvider('defaultFormatProvider')]
    public function test_it_identifies_the_default_format(object $subject, string $expectedFormat): void
    {
        // Act
        $manager = new FormatManager($subject);

        // Assert
        $this->assertSame($expectedFormat, $manager->default()->name());
    }

    public function test_it_rejects_multiple_default_formats(): void
    {
        // Expect
        $this->expectException(MultipleDefaultFormatsException::class);

        // Arrange
        $subject = new class
        {
            #[IsDefault, Format]
            public function bar() {}

            #[IsDefault, Format]
            public function foo() {}
        };

        // Act
        new FormatManager($subject);
    }

    #[DataProvider('currentFormatProvider')]
    public function test_it_tracks_the_current_format(Closure $setup, string $expectedFormat): void
    {
        // Act
        /** @var FormatManager $manager */
        $manager = $setup();

        // Assert
        $this->assertSame($expectedFormat, $manager->currentName());
        $this->assertContains($expectedFormat, $manager->current()->names());
    }

    #[DataProvider('formatExistenceProvider')]
    public function test_it_reports_when_a_format_exists(object $subject, string $formatName, bool $expectedResult): void
    {
        // Arrange
        $manager = new FormatManager($subject);

        // Act
        $actualResult = $manager->hasFormat($formatName);

        // Assert
        $this->assertSame($expectedResult, $actualResult);
    }

    #[DataProvider('formatExistenceProvider')]
    public function test_it_reports_when_a_format_is_missing(object $subject, string $formatName, bool $expectedResult): void
    {
        // Arrange
        $manager = new FormatManager($subject);

        // Act
        $actualResult = $manager->lacksFormat($formatName);

        // Assert
        $this->assertSame(! $expectedResult, $actualResult);
    }

    public function test_it_throws_when_selecting_an_unknown_format(): void
    {
        // Expect
        $this->expectException(InvalidFormatException::class);

        // Arrange
        $manager = new FormatManager(new class
        {
            #[Format]
            public function foo() {}
        });

        // Act
        $manager->select('bar');
    }

    // region Data Providers

    public static function currentFormatProvider(): array
    {
        return [
            'implicit_default_is_initial_current' => [
                fn () => new FormatManager(new class
                {
                    #[Format]
                    public function foo() {}
                }),
                'foo',
            ],
            'explicit_default_is_initial_current' => [
                fn () => new FormatManager(new class
                {
                    #[Format]
                    public function bar() {}

                    #[IsDefault, Format]
                    public function foo() {}
                }),
                'foo',
            ],
            'can_select_by_implicit_name' => [
                fn () => (new FormatManager(new class
                {
                    #[Format]
                    public function bar() {}

                    #[IsDefault, Format]
                    public function foo() {}
                }))->select('bar'),
                'bar',
            ],
            'can_select_by_explicit_name' => [
                fn () => (new FormatManager(new class
                {
                    #[Format('foobar')]
                    public function bar() {}

                    #[IsDefault, Format]
                    public function foo() {}
                }))->select('foobar'),
                'foobar',
            ],
            'can_select_by_alias' => [
                fn () => (new FormatManager(new class
                {
                    #[Format, Format('foobar')]
                    public function bar() {}

                    #[IsDefault, Format]
                    public function foo() {}
                }))->select('foobar'),
                'foobar',
            ],
        ];
    }

    public static function defaultFormatProvider(): array
    {
        return [
            'single_candidate_becomes_default' => [
                'subject' => new class
                {
                    #[Format]
                    public function foo() {}
                },
                'expectedFormat' => 'foo',
            ],
            'annotated_default_wins' => [
                'subject' => new class
                {
                    #[Format]
                    public function bar() {}

                    #[IsDefault, Format]
                    public function foo() {}
                },
                'expectedFormat' => 'foo',
            ],
            'default_may_be_inherited_from_parent' => [
                'subject' => new class extends ParentClass {},
                'expectedFormat' => 'foo',
            ],
            'child_can_override_parents_default' => [
                'subject' => new class extends ParentClass
                {
                    #[IsDefault, Format]
                    public function bar() {}
                },
                'expectedFormat' => 'bar',
            ],
        ];
    }

    public static function formatExistenceProvider(): array
    {
        return [
            'implicit_name_is_reported_as_existing' => [
                'subject' => new class
                {
                    #[Format]
                    public function foo() {}
                },
                'formatName' => 'foo',
                'expectedResult' => true,
            ],
            'explicit_name_is_reported_as_existing' => [
                'subject' => new class
                {
                    #[Format('foobar')]
                    public function foo() {}
                },
                'formatName' => 'foobar',
                'expectedResult' => true,
            ],
            'alias_is_reported_as_existing' => [
                'subject' => new class
                {
                    #[Format, Format('foobar')]
                    public function foo() {}
                },
                'formatName' => 'foobar',
                'expectedResult' => true,
            ],
            'implicit_name_missing_when_only_explicit_defined' => [
                'subject' => new class
                {
                    #[Format('foobar')]
                    public function foo() {}
                },
                'formatName' => 'foo',
                'expectedResult' => false,
            ],
            'non_existent_name_is_reported_missing' => [
                'subject' => new class
                {
                    #[Format]
                    public function foo() {}
                },
                'formatName' => 'bar',
                'expectedResult' => false,
            ],
        ];
    }

    public static function formatNameCollisionProvider(): array
    {
        return [
            'throws_when_two_explicit_names_match' => [
                'subject' => new class
                {
                    #[Format('foo')]
                    public function formatOne() {}

                    #[Format('foo')]
                    public function formatTwo() {}
                },
            ],
            'throws_when_implicit_and_explicit_names_collide' => [
                'subject' => new class
                {
                    #[Format]
                    public function foo() {}

                    #[Format('foo')]
                    public function formatTwo() {}
                },
            ],
        ];
    }

    // endregion
}

class ParentClass
{
    #[IsDefault, Format]
    public function foo() {}
}
