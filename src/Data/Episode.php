<?php

namespace BogdanKharchenko\Graphiti\Data;

use Carbon\CarbonImmutable;

/**
 * An ingested unit of source content, kept verbatim as provenance for
 * everything extracted from it.
 */
final class Episode
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $groupId,
        public readonly string $source,
        public readonly string $sourceDescription,
        public readonly string $content,
        public readonly ?CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $validAt,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'],
            name: $data['name'],
            groupId: $data['group_id'],
            source: $data['source'],
            sourceDescription: $data['source_description'],
            content: $data['content'],
            createdAt: isset($data['created_at']) ? CarbonImmutable::parse($data['created_at']) : null,
            validAt: isset($data['valid_at']) ? CarbonImmutable::parse($data['valid_at']) : null,
        );
    }
}
