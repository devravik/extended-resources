<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Tests\Unit\Enhancements;

use Devravik\ExtendedResources\Enhancements\Except;
use Devravik\ExtendedResources\Enhancements\Traits\AppliesExceptFilter;
use Devravik\ExtendedResources\ExtendedResource;
use Devravik\ExtendedResources\Formatting\Attributes\Format;
use Devravik\ExtendedResources\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ExceptEnhancementTest extends TestCase
{
    #[DataProvider('resourceProvider')]
    public function test_it_applies_except_enhancement_to_resources(
        ExtendedResource $resource,
        array $expectedPayload,
    ): void {
        // Act
        $actualPayload = $resource->toArray(request());

        // Assert
        $this->assertSame($expectedPayload, $actualPayload);
    }

    // region Data Providers

    public static function resourceProvider(): array
    {
        return [
            'applied_manually' => [
                'resource' => (new class(null) extends ExtendedResource
                {
                    #[Format]
                    public function foo(): array
                    {
                        return [
                            'first_name' => 'John',
                            'id' => 1,
                            'last_name' => 'Doe',
                        ];
                    }
                })->modify(new Except(['id'])),
                'expectedPayload' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                ],
            ],
            'applied_via_trait' => [
                'resource' => (new class(null) extends ExtendedResource
                {
                    use AppliesExceptFilter;

                    #[Format]
                    public function foo(): array
                    {
                        return [
                            'first_name' => 'John',
                            'id' => 1,
                            'last_name' => 'Doe',
                        ];
                    }
                })->except('id'),
                'expectedPayload' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                ],
            ],
        ];
    }

    // endregion
}
