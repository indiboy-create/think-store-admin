<?php

declare(strict_types=1);

namespace youvision\store\auth\tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use youvision\store\auth\support\RuleMatcher;

final class RuleMatcherTest extends TestCase
{
    private RuleMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new RuleMatcher();
    }

    public function testNormalizeNames(): void
    {
        self::assertSame(
            ['user/index', 'user/edit'],
            $this->matcher->normalizeNames(' User/Index , user/edit ,')
        );

        self::assertSame(['a', 'b'], $this->matcher->normalizeNames([' A ', 'b', '']));
    }

    /**
     * @param array<int, string>   $rules
     * @param array<int, string>   $names
     * @param array<string, mixed> $params
     */
    #[DataProvider('matchProvider')]
    public function testMatches(
        array $rules,
        array $names,
        array $params,
        string $mode,
        string $relation,
        bool $expected
    ): void {
        self::assertSame($expected, $this->matcher->matches($rules, $names, $params, $mode, $relation));
    }

    /**
     * @return array<string, array{0: array<int, string>, 1: array<int, string>, 2: array<string, mixed>, 3: string, 4: string, 5: bool}>
     */
    public static function matchProvider(): array
    {
        return [
            'simple pass' => [['user/index'], ['user/index'], [], 'url', 'or', true],
            'simple fail' => [['user/index'], ['user/edit'], [], 'url', 'or', false],
            'empty names' => [['user/index'], [], [], 'url', 'or', false],
            'empty rules' => [[], ['user/index'], [], 'url', 'or', false],
            'or relation' => [['user/add'], ['user/add', 'user/edit'], [], 'url', 'or', true],
            'and relation fail' => [['user/add'], ['user/add', 'user/edit'], [], 'url', 'and', false],
            'and relation pass' => [['user/add', 'user/edit'], ['user/add', 'user/edit'], [], 'url', 'and', true],
            'invalid relation' => [['user/add'], ['user/add'], [], 'url', 'xor', false],
            'url param pass' => [['user/index?type=admin'], ['user/index'], ['type' => 'admin'], 'url', 'or', true],
            'url param fail' => [['user/index?type=admin'], ['user/index'], ['type' => 'user'], 'url', 'or', false],
            'url param missing' => [['user/index?type=admin'], ['user/index'], [], 'url', 'or', false],
            'url param case insensitive' => [['user/index?type=admin'], ['user/index'], ['type' => 'ADMIN'], 'url', 'or', true],
            'multiple params' => [
                ['user/index?type=admin&state=on'],
                ['user/index'],
                ['type' => 'admin', 'state' => 'on', 'page' => 2],
                'url',
                'or',
                true,
            ],
            'multiple params partial' => [
                ['user/index?type=admin&state=on'],
                ['user/index'],
                ['type' => 'admin'],
                'url',
                'or',
                false,
            ],
            'nested params' => [
                ['user/index?filter[type]=admin'],
                ['user/index'],
                ['filter' => ['type' => 'admin', 'state' => 'on']],
                'url',
                'or',
                true,
            ],
            'url mode off ignores query' => [
                ['user/index?type=admin'],
                ['user/index'],
                [],
                'node',
                'or',
                true,
            ],
            'rule with empty query' => [['user/index?'], ['user/index'], [], 'url', 'or', true],
        ];
    }

    public function testLowercaseDisabled(): void
    {
        self::assertFalse($this->matcher->matches(
            ['user/index?type=admin'],
            ['user/index'],
            ['type' => 'ADMIN'],
            'url',
            'or',
            false
        ));

        self::assertTrue($this->matcher->matches(
            ['user/index?type=ADMIN'],
            ['user/index'],
            ['type' => 'ADMIN'],
            'url',
            'or',
            false
        ));
    }
}