<?php

namespace BogdanKharchenko\Graphiti\Data;

use Carbon\CarbonImmutable;

/**
 * A fact edge between two entities. An invalidAt date means the fact
 * was superseded by a later episode.
 */
final class Fact
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $fact,
        public readonly ?CarbonImmutable $validAt,
        public readonly ?CarbonImmutable $invalidAt,
        public readonly ?CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $expiredAt,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'],
            name: $data['name'],
            fact: $data['fact'],
            validAt: isset($data['valid_at']) ? CarbonImmutable::parse($data['valid_at']) : null,
            invalidAt: isset($data['invalid_at']) ? CarbonImmutable::parse($data['invalid_at']) : null,
            createdAt: isset($data['created_at']) ? CarbonImmutable::parse($data['created_at']) : null,
            expiredAt: isset($data['expired_at']) ? CarbonImmutable::parse($data['expired_at']) : null,
        );
    }

    public function isCurrent(): bool
    {
        return $this->invalidAt === null;
    }
}
