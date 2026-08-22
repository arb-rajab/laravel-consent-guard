<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Tests\Fixtures;

use ArbRajab\ConsentGuard\Consent\Concerns\HasConsent;
use ArbRajab\ConsentGuard\Consent\Contracts\ConsentSubject;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Stand-in for "an app's authenticatable user model that also implements
 * ConsentSubject," used by the middleware tests. Implements Authenticatable
 * by hand rather than via illuminate/auth's Authenticatable trait, since
 * this package doesn't otherwise depend on illuminate/auth and a test
 * fixture shouldn't be the reason it starts to.
 */
class TestUser implements Authenticatable, ConsentSubject
{
    use HasConsent;

    public function __construct(private readonly string $id) {}

    public function getKey(): string
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
