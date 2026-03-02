<?php

declare(strict_types=1);

namespace Devravik\ExtendedResources\Tests\Unit;

use Devravik\ExtendedResources\ExtendedAnonymousResourceCollection;
use Devravik\ExtendedResources\ExtendedResourceCollection;
use Devravik\ExtendedResources\Tests\ExplicitDefaultResource;
use Devravik\ExtendedResources\Tests\ImplicitDefaultResource;
use Devravik\ExtendedResources\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

class ExtendedAnonymousCollectionBehaviorTest extends TestCase
{
    #[DataProvider('anonymousCollectionFormattingProvider')]
    public function test_it_formats_anonymous_collections(
        ExtendedAnonymousResourceCollection $collection,
        array $expectedPayload
    ): void {
        // Act
        $actualPayload = $collection->toArray(request());

        // Assert
        $this->assertSame($expectedPayload, $actualPayload);
    }

    #[DataProvider('anonymousCollectionModificationProvider')]
    public function test_it_allows_modifying_anonymous_collections(
        ExtendedResourceCollection $resource,
        array $expectedPayload,
    ): void {
        // Act
        $actualPayload = $resource->toArray(request());

        // Assert
        $this->assertSame($expectedPayload, $actualPayload);
    }

    public function test_it_allows_custom_status_on_anonymous_collections(): void
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

        $collection = ImplicitDefaultResource::collection([$john, $jane]);

        // Act
        $response = $collection->setResponseStatus(201)->response();

        // Assert
        $this->assertSame(201, $response->getStatusCode());
    }

    // region Data Providers

    public static function anonymousCollectionFormattingProvider(): array
    {
        $alphaUser = new stdClass;
        $alphaUser->id = 1;
        $alphaUser->firstName = 'John';
        $alphaUser->lastName = 'Doe';

        $betaUser = new stdClass;
        $betaUser->id = 2;
        $betaUser->firstName = 'Jane';
        $betaUser->lastName = 'Doe';

        return [
            'uses_resource_implicit_default' => [
                'collection' => ImplicitDefaultResource::collection([$alphaUser, $betaUser]),
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
            'uses_resource_explicit_default' => [
                'collection' => ExplicitDefaultResource::collection([$alphaUser, $betaUser]),
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
            'uses_explicit_named_format' => [
                'collection' => ExplicitDefaultResource::collection([$alphaUser, $betaUser])->format('bar'),
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

    public static function anonymousCollectionModificationProvider(): array
    {
        $alphaUser = new stdClass;
        $alphaUser->id = 1;
        $alphaUser->firstName = 'John';
        $alphaUser->lastName = 'Doe';

        $betaUser = new stdClass;
        $betaUser->id = 2;
        $betaUser->firstName = 'Jane';
        $betaUser->lastName = 'Doe';

        return [
            'array_appends_additional_values' => [
                'resource' => ImplicitDefaultResource::collection([$alphaUser, $betaUser])
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
            'array_overrides_values' => [
                'resource' => ImplicitDefaultResource::collection([$alphaUser, $betaUser])
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
            'closure_adds_fields' => [
                'resource' => ImplicitDefaultResource::collection([$alphaUser, $betaUser])
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
            'closure_updates_fields' => [
                'resource' => ImplicitDefaultResource::collection([$alphaUser, $betaUser])
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
            'closure_replaces_each_item' => [
                'resource' => ImplicitDefaultResource::collection([$alphaUser, $betaUser])
                    ->modify(fn () => ['id' => 1]),
                'expectedPayload' => [
                    ['id' => 1],
                    ['id' => 1],
                ],
            ],
            'closure_reads_underlying_resource' => [
                'resource' => ImplicitDefaultResource::collection([$alphaUser, $betaUser])
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
            'invokable_adds_fields' => [
                'resource' => ImplicitDefaultResource::collection([$alphaUser, $betaUser])
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
            'invokable_changes_fields' => [
                'resource' => ImplicitDefaultResource::collection([$alphaUser, $betaUser])
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
            'invokable_replaces_each_item' => [
                'resource' => ImplicitDefaultResource::collection([$alphaUser, $betaUser])
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
            'invokable_reads_underlying_resource' => [
                'resource' => ImplicitDefaultResource::collection([$alphaUser, $betaUser])
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
                'resource' => ImplicitDefaultResource::collection([$alphaUser, $betaUser])
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
