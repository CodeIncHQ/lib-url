<?php

declare(strict_types=1);

namespace CodeInc\Url;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Immutable URL value object implementing PSR-7 UriInterface.
 */
class Url implements UrlInterface
{
    private const STANDARD_PORTS = [
        'http' => 80,
        'https' => 443,
    ];

    private ?string $scheme = null;
    private ?string $host = null;
    private ?int $port = null;
    private ?string $user = null;
    private ?string $password = null;
    private ?string $path = null;
    /** @var array<string, string> */
    private array $query = [];
    private ?string $fragment = null;

    // --- Factory methods ---

    public static function fromString(string $url): static
    {
        $instance = new static();
        $parsed = parse_url($url);
        if ($parsed === false) {
            return $instance;
        }

        if (isset($parsed['scheme'])) {
            $instance->scheme = strtolower($parsed['scheme']);
        }
        if (isset($parsed['host'])) {
            $instance->host = strtolower($parsed['host']);
        }
        if (isset($parsed['port'])) {
            $instance->port = $parsed['port'];
        }
        if (isset($parsed['user'])) {
            $instance->user = $parsed['user'];
            $instance->password = $parsed['pass'] ?? null;
        }
        if (isset($parsed['path'])) {
            $instance->path = $parsed['path'];
        }
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
            $instance->query = array_map(strval(...), $queryParams);
        }
        if (isset($parsed['fragment'])) {
            $instance->fragment = $parsed['fragment'];
        }

        return $instance;
    }

    public static function fromGlobals(bool $withScheme = true): static
    {
        $instance = new static();

        if ($withScheme) {
            $instance->scheme = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            $parsed = parse_url('//' . $_SERVER['HTTP_HOST']);
            if ($parsed !== false && isset($parsed['host'])) {
                $instance->host = strtolower($parsed['host']);
                if (isset($parsed['port'])) {
                    $instance->port = $parsed['port'];
                }
            }
        }

        if (isset($_SERVER['REQUEST_URI'])) {
            $questionPos = strpos($_SERVER['REQUEST_URI'], '?');
            if ($questionPos !== false) {
                $instance->path = substr($_SERVER['REQUEST_URI'], 0, $questionPos);
                $queryString = substr($_SERVER['REQUEST_URI'], $questionPos + 1);
                if ($queryString !== '') {
                    parse_str($queryString, $queryParams);
                    $instance->query = array_map(strval(...), $queryParams);
                }
            } else {
                $instance->path = $_SERVER['REQUEST_URI'];
            }
        }

        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $instance->user = $_SERVER['PHP_AUTH_USER'];
            $instance->password = $_SERVER['PHP_AUTH_PW'] ?? null;
        }

        return $instance;
    }

    public static function fromPsr7Uri(UriInterface $uri): static
    {
        $instance = new static();

        $scheme = $uri->getScheme();
        if ($scheme !== '') {
            $instance->scheme = strtolower($scheme);
        }

        $host = $uri->getHost();
        if ($host !== '') {
            $instance->host = $host;
        }

        $instance->port = $uri->getPort();

        $path = $uri->getPath();
        if ($path !== '') {
            $instance->path = $path;
        }

        $userInfo = $uri->getUserInfo();
        if ($userInfo !== '') {
            $parts = explode(':', $userInfo, 2);
            $instance->user = $parts[0];
            $instance->password = $parts[1] ?? null;
        }

        $query = $uri->getQuery();
        if ($query !== '') {
            parse_str($query, $queryParams);
            $instance->query = array_map(strval(...), $queryParams);
        }

        $fragment = $uri->getFragment();
        if ($fragment !== '') {
            $instance->fragment = $fragment;
        }

        return $instance;
    }

    public static function fromPsr7Request(ServerRequestInterface $request): static
    {
        $instance = static::fromPsr7Uri($request->getUri());
        $queryParams = $request->getQueryParams();
        if (!empty($queryParams)) {
            parse_str(http_build_query($queryParams), $normalized);
            $instance->query = array_map(strval(...), $normalized);
        }
        return $instance;
    }

    // --- PSR-7 UriInterface getters ---

    public function getScheme(): string
    {
        return $this->scheme ?? '';
    }

    public function getAuthority(): string
    {
        $authority = '';
        $userInfo = $this->getUserInfo();
        if ($userInfo !== '') {
            $authority = $userInfo . '@';
        }
        if ($this->host !== null) {
            $authority .= $this->host;
        }
        $port = $this->getPort();
        if ($port !== null) {
            $authority .= ':' . $port;
        }
        return $authority;
    }

    public function getUserInfo(): string
    {
        if ($this->user === null) {
            return '';
        }
        $info = $this->user;
        if ($this->password !== null) {
            $info .= ':' . $this->password;
        }
        return $info;
    }

    public function getHost(): string
    {
        return $this->host ?? '';
    }

    public function getPort(): ?int
    {
        if (
            $this->port !== null
            && $this->scheme !== null
            && isset(self::STANDARD_PORTS[$this->scheme])
            && $this->port === self::STANDARD_PORTS[$this->scheme]
        ) {
            return null;
        }
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path ?? '';
    }

    public function getQuery(): string
    {
        $parts = [];
        foreach ($this->query as $param => $value) {
            $part = rawurlencode((string) $param);
            if ($value !== '') {
                $part .= '=' . rawurlencode($value);
            }
            $parts[] = $part;
        }
        return implode('&', $parts);
    }

    public function getFragment(): string
    {
        return $this->fragment ?? '';
    }

    // --- PSR-7 UriInterface with* methods ---

    public function withScheme(string $scheme): static
    {
        $clone = clone $this;
        $clone->scheme = $scheme !== '' ? strtolower($scheme) : null;
        return $clone;
    }

    public function withUserInfo(string $user, ?string $password = null): static
    {
        $clone = clone $this;
        if ($user !== '') {
            $clone->user = $user;
            $clone->password = $password;
        } else {
            $clone->user = null;
            $clone->password = null;
        }
        return $clone;
    }

    public function withHost(string $host): static
    {
        $clone = clone $this;
        $clone->host = $host !== '' ? strtolower($host) : null;
        return $clone;
    }

    public function withPort(?int $port): static
    {
        $clone = clone $this;
        $clone->port = $port;
        return $clone;
    }

    public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->path = $path !== '' ? $path : null;
        return $clone;
    }

    public function withQuery(string $query): static
    {
        $clone = clone $this;
        if ($query !== '') {
            parse_str($query, $params);
            $clone->query = array_map(strval(...), $params);
        } else {
            $clone->query = [];
        }
        return $clone;
    }

    public function withFragment(string $fragment): static
    {
        $clone = clone $this;
        $clone->fragment = $fragment !== '' ? $fragment : null;
        return $clone;
    }

    // --- UrlInterface extra methods ---

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /** @return array<string, string> */
    public function getQueryAsArray(): array
    {
        return $this->query;
    }

    public function withQueryParams(iterable $params): static
    {
        $clone = clone $this;
        foreach ($params as $key => $value) {
            $clone->query[(string) $key] = (string) $value;
        }
        return $clone;
    }

    public function withoutScheme(): static
    {
        $clone = clone $this;
        $clone->scheme = null;
        return $clone;
    }

    public function withoutHost(): static
    {
        $clone = clone $this;
        $clone->host = null;
        return $clone;
    }

    public function withoutPort(): static
    {
        $clone = clone $this;
        $clone->port = null;
        return $clone;
    }

    public function withoutUserInfo(): static
    {
        $clone = clone $this;
        $clone->user = null;
        $clone->password = null;
        return $clone;
    }

    public function withoutPath(): static
    {
        $clone = clone $this;
        $clone->path = null;
        return $clone;
    }

    public function withoutFragment(): static
    {
        $clone = clone $this;
        $clone->fragment = null;
        return $clone;
    }

    public function withoutQuery(?iterable $parameters = null): static
    {
        $clone = clone $this;
        if ($parameters === null) {
            $clone->query = [];
        } else {
            foreach ($parameters as $param) {
                unset($clone->query[(string) $param]);
            }
        }
        return $clone;
    }

    public function buildUrl(
        bool $withHost = true,
        bool $withUserInfo = true,
        bool $withPort = true,
        bool $withQuery = true,
        bool $withFragment = true,
    ): string {
        $url = '';

        if ($withHost && $this->host !== null) {
            if ($this->scheme !== null) {
                $url .= $this->scheme . ':';
            }
            $url .= '//';

            if ($withUserInfo && $this->user !== null) {
                $url .= $this->getUserInfo() . '@';
            }

            $url .= $this->host;

            $port = $this->getPort();
            if ($withPort && $port !== null) {
                $url .= ':' . $port;
            }
        }

        $url .= $this->path ?? '/';

        if ($withQuery && !empty($this->query)) {
            $url .= '?' . $this->getQuery();
        }

        if ($withFragment && $this->fragment !== null) {
            $url .= '#' . rawurlencode($this->fragment);
        }

        return $url;
    }

    public function getFullUrl(): string
    {
        return $this->buildUrl();
    }

    public function getRelUrl(): string
    {
        return $this->buildUrl(withHost: false, withUserInfo: false, withPort: false);
    }

    public function __toString(): string
    {
        return $this->getFullUrl();
    }
}
