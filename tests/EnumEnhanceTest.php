<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use PHPUnit\Framework\TestCase;
use youvision\store\admin\support\enums\EnumEnhance;

enum EnumEnhanceTestStatus: int
{
    use EnumEnhance;

    case ENABLED = 1;
    case DISABLED = 0;
    case PENDING = 2;
}

final class EnumEnhanceTest extends TestCase
{
    public function testNamesAndValues(): void
    {
        self::assertSame(['ENABLED', 'DISABLED', 'PENDING'], EnumEnhanceTestStatus::names());
        self::assertSame([1, 0, 2], EnumEnhanceTestStatus::values());
        self::assertSame('ENABLED', EnumEnhanceTestStatus::ENABLED->name());
        self::assertSame(1, EnumEnhanceTestStatus::ENABLED->value());
    }

    public function testHasNameAndValue(): void
    {
        self::assertTrue(EnumEnhanceTestStatus::hasName('ENABLED'));
        self::assertFalse(EnumEnhanceTestStatus::hasName('enabled'));
        self::assertTrue(EnumEnhanceTestStatus::hasValue(1));
        self::assertFalse(EnumEnhanceTestStatus::hasValue(99));
    }

    public function testFromNameAndValue(): void
    {
        self::assertSame(EnumEnhanceTestStatus::ENABLED, EnumEnhanceTestStatus::fromName('ENABLED'));
        self::assertSame(EnumEnhanceTestStatus::DISABLED, EnumEnhanceTestStatus::fromValue(0));
    }

    public function testInvalidNameAndValue(): void
    {
        $this->expectException(\ValueError::class);
        EnumEnhanceTestStatus::fromName('UNKNOWN');
    }

    public function testGuess(): void
    {
        self::assertSame(EnumEnhanceTestStatus::PENDING, EnumEnhanceTestStatus::guess('PENDING'));
        self::assertSame(EnumEnhanceTestStatus::PENDING, EnumEnhanceTestStatus::guess(2));
    }

    public function testIsMethods(): void
    {
        $enabled = EnumEnhanceTestStatus::ENABLED;

        self::assertTrue($enabled->is(EnumEnhanceTestStatus::ENABLED));
        self::assertFalse($enabled->is(EnumEnhanceTestStatus::DISABLED));
        self::assertTrue($enabled->isNot(EnumEnhanceTestStatus::DISABLED));
        self::assertTrue($enabled->isAny([
            EnumEnhanceTestStatus::DISABLED,
            EnumEnhanceTestStatus::ENABLED,
        ]));
    }

    public function testToArrayAndSelectArray(): void
    {
        $array = EnumEnhanceTestStatus::toArray();
        self::assertSame('ENABLED', $array[0]['name']);
        self::assertSame(1, $array[0]['value']);
        self::assertSame('Enabled', $array[0]['description']);

        self::assertSame([
            1 => 'Enabled',
            0 => 'Disabled',
            2 => 'Pending',
        ], EnumEnhanceTestStatus::toSelectArray());
    }
}