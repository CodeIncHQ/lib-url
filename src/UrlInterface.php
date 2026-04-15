<?php

declare(strict_types=1);

namespace CodeInc\Url;

use Psr\Http\Message\UriInterface;

/**
 * Extended URI interface with additional URL manipulation methods.
 *
 * Extends PSR-7 UriInterface with convenience methods for removing components,
 * accessing user/password separately, and working with query parameters as arrays.
 */
interface UrlInterface extends UriInterface
{
    // Narrowed return types (static instead of UriInterface)

    public function withScheme(string $scheme): static;
    public function withUserInfo(string $user, ?string $password = null): static;
    public function withHost(string $host): static;
    public function withPort(?int $port): static;
    public function withPath(string $path): static;
    public function withQuery(string $query): static;
    public function withFragment(string $fragment): static;

    // Component removal methods

    public function withoutScheme(): static;
    public function withoutHost(): static;
    public function withoutPort(): static;
    public function withoutUserInfo(): static;
    public function withoutPath(): static;
    public function withoutFragment(): static;

    /**
     * Returns a new instance without the specified query parameters.
     * If no parameters are specified, removes the entire query string.
     *
     * @param iterable<string>|null $parameters Parameter names to remove, or null to remove all.
     */
    public function withoutQuery(?iterable $parameters = null): static;

    // User info accessors

    public function getUser(): ?string;
    public function getPassword(): ?string;

    // Query helpers

    /** @return array<string, string> */
    public function getQueryAsArray(): array;

    /**
     * Returns a new instance with the given query parameters merged in.
     *
     * @param iterable<string, string|int|float> $params
     */
    public function withQueryParams(iterable $params): static;

    // URL building

    public function buildUrl(
        bool $withHost = true,
        bool $withUserInfo = true,
        bool $withPort = true,
        bool $withQuery = true,
        bool $withFragment = true,
    ): string;

    public function getFullUrl(): string;
    public function getRelUrl(): string;
}
