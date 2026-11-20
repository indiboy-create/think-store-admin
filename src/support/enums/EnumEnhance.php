<?php

declare(strict_types=1);

namespace youvision\store\admin\support\enums;

use think\facade\Lang;

/**
 * 枚举增强 trait，移植自 yuanzhihai/think-enum ^2.0。
 *
 * 使用 PHP 8.1+ BackedEnum，提供名称、值、比较、实例化和多语言描述能力。
 */
trait EnumEnhance
{
    public function name(): string
    {
        return $this->name;
    }

    public function value(): int|string
    {
        return $this->value;
    }

    public function description(string $localizationGroup = '*'): string
    {
        $fallback = ucfirst(str_replace('_', '', strtolower($this->name)));

        try {
            $langSet = (string) Lang::getLangSet();
            $filePath = app()->getAppPath() . 'lang/' . $langSet . '/' . $localizationGroup . '.php';

            if (!is_file($filePath)) {
                return $fallback;
            }

            $key = static::class . '.' . $this->value;

            if (Lang::has($key)) {
                return (string) Lang::get($key);
            }
        } catch (\Throwable) {
        }

        return $fallback;
    }

    public function is(\BackedEnum $enum): bool
    {
        return $this === $enum;
    }

    public function isNot(\BackedEnum $enum): bool
    {
        return !$this->is($enum);
    }

    /**
     * @param array<int, \BackedEnum> $enums
     */
    public function isAny(array $enums): bool
    {
        return in_array($this, $enums, true);
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(static::cases(), 'name');
    }

    /**
     * @return array<int, int|string>
     */
    public static function values(): array
    {
        return array_column(static::cases(), 'value');
    }

    public static function hasName(string $name, bool $strict = false): bool
    {
        return in_array($name, static::names(), $strict);
    }

    public static function hasValue(int|string $value, bool $strict = false): bool
    {
        return in_array($value, static::values(), $strict);
    }

    public static function fromName(string $name): static
    {
        foreach (static::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        throw new \ValueError($name . ' is not a valid backing name for enum "' . static::class . '"');
    }

    public static function fromValue(int|string $value): static
    {
        foreach (static::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        throw new \ValueError((string) $value . ' is not a valid backing value for enum "' . static::class . '"');
    }

    public static function guess(int|string $key): static
    {
        return match (true) {
            static::hasName((string) $key) => static::fromName((string) $key),
            static::hasValue($key) => static::fromValue($key),
            default => throw new \ValueError((string) $key . ' is illegal for enum "' . static::class . '"'),
        };
    }

    /**
     * @return array<int, array{name: string, value: int|string, description: string}>
     */
    public static function toArray(string $localizationGroup = 'enums'): array
    {
        return array_map(static fn (\BackedEnum $item): array => [
            'name' => $item->name,
            'value' => $item->value,
            'description' => $item->description($localizationGroup),
        ], static::cases());
    }

    /**
     * @return array<int|string, string>
     */
    public static function toSelectArray(string $localizationGroup = 'enums'): array
    {
        return array_reduce(static::toArray($localizationGroup), static function (array $carry, array $item): array {
            $carry[$item['value']] = $item['description'];

            return $carry;
        }, []);
    }
}