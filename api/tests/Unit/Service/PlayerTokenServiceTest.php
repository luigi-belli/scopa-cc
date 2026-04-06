<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\PlayerTokenService;
use PHPUnit\Framework\TestCase;

class PlayerTokenServiceTest extends TestCase
{
    private PlayerTokenService $service;

    protected function setUp(): void
    {
        $this->service = new PlayerTokenService();
    }

    public function testGenerateToken(): void
    {
        $token = $this->service->generateToken();

        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);

        // Two tokens should be different
        $token2 = $this->service->generateToken();
        $this->assertNotEquals($token, $token2);
    }

    public function testSanitizeName(): void
    {
        $this->assertEquals('Alice', $this->service->sanitizeName('  Alice  '));
        $this->assertEquals('Bob', $this->service->sanitizeName("Bob\x00\x01"));
        $this->assertEquals(30, mb_strlen($this->service->sanitizeName(str_repeat('A', 50))));
        $this->assertEquals('', $this->service->sanitizeName('   '));
    }
}
