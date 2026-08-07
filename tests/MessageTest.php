<?php

use BogdanKharchenko\Graphiti\Data\Message;
use BogdanKharchenko\Graphiti\Data\RoleType;
use Carbon\CarbonImmutable;

it('serializes only the fields that are set', function () {
    $message = new Message(content: 'hello', role: 'transcript');

    expect($message->toArray())->toBe([
        'content' => 'hello',
        'role' => 'transcript',
        'role_type' => 'user',
    ]);
});

it('serializes timestamps as ISO 8601 and honors the role type', function () {
    $message = new Message(
        content: 'noted',
        role: 'assistant',
        roleType: RoleType::Assistant,
        timestamp: CarbonImmutable::parse('2026-08-06 13:00:00', 'UTC'),
        uuid: 'episode-42',
    );

    expect($message->toArray())->toBe([
        'content' => 'noted',
        'role' => 'assistant',
        'role_type' => 'assistant',
        'timestamp' => '2026-08-06T13:00:00+00:00',
        'uuid' => 'episode-42',
    ]);
});
