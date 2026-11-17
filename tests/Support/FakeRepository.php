<?php

declare(strict_types=1);

namespace youvision\store\auth\tests\Support;

use youvision\store\auth\contract\Repository;

final class FakeRepository implements Repository
{
    /**
     * @var array<int, array<int, int>>
     */
    public array $roleIds = [];

    /**
     * @var array<int, array{name: string, condition?: string, type?: int, status?: int}>
     */
    public array $rules = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $users = [];

    /**
     * @var array<int, array<int, mixed>>
     */
    public array $roles = [];

    public int $roleIdCalls = 0;

    public int $ruleCalls = 0;

    public int $userCalls = 0;

    public ?int $lastType = null;

    /**
     * @var array<int, int>
     */
    public array $purgedUsers = [];

    public function purgeUser(int $uid): int
    {
        $this->purgedUsers[] = $uid;

        return 2;
    }

    public function roleIds(int $uid): array
    {
        $this->roleIdCalls++;

        return $this->roleIds[$uid] ?? [];
    }

    public function rulesForRoles(array $roleIds, int $type): array
    {
        $this->ruleCalls++;
        $this->lastType = $type;

        $rules = [];

        foreach ($this->rules as $rule) {
            $ruleType = (int) ($rule['type'] ?? 1);
            $status = (int) ($rule['status'] ?? 1);

            if ($status !== 1) {
                continue;
            }

            if ($type > 0 && $ruleType !== $type) {
                continue;
            }

            $rules[] = [
                'name' => (string) $rule['name'],
                'condition' => (string) ($rule['condition'] ?? ''),
                'type' => $ruleType,
            ];
        }

        return $rules;
    }

    public function user(int $uid): array
    {
        $this->userCalls++;

        return $this->users[$uid] ?? [];
    }

    public function roles(int $uid, string $field = ''): array
    {
        $roles = $this->roles[$uid] ?? [];

        if ($field === '') {
            return $roles;
        }

        return array_map(static fn (array $role) => $role[$field] ?? null, $roles);
    }
}