# codeinc/url

[![Tests](https://github.com/codeinchq/url/actions/workflows/tests.yml/badge.svg)](https://github.com/codeinchq/url/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/codeinc/url/v/stable)](https://packagist.org/packages/codeinc/url)
[![PHP](https://poser.pugx.org/codeinc/url/require/php)](https://packagist.org/packages/codeinc/url)
[![Total Downloads](https://poser.pugx.org/codeinc/url/downloads)](https://packagist.org/packages/codeinc/url)
[![License](https://poser.pugx.org/codeinc/url/license)](https://packagist.org/packages/codeinc/url)

A PHP library for URL manipulation, implementing [PSR-7](https://www.php-fig.org/psr/psr-7/) `UriInterface`.

## Requirements

- PHP 8.2+

## Installation

```bash
composer require codeinc/url
```

## Usage

### Parsing a URL

```php
use CodeInc\Url\Url;

$url = Url::fromString('https://www.example.com/search?q=php&page=1#results');

$url->getScheme();       // 'https'
$url->getHost();         // 'www.example.com'
$url->getPath();         // '/search'
$url->getQuery();        // 'q=php&page=1'
$url->getQueryAsArray(); // ['q' => 'php', 'page' => '1']
$url->getFragment();     // 'results'
```

### Building a URL

```php
$url = (new Url())
    ->withScheme('https')
    ->withHost('api.example.com')
    ->withPath('/v2/users')
    ->withQueryParams(['page' => '1', 'limit' => '50']);

echo $url; // https://api.example.com/v2/users?page=1&limit=50
```

### Modifying a URL

All `with*` methods return a new immutable instance:

```php
$url = Url::fromString('https://example.com/path?a=1&b=2#frag');

$modified = $url
    ->withScheme('http')
    ->withQueryParams(['c' => '3'])
    ->withoutFragment();

echo $modified; // http://example.com/path?a=1&b=2&c=3
echo $url;      // https://example.com/path?a=1&b=2#frag (unchanged)
```

### Removing components

```php
$url->withoutScheme();
$url->withoutHost();
$url->withoutPort();
$url->withoutUserInfo();
$url->withoutPath();
$url->withoutFragment();
$url->withoutQuery();            // removes entire query string
$url->withoutQuery(['a', 'b']); // removes specific parameters
```

### PSR-7 interop

```php
// From a PSR-7 UriInterface
$url = Url::fromPsr7Uri($psr7Uri);

// From a PSR-7 ServerRequestInterface
$url = Url::fromPsr7Request($serverRequest);

// Use anywhere a UriInterface is expected
function processUri(UriInterface $uri): void { /* ... */ }
processUri($url);
```

### Getting the current URL

```php
$url = Url::fromGlobals();
```

## Tests

```bash
composer install
vendor/bin/phpunit
```

## License

This library is published under the MIT license (see [LICENSE](LICENSE) file).
