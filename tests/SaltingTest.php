<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use youvision\store\admin\support\auth\Salting;

final class SaltingTest extends TestCase
{
    public function testEncodeMatchesAdminPasswordAlgorithm(): void
    {
        $password = 'secret';
        $salt = 'salt-1';
        $salt2 = 'salt-2';
        $expected = md5($salt2 . 'Y!o^U' . md5(md5($password) . $salt) . 'V,is*Io(N' . $salt);

        self::assertSame($expected, Salting::encode($password, $salt, $salt2));
        self::assertTrue(Salting::verify($password, $salt, $salt2, $expected));
        self::assertFalse(Salting::verify('wrong', $salt, $salt2, $expected));
        self::assertFalse(Salting::verify($password, 'other-salt', $salt2, $expected));
    }

    public function testMakeSalt(): void
    {
        $salt = Salting::makeSalt(12);

        self::assertSame(12, strlen($salt));
        self::assertNotSame('', $salt);
    }

    public function testMakePair(): void
    {
        $pair = Salting::makePair(5, 7);

        self::assertArrayHasKey('salt', $pair);
        self::assertArrayHasKey('salt2', $pair);
        self::assertSame(5, strlen($pair['salt']));
        self::assertSame(7, strlen($pair['salt2']));
    }

    public function testInvalidLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Salting::makeSalt(0);
    }
}