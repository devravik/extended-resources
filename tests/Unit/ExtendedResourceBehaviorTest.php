<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Tests\Unit;

use Devravik\ExtendedResources\Exceptions\NoDefinedFormatsException;
use Devravik\ExtendedResources\Exceptions\NoFormatSelectedException;
use Devravik\ExtendedResources\ExtendedResource;
use Devravik\ExtendedResources\Formatting\Attributes\Format;
use Devravik\ExtendedResources\Formatting\Attributes\IsDefault;
use Devravik\ExtendedResources\Tests\ImplicitDefaultResource;
use Devravik\ExtendedResources\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

class ExtendedResourceBehaviorTest extends TestCase
{
    #[DataProvider('formattingScenariosProvider')]
    public function test_it_formats_resource_output(ExtendedResource $resource, array $expectedPayload): void
    {
        // Act
        $actualPayload = $resource->toArray(request());

        // Assert
        $this->assertSame($expectedPayload, $actualPayload);
    }

    public function test_it_throws_when_no_formats_are_defined(): void
    {
        // Expect
        $this->expectException(NoDefinedFormatsException::class);

        // Act
        new class(null) extends ExtendedResource {};
    }

    public function test_it_throws_when_no_format_is_selected(): void
    {
        // Expect
        $this->expectException(NoFormatSelectedException::class);

        // Act
        (new class(null) extends ExtendedResource
        {
            #[Format]
            public function bar() {}

            #[Format]
            public function foo() {}
        })->toArray(request());
    }

    #[DataProvider('modificationScenariosProvider')]
    public function test_it_allows_runtime_modifications(ExtendedResource $resource, array $expectedPayload): void
    {
        // Act
        $actualPayload = $resource->toArray(request());

        // Assert
        $this->assertSame($expectedPayload, $actualPayload);
    }

    public function test_it_can_customize_response_status_code(): void
    {
        // Arrange
        $resource = (new class(null) extends ExtendedResource
        {
            #[Format]
            public function foo() {}
        });

        // Act
        $response = $resource->setResponseStatus(201)->response();

        // Assert
        $this->assertSame(201, $response->getStatusCode());
    }

    // region Data Providers

    public static function formattingScenariosProvider(): array
    {
        return [
            'uses_single_available_format_by_default' => [
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
                }),
                'expectedPayload' => [
                    'first_name' => 'John',
                    'id' => 1,
                    'last_name' => 'Doe',
                ],
            ],
            'uses_explicit_default_format_when_marked' => [
                'resource' => (new class(null) extends ExtendedResource
                {
                    #[Format]
                    public function bar(): array
                    {
                        return [
                            'first_name' => 'John',
                            'id' => 1,
                            'last_name' => 'Doe',
                        ];
                    }

                    #[IsDefault, Format]
                    public function foo(): array
                    {
                        return [
                            'id' => 1,
                            'name' => [
                                'first' => 'John',
                                'last' => 'Doe',
                            ],
                        ];
                    }
                }),
                'expectedPayload' => [
                    'id' => 1,
                    'name' => [
                        'first' => 'John',
                        'last' => 'Doe',
                    ],
                ],
            ],
            'uses_specified_format_when_requested' => [
                'resource' => (new class(null) extends ExtendedResource
                {
                    #[Format]
                    public function bar(): array
                    {
                        return [
                            'first_name' => 'John',
                            'id' => 1,
                            'last_name' => 'Doe',
                        ];
                    }

                    #[IsDefault, Format]
                    public function foo(): array
                    {
                        return [
                            'id' => 1,
                            'name' => [
                                'first' => 'John',
                                'last' => 'Doe',
                            ],
                        ];
                    }
                })->format('bar'),
                'expectedPayload' => [
                    'first_name' => 'John',
                    'id' => 1,
                    'last_name' => 'Doe',
                ],
            ],
        ];
    }

    public static function modificationScenariosProvider(): array
    {
        $primaryUser = new stdClass;
        $primaryUser->id = 1;
        $primaryUser->firstName = 'John';
        $primaryUser->lastName = 'Doe';

        return [
            'array_merges_additional_data' => [
                'resource' => ImplicitDefaultResource::make($primaryUser)
                    ->modify(['middle_initial' => 'A.']),
                'expectedPayload' => [
                    'first_name' => 'John',
                    'id' => 1,
                    'last_name' => 'Doe',
                    'middle_initial' => 'A.',
                ],
            ],
            'array_overrides_existing_keys' => [
                'resource' => ImplicitDefaultResource::make($primaryUser)
                    ->modify(['first_name' => 'Jon']),
                'expectedPayload' => [
                    'first_name' => 'Jon',
                    'id' => 1,
                    'last_name' => 'Doe',
                ],
            ],
            'closure_can_append_fields' => [
                'resource' => ImplicitDefaultResource::make($primaryUser)
                    ->modify(fn (array $data) => array_merge($data, ['middle_initial' => 'A.'])),
                'expectedPayload' => [
                    'first_name' => 'John',
                    'id' => 1,
                    'last_name' => 'Doe',
                    'middle_initial' => 'A.',
                ],
            ],
            'closure_can_override_fields' => [
                'resource' => ImplicitDefaultResource::make($primaryUser)
                    ->modify(fn (array $data) => array_merge($data, ['first_name' => 'Jon'])),
                'expectedPayload' => [
                    'first_name' => 'Jon',
                    'id' => 1,
                    'last_name' => 'Doe',
                ],
            ],
            'closure_can_replace_entire_payload' => [
                'resource' => ImplicitDefaultResource::make($primaryUser)
                    ->modify(fn () => ['id' => 1]),
                'expectedPayload' => ['id' => 1],
            ],
            'closure_has_access_to_resource_instance' => [
                'resource' => ImplicitDefaultResource::make($primaryUser)
                    ->modify(function (array $data, ImplicitDefaultResource $resource) {
                        $data['id'] = $resource->resource->id * 2;

                        return $data;
                    }),
                'expectedPayload' => [
                    'first_name' => 'John',
                    'id' => 2,
                    'last_name' => 'Doe',
                ],
            ],
            'invokable_object_can_append_fields' => [
                'resource' => ImplicitDefaultResource::make($primaryUser)
                    ->modify(new class
                    {
                        public function __invoke(array $data): array
                        {
                            return array_merge($data, ['middle_initial' => 'A.']);
                        }
                    }),
                'expectedPayload' => [
                    'first_name' => 'John',
                    'id' => 1,
                    'last_name' => 'Doe',
                    'middle_initial' => 'A.',
                ],
            ],
            'invokable_object_can_override_fields' => [
                'resource' => ImplicitDefaultResource::make($primaryUser)
                    ->modify(new class
                    {
                        public function __invoke(array $data): array
                        {
                            return array_merge($data, ['first_name' => 'Jon']);
                        }
                    }),
                'expectedPayload' => [
                    'first_name' => 'Jon',
                    'id' => 1,
                    'last_name' => 'Doe',
                ],
            ],
            'invokable_object_can_replace_payload' => [
                'resource' => ImplicitDefaultResource::make($primaryUser)
                    ->modify(new class
                    {
                        public function __invoke(array $data): array
                        {
                            return ['id' => 1];
                        }
                    }),
                'expectedPayload' => ['id' => 1],
            ],
            'invokable_object_has_access_to_resource_instance' => [
                'resource' => ImplicitDefaultResource::make($primaryUser)
                    ->modify(new class
                    {
                        public function __invoke(array $data, ImplicitDefaultResource $resource): array
                        {
                            $data['id'] = $resource->resource->id * 2;

                            return $data;
                        }
                    }),
                'expectedPayload' => [
                    'first_name' => 'John',
                    'id' => 2,
                    'last_name' => 'Doe',
                ],
            ],
            'modifications_can_be_chained' => [
                'resource' => ImplicitDefaultResource::make($primaryUser)
                    ->modify(['middle_initial' => 'A.'])
                    ->modify(function (array $data): array {
                        $data['first_name'] = 'Jon';

                        return $data;
                    })
                    ->modify(new class
                    {
                        public function __invoke(array $data, ImplicitDefaultResource $resource): array
                        {
                            $data['id'] = $resource->resource->id * 2;

                            return $data;
                        }
                    }),
                'expectedPayload' => [
                    'first_name' => 'Jon',
                    'id' => 2,
                    'last_name' => 'Doe',
                    'middle_initial' => 'A.',
                ],
            ],
        ];
    }

    // endregion
}
