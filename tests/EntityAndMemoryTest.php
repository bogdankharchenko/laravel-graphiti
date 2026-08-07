<?php

use BogdanKharchenko\Graphiti\Data\Fact;
use BogdanKharchenko\Graphiti\Data\Message;
use BogdanKharchenko\Graphiti\Facades\Graphiti;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('pre-seeds entity nodes', function () {
    Http::fake(['graphiti.test/entity-node' => Http::response(['success' => true])]);

    Graphiti::addEntityNode('team-eng', 'person-42', 'Bogdan Kharchenko', 'Engineer at InterNACHI');

    Http::assertSent(fn (Request $request) => $request->url() === 'http://graphiti.test/entity-node'
        && $request['uuid'] === 'person-42'
        && $request['group_id'] === 'team-eng'
        && $request['name'] === 'Bogdan Kharchenko'
        && $request['summary'] === 'Engineer at InterNACHI');
});

it('fetches and deletes a single entity edge', function () {
    Http::fake(['graphiti.test/entity-edge/fact-1' => Http::response([
        'uuid' => 'fact-1',
        'name' => 'OWNS',
        'fact' => 'Marcus Webb owns the metering work.',
        'valid_at' => '2026-08-03T16:00:00Z',
        'invalid_at' => null,
        'created_at' => '2026-08-06T00:00:00Z',
        'expired_at' => null,
    ])]);

    $fact = Graphiti::entityEdge('fact-1');

    expect($fact)->toBeInstanceOf(Fact::class)
        ->and($fact->fact)->toBe('Marcus Webb owns the metering work.');

    Graphiti::deleteEntityEdge('fact-1');

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === 'http://graphiti.test/entity-edge/fact-1');
});

it('retrieves memory relevant to conversation messages', function () {
    Http::fake(['graphiti.test/get-memory' => Http::response(['facts' => [[
        'uuid' => 'fact-1',
        'name' => 'OWNS',
        'fact' => 'Marcus Webb owns the metering work.',
        'valid_at' => '2026-08-03T16:00:00Z',
        'invalid_at' => null,
        'created_at' => '2026-08-06T00:00:00Z',
        'expired_at' => null,
    ]]])]);

    $facts = Graphiti::getMemory('team-eng', [
        new Message(content: 'who should I ask about metering?', role: 'Bogdan'),
    ], maxFacts: 5);

    expect($facts)->toHaveCount(1)
        ->and($facts[0]->isCurrent())->toBeTrue();

    Http::assertSent(fn (Request $request) => $request['group_id'] === 'team-eng'
        && $request['center_node_uuid'] === null
        && $request['messages'][0]['content'] === 'who should I ask about metering?'
        && $request['max_facts'] === 5);
});

it('clears the entire graph', function () {
    Http::fake(['graphiti.test/clear' => Http::response(['success' => true])]);

    Graphiti::clearGraph();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'http://graphiti.test/clear');
});
