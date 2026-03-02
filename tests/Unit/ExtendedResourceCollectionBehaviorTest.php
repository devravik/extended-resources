<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Tests\Unit;

use Devravik\ExtendedResources\Exceptions\BaseResourceIsNotExtendedException;
use Devravik\ExtendedResources\ExtendedResourceCollection;
use Devravik\ExtendedResources\Tests\ExplicitDefaultResource;
use Devravik\ExtendedResources\Tests\ImplicitDefaultCollection;
use Devravik\ExtendedResources\Tests\ImplicitDefaultResource;
use Devravik\ExtendedResources\Tests\TestCase;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

class ExtendedResourceCollectionBehaviorTest extends TestCase
{
    #[DataProvider('formattingCollectionProvider')]
    public function test_it_formats_each_item_in_collection(
        ExtendedResourceCollection $collection,
        array $expectedPayload
    ): void {
        // Act
        $actualPayload = $collection->toArray(request());

        // Assert
        $this->assertSame($expectedPayload, $actualPayload);
    }

    public function test_it_rejects_collections_of_non_extended_resources(): void
    {
        // Expect
        $this->expectException(BaseResourceIsNotExtendedException::class);

        // Act
        new class([]) extends ExtendedResourceCollection
        {
            public $collects = JsonResource::class;
        };
    }

    #[DataProvider('modifiableCollectionProvider')]
    public function test_it_allows_collection_level_modifications(
        ExtendedResourceCollection $resource,
        array $expectedPayload,
    ): void {
        // Act
        $actualPayload = $resource->toArray(request());

        // Assert
        $this->assertSame($expectedPayload, $actualPayload);
    }

    public function test_it_can_customize_collection_response_status(): void
    {
        // Arrange
        $john = new stdClass;
        $john->id = 1;
        $john->firstName = 'John';
        $john->lastName = 'Doe';

        $jane = new stdClass;
        $jane->id = 2;
        $jane->firstName = 'Jane';
        $jane->lastName = 'Doe';

        $collection = new ImplicitDefaultCollection([$john, $jane]);

        // Act
        $response = $collection->setResponseStatus(201)->response();

        // Assert
        $this->assertSame(201, $response->getStatusCode());
    }

    // region Data Providers

    public static function formattingCollectionProvider(): array
    {
        $firstCustomer = new stdClass;
        $firstCustomer->id = 1;
        $firstCustomer->firstName = 'John';
        $firstCustomer->lastName = 'Doe';

        $secondCustomer = new stdClass;
        $secondCustomer->id = 2;
        $secondCustomer->firstName = 'Jane';
        $secondCustomer->lastName = 'Doe';

        return [
            'uses_implicit_default_format' => [
                'collection' => new class([$firstCustomer, $secondCustomer]) extends ExtendedResourceCollection
                {
                    public $collects = ImplicitDefaultResource::class;
                },
                'expectedPayload' => [
                    [
                        'first_name' => 'John',
                        'id' => 1,
                        'last_name' => 'Doe',
                    ],
                    [
                        'first_name' => 'Jane',
                        'id' => 2,
                        'last_name' => 'Doe',
                    ],
                ],
            ],
            'uses_explicit_default_format' => [
                'collection' => new class([$firstCustomer, $secondCustomer]) extends ExtendedResourceCollection
                {
                    public $collects = ExplicitDefaultResource::class;
                },
                'expectedPayload' => [
                    [
                        'first_name' => 'John',
                        'id' => 1,
                        'last_name' => 'Doe',
                    ],
                    [
                        'first_name' => 'Jane',
                        'id' => 2,
                        'last_name' => 'Doe',
                    ],
                ],
            ],
            'uses_specified_named_format' => [
                'collection' => (new class([$firstCustomer, $secondCustomer]) extends ExtendedResourceCollection
                {
                    public $collects = ExplicitDefaultResource::class;
                })->format('bar'),
                'expectedPayload' => [
                    [
                        'id' => 1,
                        'name' => [
                            'first' => 'John',
                            'last' => 'Doe',
                        ],
                    ],
                    [
                        'id' => 2,
                        'name' => [
                            'first' => 'Jane',
                            'last' => 'Doe',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function modifiableCollectionProvider(): array
    {
        $firstCustomer = new stdClass;
        $firstCustomer->id = 1;
        $firstCustomer->firstName = 'John';
        $firstCustomer->lastName = 'Doe';

        $secondCustomer = new stdClass;
        $secondCustomer->id = 2;
        $secondCustomer->firstName = 'Jane';
        $secondCustomer->lastName = 'Doe';

        return [
            'array_merges_extra_fields' => [
                'resource' => ImplicitDefaultCollection::make([$firstCustomer, $secondCustomer])
                    ->modify(['middle_initial' => 'A.']),
                'expectedPayload' => [
                    [
                        'first_name' => 'John',
                        'id' => 1,
                        'last_name' => 'Doe',
                        'middle_initial' => 'A.',
                    ],
                    [
                        'first_name' => 'Jane',
                        'id' => 2,
                        'last_name' => 'Doe',
                        'middle_initial' => 'A.',
                    ],
                ],
            ],
            'array_overwrites_existing_values' => [
                'resource' => ImplicitDefaultCollection::make([$firstCustomer, $secondCustomer])
                    ->modify(['first_name' => 'Jon']),
                'expectedPayload' => [
                    [
                        'first_name' => 'Jon',
                        'id' => 1,
                        'last_name' => 'Doe',
                    ],
                    [
                        'first_name' => 'Jon',
                        'id' => 2,
                        'last_name' => 'Doe',
                    ],
                ],
            ],
            'closure_appends_additional_fields' => [
                'resource' => ImplicitDefaultCollection::make([$firstCustomer, $secondCustomer])
                    ->modify(fn (array $data) => array_merge($data, ['middle_initial' => 'A.'])),
                'expectedPayload' => [
                    [
                        'first_name' => 'John',
                        'id' => 1,
                        'last_name' => 'Doe',
                        'middle_initial' => 'A.',
                    ],
                    [
                        'first_name' => 'Jane',
                        'id' => 2,
                        'last_name' => 'Doe',
                        'middle_initial' => 'A.',
                    ],
                ],
            ],
            'closure_overrides_existing_fields' => [
                'resource' => ImplicitDefaultCollection::make([$firstCustomer, $secondCustomer])
                    ->modify(fn (array $data) => array_merge($data, ['first_name' => 'Jon'])),
                'expectedPayload' => [
                    [
                        'first_name' => 'Jon',
                        'id' => 1,
                        'last_name' => 'Doe',
                    ],
                    [
                        'first_name' => 'Jon',
                        'id' => 2,
                        'last_name' => 'Doe',
                    ],
                ],
            ],
            'closure_replaces_each_item_payload' => [
                'resource' => ImplicitDefaultCollection::make([$firstCustomer, $secondCustomer])
                    ->modify(fn () => ['id' => 1]),
                'expectedPayload' => [
                    ['id' => 1],
                    ['id' => 1],
                ],
            ],
            'closure_can_see_underlying_resource' => [
                'resource' => ImplicitDefaultCollection::make([$firstCustomer, $secondCustomer])
                    ->modify(function (array $data, ImplicitDefaultResource $resource) {
                        $data['id'] = $resource->resource->id * 2;

                        return $data;
                    }),
                'expectedPayload' => [
                    [
                        'first_name' => 'John',
                        'id' => 2,
                        'last_name' => 'Doe',
                    ],
                    [
                        'first_name' => 'Jane',
                        'id' => 4,
                        'last_name' => 'Doe',
                    ],
                ],
            ],
            'invokable_append_fields' => [
                'resource' => ImplicitDefaultCollection::make([$firstCustomer, $secondCustomer])
                    ->modify(new class
                    {
                        public function __invoke(array $data): array
                        {
                            return array_merge($data, ['middle_initial' => 'A.']);
                        }
                    }),
                'expectedPayload' => [
                    [
                        'first_name' => 'John',
                        'id' => 1,
                        'last_name' => 'Doe',
                        'middle_initial' => 'A.',
                    ],
                    [
                        'first_name' => 'Jane',
                        'id' => 2,
                        'last_name' => 'Doe',
                        'middle_initial' => 'A.',
                    ],
                ],
            ],
            'invokable_overrides_fields' => [
                'resource' => ImplicitDefaultCollection::make([$firstCustomer, $secondCustomer])
                    ->modify(new class
                    {
                        public function __invoke(array $data): array
                        {
                            return array_merge($data, ['first_name' => 'Jon']);
                        }
                    }),
                'expectedPayload' => [
                    [
                        'first_name' => 'Jon',
                        'id' => 1,
                        'last_name' => 'Doe',
                    ],
                    [
                        'first_name' => 'Jon',
                        'id' => 2,
                        'last_name' => 'Doe',
                    ],
                ],
            ],
            'invokable_replaces_each_item_payload' => [
                'resource' => ImplicitDefaultCollection::make([$firstCustomer, $secondCustomer])
                    ->modify(new class
                    {
                        public function __invoke(array $data): array
                        {
                            return ['id' => 1];
                        }
                    }),
                'expectedPayload' => [
                    ['id' => 1],
                    ['id' => 1],
                ],
            ],
            'invokable_has_access_to_underlying_resource' => [
                'resource' => ImplicitDefaultCollection::make([$firstCustomer, $secondCustomer])
                    ->modify(new class
                    {
                        public function __invoke(array $data, ImplicitDefaultResource $resource): array
                        {
                            $data['id'] = $resource->resource->id * 2;

                            return $data;
                        }
                    }),
                'expectedPayload' => [
                    [
                        'first_name' => 'John',
                        'id' => 2,
                        'last_name' => 'Doe',
                    ],
                    [
                        'first_name' => 'Jane',
                        'id' => 4,
                        'last_name' => 'Doe',
                    ],
                ],
            ],
            'modifications_are_chainable' => [
                'resource' => ImplicitDefaultCollection::make([$firstCustomer, $secondCustomer])
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
                    [
                        'first_name' => 'Jon',
                        'id' => 2,
                        'last_name' => 'Doe',
                        'middle_initial' => 'A.',
                    ],
                    [
                        'first_name' => 'Jon',
                        'id' => 4,
                        'last_name' => 'Doe',
                        'middle_initial' => 'A.',
                    ],
                ],
            ],
            'modifications_work_with_basic_paginator' => [
                'resource' => ImplicitDefaultCollection::make(new Paginator([$firstCustomer, $secondCustomer], 2))
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
                    [
                        'first_name' => 'Jon',
                        'id' => 2,
                        'last_name' => 'Doe',
                        'middle_initial' => 'A.',
                    ],
                    [
                        'first_name' => 'Jon',
                        'id' => 4,
                        'last_name' => 'Doe',
                        'middle_initial' => 'A.',
                    ],
                ],
            ],
            'modifications_work_with_length_aware_paginator' => [
                'resource' => ImplicitDefaultCollection::make(new LengthAwarePaginator([$firstCustomer, $secondCustomer], 2, 2))
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
                    [
                        'first_name' => 'Jon',
                        'id' => 2,
                        'last_name' => 'Doe',
                        'middle_initial' => 'A.',
                    ],
                    [
                        'first_name' => 'Jon',
                        'id' => 4,
                        'last_name' => 'Doe',
                        'middle_initial' => 'A.',
                    ],
                ],
            ],
        ];
    }

    // endregion
}
