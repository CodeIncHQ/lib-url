<?php

declare(strict_types=1);

namespace CodeInc\Url\Tests;

use CodeInc\Url\Url;
use CodeInc\Url\UrlInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

class UrlTest extends TestCase
{
    private const SCHEME = 'https';
    private const USER = 'user';
    private const PASSWORD = 'pass';
    private const HOST = 'www.example.com';
    private const PORT = 8080;
    private const PATH = '/a/great_path';
    private const QUERY = 'p1=val1&p2&p3=1&p4=0';
    private const FRAGMENT = 'fragment';

    private const FULL_URL = self::SCHEME . '://' . self::USER . ':' . self::PASSWORD . '@'
        . self::HOST . ':' . self::PORT . self::PATH . '?' . self::QUERY . '#' . self::FRAGMENT;

    private const REL_URL = self::PATH . '?' . self::QUERY . '#' . self::FRAGMENT;

    // --- Parsing ---

    public function testFromString(): void
    {
        $url = Url::fromString(self::FULL_URL);

        self::assertSame(self::SCHEME, $url->getScheme());
        self::assertSame(self::USER, $url->getUser());
        self::assertSame(self::PASSWORD, $url->getPassword());
        self::assertSame(self::HOST, $url->getHost());
        self::assertSame(self::PORT, $url->getPort());
        self::assertSame(self::PATH, $url->getPath());
        self::assertSame(self::QUERY, $url->getQuery());
        self::assertSame(self::FRAGMENT, $url->getFragment());
    }

    public function testFromStringMinimal(): void
    {
        $url = Url::fromString('https://example.com');

        self::assertSame('https', $url->getScheme());
        self::assertSame('example.com', $url->getHost());
        self::assertNull($url->getPort());
        self::assertSame('', $url->getPath());
        self::assertSame('', $url->getQuery());
        self::assertSame('', $url->getFragment());
        self::assertNull($url->getUser());
        self::assertNull($url->getPassword());
    }

    public function testFromStringPathOnly(): void
    {
        $url = Url::fromString('/some/path?key=value');

        self::assertSame('', $url->getScheme());
        self::assertSame('', $url->getHost());
        self::assertSame('/some/path', $url->getPath());
        self::assertSame('key=value', $url->getQuery());
    }

    public function testFromStringInvalid(): void
    {
        $url = Url::fromString('://');

        self::assertSame('', $url->getScheme());
        self::assertSame('', $url->getHost());
    }

    public function testFromStringUserWithoutPassword(): void
    {
        $url = Url::fromString('https://admin@example.com/path');

        self::assertSame('admin', $url->getUser());
        self::assertNull($url->getPassword());
        self::assertSame('admin', $url->getUserInfo());
    }

    // --- Builder (fluent interface) ---

    public function testBuilder(): void
    {
        $url = (new Url())
            ->withScheme(self::SCHEME)
            ->withUserInfo(self::USER, self::PASSWORD)
            ->withHost(self::HOST)
            ->withPort(self::PORT)
            ->withPath(self::PATH)
            ->withQuery(self::QUERY)
            ->withFragment(self::FRAGMENT);

        self::assertSame(self::FULL_URL, $url->getFullUrl());
        self::assertSame(self::REL_URL, $url->getRelUrl());
    }

    public function testBuilderSchemeLowercased(): void
    {
        $url = (new Url())->withScheme('HTTPS');
        self::assertSame('https', $url->getScheme());
    }

    // --- Immutability ---

    public function testWithMethodsReturnNewInstance(): void
    {
        $original = Url::fromString(self::FULL_URL);

        self::assertNotSame($original, $original->withScheme('http'));
        self::assertNotSame($original, $original->withHost('other.com'));
        self::assertNotSame($original, $original->withPort(9090));
        self::assertNotSame($original, $original->withPath('/other'));
        self::assertNotSame($original, $original->withQuery('x=1'));
        self::assertNotSame($original, $original->withFragment('other'));
        self::assertNotSame($original, $original->withUserInfo('other'));
    }

    public function testWithMethodsDoNotMutateOriginal(): void
    {
        $original = Url::fromString('https://example.com/path');
        $original->withScheme('http');
        $original->withHost('other.com');
        $original->withPort(9090);

        self::assertSame('https', $original->getScheme());
        self::assertSame('example.com', $original->getHost());
        self::assertNull($original->getPort());
    }

    // --- Without methods ---

    public function testWithoutScheme(): void
    {
        $url = Url::fromString('https://example.com')->withoutScheme();
        self::assertSame('', $url->getScheme());
    }

    public function testWithoutHost(): void
    {
        $url = Url::fromString('https://example.com/path')->withoutHost();
        self::assertSame('', $url->getHost());
    }

    public function testWithoutPort(): void
    {
        $url = Url::fromString('https://example.com:8080/path')->withoutPort();
        self::assertNull($url->getPort());
    }

    public function testWithoutUserInfo(): void
    {
        $url = Url::fromString('https://user:pass@example.com')->withoutUserInfo();
        self::assertSame('', $url->getUserInfo());
        self::assertNull($url->getUser());
        self::assertNull($url->getPassword());
    }

    public function testWithoutPath(): void
    {
        $url = Url::fromString('https://example.com/some/path')->withoutPath();
        self::assertSame('', $url->getPath());
    }

    public function testWithoutFragment(): void
    {
        $url = Url::fromString('https://example.com#frag')->withoutFragment();
        self::assertSame('', $url->getFragment());
    }

    public function testWithoutQueryRemovesAll(): void
    {
        $url = Url::fromString('https://example.com?a=1&b=2')->withoutQuery();
        self::assertSame('', $url->getQuery());
        self::assertSame([], $url->getQueryAsArray());
    }

    public function testWithoutQueryRemovesSpecific(): void
    {
        $url = Url::fromString('https://example.com?a=1&b=2&c=3')
            ->withoutQuery(['a', 'c']);

        self::assertSame(['b' => '2'], $url->getQueryAsArray());
        self::assertSame('b=2', $url->getQuery());
    }

    // --- Query manipulation ---

    public function testWithQueryReplacesEntireQuery(): void
    {
        $url = Url::fromString('https://example.com?a=1&b=2')
            ->withQuery('x=10&y=20');

        self::assertSame(['x' => '10', 'y' => '20'], $url->getQueryAsArray());
    }

    public function testWithQueryEmptyRemovesQuery(): void
    {
        $url = Url::fromString('https://example.com?a=1')
            ->withQuery('');

        self::assertSame('', $url->getQuery());
        self::assertSame([], $url->getQueryAsArray());
    }

    public function testWithQueryParamsMerges(): void
    {
        $url = Url::fromString('https://example.com?a=1&b=2')
            ->withQueryParams(['b' => '20', 'c' => '30']);

        self::assertSame(['a' => '1', 'b' => '20', 'c' => '30'], $url->getQueryAsArray());
    }

    public function testQueryPreservesValuelessParams(): void
    {
        $url = Url::fromString('https://example.com?flag&key=value');
        $query = $url->getQueryAsArray();

        self::assertArrayHasKey('flag', $query);
        self::assertSame('', $query['flag']);
        self::assertSame('value', $query['key']);
        self::assertSame('flag&key=value', $url->getQuery());
    }

    public function testQueryWithZeroValue(): void
    {
        $url = Url::fromString('https://example.com?count=0');
        self::assertSame('0', $url->getQueryAsArray()['count']);
        self::assertSame('count=0', $url->getQuery());
    }

    // --- PSR-7 getters return empty strings (not null) ---

    public function testEmptyUrlReturnsPsr7Defaults(): void
    {
        $url = new Url();

        self::assertSame('', $url->getScheme());
        self::assertSame('', $url->getHost());
        self::assertSame('', $url->getPath());
        self::assertSame('', $url->getQuery());
        self::assertSame('', $url->getFragment());
        self::assertSame('', $url->getUserInfo());
        self::assertSame('', $url->getAuthority());
        self::assertNull($url->getPort());
    }

    // --- withScheme/withHost/withPort empty values ---

    public function testWithEmptySchemeRemovesScheme(): void
    {
        $url = Url::fromString('https://example.com')->withScheme('');
        self::assertSame('', $url->getScheme());
    }

    public function testWithEmptyHostRemovesHost(): void
    {
        $url = Url::fromString('https://example.com')->withHost('');
        self::assertSame('', $url->getHost());
    }

    public function testWithNullPortRemovesPort(): void
    {
        $url = Url::fromString('https://example.com:8080')->withPort(null);
        self::assertNull($url->getPort());
    }

    public function testWithEmptyUserInfoRemovesUserInfo(): void
    {
        $url = Url::fromString('https://user:pass@example.com')->withUserInfo('');
        self::assertSame('', $url->getUserInfo());
        self::assertNull($url->getUser());
    }

    // --- Authority ---

    public function testGetAuthorityFull(): void
    {
        $url = Url::fromString('https://user:pass@example.com:8080/path');
        self::assertSame('user:pass@example.com:8080', $url->getAuthority());
    }

    public function testGetAuthorityHostOnly(): void
    {
        $url = Url::fromString('https://example.com/path');
        self::assertSame('example.com', $url->getAuthority());
    }

    public function testGetAuthorityEmpty(): void
    {
        $url = Url::fromString('/just/a/path');
        self::assertSame('', $url->getAuthority());
    }

    // --- User info ---

    public function testGetUserInfoWithPassword(): void
    {
        $url = Url::fromString('https://admin:secret@example.com');
        self::assertSame('admin:secret', $url->getUserInfo());
        self::assertSame('admin', $url->getUser());
        self::assertSame('secret', $url->getPassword());
    }

    public function testGetUserInfoWithoutPassword(): void
    {
        $url = Url::fromString('https://admin@example.com');
        self::assertSame('admin', $url->getUserInfo());
        self::assertSame('admin', $url->getUser());
        self::assertNull($url->getPassword());
    }

    // --- URL building ---

    public function testGetFullUrl(): void
    {
        $url = Url::fromString(self::FULL_URL);
        self::assertSame(self::FULL_URL, $url->getFullUrl());
    }

    public function testGetRelUrl(): void
    {
        $url = Url::fromString(self::FULL_URL);
        self::assertSame(self::REL_URL, $url->getRelUrl());
    }

    public function testBuildUrlWithoutUserInfo(): void
    {
        $url = Url::fromString('https://user:pass@example.com:8080/path');
        $built = $url->buildUrl(withUserInfo: false);
        self::assertSame('https://example.com:8080/path', $built);
    }

    public function testBuildUrlWithoutPort(): void
    {
        $url = Url::fromString('https://example.com:8080/path');
        $built = $url->buildUrl(withPort: false);
        self::assertSame('https://example.com/path', $built);
    }

    public function testBuildUrlWithoutQuery(): void
    {
        $url = Url::fromString('https://example.com/path?q=1');
        $built = $url->buildUrl(withQuery: false);
        self::assertSame('https://example.com/path', $built);
    }

    public function testBuildUrlWithoutFragment(): void
    {
        $url = Url::fromString('https://example.com/path#frag');
        $built = $url->buildUrl(withFragment: false);
        self::assertSame('https://example.com/path', $built);
    }

    public function testBuildUrlNoHostDefaultsToSlash(): void
    {
        $url = new Url();
        self::assertSame('/', $url->buildUrl());
    }

    public function testBuildUrlSchemelessAuthority(): void
    {
        $url = (new Url())->withHost('example.com');
        self::assertSame('//example.com/', $url->buildUrl());
    }

    // --- __toString ---

    public function testToString(): void
    {
        $url = Url::fromString(self::FULL_URL);
        self::assertSame(self::FULL_URL, (string) $url);
    }

    public function testToStringEmpty(): void
    {
        $url = new Url();
        self::assertSame('/', (string) $url);
    }

    // --- Interface compliance ---

    public function testImplementsUriInterface(): void
    {
        $url = new Url();
        self::assertInstanceOf(UriInterface::class, $url);
        self::assertInstanceOf(UrlInterface::class, $url);
    }

    // --- Special characters ---

    public function testQuerySpecialCharacters(): void
    {
        $url = (new Url())
            ->withHost('example.com')
            ->withQueryParams(['q' => 'hello world', 'tag' => 'a&b']);

        self::assertSame('q=hello%20world&tag=a%26b', $url->getQuery());
    }

    public function testFragmentEncoding(): void
    {
        $url = (new Url())
            ->withHost('example.com')
            ->withFragment('section one');

        self::assertStringContainsString('#section%20one', $url->getFullUrl());
    }

    public function testPathWithEncodedCharacters(): void
    {
        $url = Url::fromString('https://example.com/path%20with%20spaces');
        self::assertSame('/path%20with%20spaces', $url->getPath());
    }

    // --- Standard port filtering (PSR-7) ---

    public function testGetPortReturnsNullForStandardHttpPort(): void
    {
        $url = Url::fromString('http://example.com:80/path');
        self::assertNull($url->getPort());
    }

    public function testGetPortReturnsNullForStandardHttpsPort(): void
    {
        $url = Url::fromString('https://example.com:443/path');
        self::assertNull($url->getPort());
    }

    public function testGetPortReturnsNonStandardPort(): void
    {
        $url = Url::fromString('https://example.com:8080/path');
        self::assertSame(8080, $url->getPort());
    }

    public function testStandardPortOmittedFromAuthority(): void
    {
        $url = Url::fromString('https://example.com:443/path');
        self::assertSame('example.com', $url->getAuthority());
    }

    public function testStandardPortOmittedFromFullUrl(): void
    {
        $url = Url::fromString('https://example.com:443/path');
        self::assertSame('https://example.com/path', $url->getFullUrl());
    }

    // --- Host lowercase (PSR-7) ---

    public function testWithHostLowercases(): void
    {
        $url = (new Url())->withHost('Example.COM');
        self::assertSame('example.com', $url->getHost());
    }

    public function testFromStringLowercasesHost(): void
    {
        $url = Url::fromString('https://Example.COM/path');
        self::assertSame('example.com', $url->getHost());
    }

    // --- Edge cases ---

    public function testFromStringWithPortZero(): void
    {
        // Port 0 is technically valid
        $url = Url::fromString('http://example.com:0/path');
        self::assertSame(0, $url->getPort());
    }

    #[DataProvider('urlRoundTripProvider')]
    public function testUrlRoundTrip(string $input, string $expectedFull): void
    {
        $url = Url::fromString($input);
        self::assertSame($expectedFull, $url->getFullUrl());
    }

    /** @return array<string, array{string, string}> */
    public static function urlRoundTripProvider(): array
    {
        return [
            'full url' => [
                'https://user:pass@example.com:8080/path?q=1#frag',
                'https://user:pass@example.com:8080/path?q=1#frag',
            ],
            'scheme and host' => [
                'https://example.com',
                'https://example.com/',
            ],
            'with path' => [
                'https://example.com/foo/bar',
                'https://example.com/foo/bar',
            ],
            'with query' => [
                'https://example.com/path?a=1&b=2',
                'https://example.com/path?a=1&b=2',
            ],
            'http scheme' => [
                'http://example.com/',
                'http://example.com/',
            ],
        ];
    }

    // --- fromPsr7Uri ---

    public function testFromPsr7Uri(): void
    {
        $source = Url::fromString(self::FULL_URL);
        $copy = Url::fromPsr7Uri($source);

        self::assertSame($source->getScheme(), $copy->getScheme());
        self::assertSame($source->getHost(), $copy->getHost());
        self::assertSame($source->getPort(), $copy->getPort());
        self::assertSame($source->getPath(), $copy->getPath());
        self::assertSame($source->getQuery(), $copy->getQuery());
        self::assertSame($source->getFragment(), $copy->getFragment());
        self::assertSame($source->getUserInfo(), $copy->getUserInfo());
    }

    // --- Chaining ---

    public function testComplexChaining(): void
    {
        $url = (new Url())
            ->withScheme('https')
            ->withHost('api.example.com')
            ->withPort(8443)
            ->withPath('/v2/users')
            ->withQueryParams(['page' => '1', 'limit' => '50'])
            ->withFragment('results');

        self::assertSame(
            'https://api.example.com:8443/v2/users?page=1&limit=50#results',
            $url->getFullUrl(),
        );

        $modified = $url
            ->withoutPort()
            ->withQueryParams(['page' => '2'])
            ->withoutFragment();

        self::assertSame(
            'https://api.example.com/v2/users?page=2&limit=50',
            $modified->getFullUrl(),
        );

        // Original unchanged
        self::assertSame(8443, $url->getPort());
        self::assertSame('results', $url->getFragment());
    }
}
