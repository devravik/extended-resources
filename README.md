## Laravel Extended Resources

[![Latest Version on Packagist](https://img.shields.io/packagist/v/devravik/extended-resources.svg?style=flat-square)](https://packagist.org/packages/devravik/extended-resources)
[![Total Downloads](https://img.shields.io/packagist/dt/devravik/extended-resources.svg?style=flat-square)](https://packagist.org/packages/devravik/extended-resources)
[![Tests](https://img.shields.io/github/actions/workflow/status/devravik/extended-resources/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/devravik/extended-resources/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/devravik/extended-resources.svg?style=flat-square)](https://packagist.org/packages/devravik/extended-resources)
[![License](https://img.shields.io/packagist/l/devravik/extended-resources.svg?style=flat-square)](https://packagist.org/packages/devravik/extended-resources)

DevRavik Extended Resources is a small but powerful extension around Laravel API resources that lets you:

- Define **multiple named formats** for the same resource using PHP 8 attributes.
- Apply **on-the-fly modifications** to the serialized data (array merges, closures, invokable objects).
- Use **convenience enhancements** like `only()` and `except()` without building custom transformers.
- Adjust the **HTTP status code** directly from the resource.

It is designed to feel native to Laravel while giving you more control over how resources are shaped and delivered.

---

## Requirements

| Dependency | Version |
|-----------|---------|
| PHP       | `^8.1 \| ^8.2 \| ^8.3 \| ^8.4` |
| Laravel   | `^10.0 \| ^11.0 \| ^12.0`      |

---

## Installation

Install via Composer:

```bash
composer require devravik/extended-resources
```

There is no configuration or service provider registration required; the package is auto-discovered by Laravel.

---

## Core Concepts

### 1. Defining Formats with Attributes

Extend `Devravik\ExtendedResources\ExtendedResource` instead of `Illuminate\Http\Resources\Json\JsonResource`, and define one or more `#[Format]` methods:

```php
<?php

use Devravik\ExtendedResources\ExtendedResource;
use Devravik\ExtendedResources\Formatting\Attributes\Format;

class ProductResource extends ExtendedResource
{
    #[Format]
    public function summary(): array
    {
        return [
            'sku'   => $this->resource->sku,
            'name'  => $this->resource->name,
            'price' => $this->resource->price,
        ];
    }
}
```

If a resource defines only a single `#[Format]` method, that format is considered the default.

#### Multiple Formats

```php
use Devravik\ExtendedResources\ExtendedResource;
use Devravik\ExtendedResources\Formatting\Attributes\Format;

class ProductResource extends ExtendedResource
{
    #[Format]
    public function summary(): array
    {
        return [
            'sku'  => $this->resource->sku,
            'name' => $this->resource->name,
        ];
    }

    #[Format]
    public function pricing(): array
    {
        return [
            'sku'      => $this->resource->sku,
            'price'    => $this->resource->price,
            'currency' => $this->resource->currency,
        ];
    }
}

// Choose a specific format at runtime
ProductResource::make($product)->format('pricing');
```

The format name defaults to the method name (`summary`, `pricing`, etc.).

### 2. Default Formats with `#[IsDefault]`

If you have multiple formats and want one to be used when no explicit format is selected, mark it with `#[IsDefault]`:

```php
use Devravik\ExtendedResources\ExtendedResource;
use Devravik\ExtendedResources\Formatting\Attributes\Format;
use Devravik\ExtendedResources\Formatting\Attributes\IsDefault;

class UserProfileResource extends ExtendedResource
{
    #[Format]
    public function compact(): array
    {
        return [
            'id'   => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }

    #[IsDefault, Format]
    public function detailed(): array
    {
        return [
            'id'      => $this->resource->id,
            'name'    => $this->resource->name,
            'email'   => $this->resource->email,
            'joined'  => $this->resource->created_at,
        ];
    }
}
```

If no explicit call to `format()` is made, the `detailed` format is used.

### 3. Modifying Resource Output

Every extended resource exposes a `modify()` method that lets you transform the final array:

```php
// Simple array merge
ProductResource::make($product)
    ->modify(['is_featured' => true]);

// Using a closure
ProductResource::make($product)
    ->modify(function (array $data) {
        $data['price_with_tax'] = $data['price'] * 1.2;

        return $data;
    });

// Using an invokable object
ProductResource::make($product)
    ->modify(new class {
        public function __invoke(array $data): array
        {
            $data['label'] = strtoupper($data['name']);

            return $data;
        }
    });
```

Multiple modifications can be chained; they are applied in order.

### 4. Enhancements: `except()` and `only()`

Two small enhancements ship with the package:

- `Except` + `AppliesExceptFilter` trait for excluding keys.
- `Only` + `AppliesOnlyFilter` trait for whitelisting keys.

```php
use Devravik\ExtendedResources\Enhancements\Except;
use Devravik\ExtendedResources\Enhancements\Only;
use Devravik\ExtendedResources\Enhancements\Traits\AppliesExceptFilter;
use Devravik\ExtendedResources\Enhancements\Traits\AppliesOnlyFilter;
use Devravik\ExtendedResources\ExtendedResource;
use Devravik\ExtendedResources\Formatting\Attributes\Format;

class CustomerResource extends ExtendedResource
{
    use AppliesExceptFilter;
    use AppliesOnlyFilter;

    #[Format]
    public function base(): array
    {
        return [
            'id'         => $this->resource->id,
            'name'       => $this->resource->name,
            'email'      => $this->resource->email,
            'created_at' => $this->resource->created_at,
        ];
    }
}

// Drop email from the payload
CustomerResource::make($customer)->except('email');

// Keep only ID + name
CustomerResource::make($customer)->only('id', 'name');

// You can also apply the enhancements manually:
CustomerResource::make($customer)->modify(new Except(['email']));
CustomerResource::make($customer)->modify(new Only(['id', 'name']));
```

### 5. Collections

Extended Resources work with both explicit collections and anonymous collections.

```php
use Devravik\ExtendedResources\ExtendedResource;

class OrderResource extends ExtendedResource
{
    // ...
}

// Anonymous collection
return OrderResource::collection($orders);
```

Under the hood this uses `ExtendedResourceCollection` and `ExtendedAnonymousResourceCollection`, which proxy modification methods like `format()`, `only()`, and `except()` down to each resource in the collection.

### 6. Response Status Codes

All resources and collections use the `SetsResponseStatus` trait, which adds a `setResponseStatus()` helper:

```php
use Symfony\Component\HttpFoundation\Response;

return ProductResource::make($product)
    ->setResponseStatus(Response::HTTP_CREATED);
```

This leaves your controller methods clean while still letting you adjust the status code where the data is built.

---

## API Overview

### ExtendedResource

Key methods:

- `format(string $name): static` – choose a named format.
- `modify(callable|array $modification): static` – queue a modification.
- `setResponseStatus(?int $code): static` – override the response status.

### ExtendedResourceCollection

Behaves similarly to Laravel's `ResourceCollection`, but:

- Ensures `collects` is an `ExtendedResource` subclass.
- Proxies unknown method calls to the underlying resource class when appropriate (e.g. `format()`, `only()`, etc.).

### Attributes

- `#[Format(?string $name = null)]` – declare a format; optional explicit name.
- `#[IsDefault]` – mark a format as the default when multiple formats exist.

---

## Testing

The package ships with a full PHPUnit test suite. To run it:

```bash
composer test
```
For HTTP tests in your own application, you can continue to rely on Laravel's built‑in response assertions when controllers return extended resources.

---

## Contributing

Contributions are welcome:

1. Fork the repository and create a feature branch from `main`.
2. Add tests for any new functionality or bug fixes.
3. Run `composer test` to ensure the suite passes.
4. Follow PSR-12 / Laravel Pint style guidelines.
5. Open a pull request with a clear description of the change.

Bug reports should include your PHP and Laravel versions, the package version, minimal reproduction code, and any relevant stack traces.

---

## Security

If you discover a security vulnerability, please **do not** open a public GitHub issue.

Instead, email `dev.ravikgupta@gmail.com` with the subject line:

`[SECURITY] devravik/extended-resources <short description>`

You will receive a response as soon as possible with next steps.

---

## Maintainer

**Ravi K Gupta**

- **Website**: [devravik.github.io](https://devravik.github.io/)
- **Email**: `dev.ravikgupta@gmail.com`
- **LinkedIn**: [linkedin.com/in/ravi-k-dev](https://www.linkedin.com/in/ravi-k-dev)
- **GitHub**: [github.com/devravik](https://github.com/devravik)

---

## License

The MIT License (MIT). See the [LICENSE](LICENSE) file for details.