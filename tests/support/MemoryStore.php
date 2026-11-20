<?php

declare(strict_types=1);

namespace youvision\store\admin\tests\support;

use youvision\store\admin\contract\Store;

final class MemoryStore implements Store
{
    /**
     * @var array<string, array<string, mixed>>
     */
    public array $data = [];

    /**
     * @var array<int, string>
     */
    public array $keys = [];

    public function get(string $key): ?array
    {
        return $this->data[$key] ?? null;
    }

    public function put(string $key, array $value, int $ttl = 0): void
    {
        $this->data[$key] = $value;

        if (!in_array($key, $this->keys, true)) {
            $this->keys[] = $key;
        }
    }

    public function forget(string $key = '', string $prefix = ''): void
    {
        if ($key !== '') {
            unset($this->data[$key]);
            $this->keys = array_values(array_diff($this->keys, [$key]));

            return;
        }

        foreach ($this->keys as $item) {
            if ($prefix !== '' && !str_starts_with($item, $prefix)) {
                continue;
            }

            unset($this->data[$item]);
        }

        $this->keys = array_values(array_filter(
            $this->keys,
            static fn (string $item): bool => $prefix !== '' && !str_starts_with($item, $prefix)
        ));
    }
}