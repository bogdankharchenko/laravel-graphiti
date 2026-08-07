<?php

namespace BogdanKharchenko\Graphiti\Data;

use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One unit of episodic content to ingest; the service extracts entities
 * and facts from it.
 */
final class Message implements Arrayable
{
    public function __construct(
        public readonly string $content,
        public readonly string $role,
        public readonly RoleType $roleType = RoleType::User,
        public readonly ?string $name = null,
        public readonly ?DateTimeInterface $timestamp = null,
        public readonly ?string $sourceDescription = null,
        public readonly ?string $uuid = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'content' => $this->content,
            'role' => $this->role,
            'role_type' => $this->roleType->value,
            'name' => $this->name,
            'timestamp' => $this->timestamp?->format(DateTimeInterface::ATOM),
            'source_description' => $this->sourceDescription,
            'uuid' => $this->uuid,
        ], fn (?string $value) => $value !== null);
    }
}
