<?php

declare(strict_types=1);

namespace youvision\store\auth\tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use youvision\store\auth\support\ConditionEvaluator;

final class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ConditionEvaluator();
    }

    /**
     * @param array<string, mixed> $user
     */
    #[DataProvider('conditionProvider')]
    public function testEvaluate(string $condition, array $user, bool $expected): void
    {
        self::assertSame($expected, $this->evaluator->evaluate($condition, $user));
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: bool}>
     */
    public static function conditionProvider(): array
    {
        return [
            'empty condition' => ['', ['score' => 1], false],
            'less than pass' => ['{score}<100', ['score' => 99], true],
            'less than fail' => ['{score}<100', ['score' => 100], false],
            'greater or equal' => ['{score}>=100', ['score' => 100], true],
            'spaces around operator' => ['{score} = 100', ['score' => 100], true],
            'not equal' => ['{status}!=0', ['status' => 1], true],
            'and operator' => ['{score}>=100 and {score}<200', ['score' => 150], true],
            'and operator fail' => ['{score}>=100 and {score}<200', ['score' => 200], false],
            'or operator' => ['{score}<100 or {score}>=200', ['score' => 200], true],
            'and has higher priority' => ['{a}=1 or {b}=2 and {c}=3', ['a' => 1, 'b' => 0, 'c' => 0], true],
            'quoted string' => ["{name}='a or b'", ['name' => 'a or b'], true],
            'double quoted string' => ['{name}="and"', ['name' => 'and'], true],
            'quoted true is string' => ["{flag}='true'", ['flag' => 'true'], true],
            'boolean true' => ['{enabled}=true', ['enabled' => true], true],
            'boolean false' => ['{enabled}=false', ['enabled' => false], true],
            'null value' => ['{deleted_at}=null', ['deleted_at' => null], true],
            'field exists' => ['{avatar}', ['avatar' => 'a.png'], true],
            'field empty' => ['{avatar}', ['avatar' => ''], false],
            'missing field' => ['{score}>1', ['level' => 1], false],
            'invalid field expression' => ['{score} in (1,2)', ['score' => 1], false],
            'parentheses not supported' => ['({score}>1)', ['score' => 2], false],
            'reverse comparison not supported' => ['100<{score}', ['score' => 200], false],
            'missing operator value' => ['{score}>', ['score' => 1], false],
            'unclosed field' => ['{score>1', ['score' => 1], false],
            'unclosed quote' => ["{name}='abc", ['name' => 'abc'], false],
            'trailing tokens' => ['{score}>1 {age}>1', ['score' => 2, 'age' => 2], false],
            'string comparison' => ["{role}='admin'", ['role' => 'admin'], true],
            'numeric string' => ['{level}>=10', ['level' => '10'], true],
            'decimal value' => ['{amount}<10.5', ['amount' => 10.4], true],
            'negative value' => ['{balance}>-10', ['balance' => -5], true],
            'scientific value' => ['{size}>1e3', ['size' => 2000], true],
            'chinese field' => ['{等级}>1', ['等级' => 2], true],
        ];
    }

    public function testEscapedQuoteInValue(): void
    {
        self::assertTrue($this->evaluator->evaluate("{name}='it\\'s'", ['name' => "it's"]));
    }

    public function testFields(): void
    {
        self::assertSame([], $this->evaluator->fields(''));
        self::assertSame(['score', 'status'], $this->evaluator->fields('{score}>=1 and {status}=1'));
        self::assertSame(['name'], $this->evaluator->fields("{name}='a and b'"));
        self::assertSame(['等级'], $this->evaluator->fields('{等级}>1'));
    }
}