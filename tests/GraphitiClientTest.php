<?php

use BogdanKharchenko\Graphiti\Data\Episode;
use BogdanKharchenko\Graphiti\Data\Fact;
use BogdanKharchenko\Graphiti\Data\Message;
use BogdanKharchenko\Graphiti\Exceptions\GraphitiRequestException;
use BogdanKharchenko\Graphiti\Facades\Graphiti;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('posts messages with a normalized payload', function () {
    Http::fake(['graphiti.test/messages' => Http::response(['success' => true], 202)]);

    $sent = Graphiti::addMessages('team-eng', [
        new Message(
            content: 'Bogdan Kharchenko: we ship on Tuesday',
            role: 'transcript',
            name: 'Standup (part 1/1)',
            timestamp: CarbonImmutable::parse('2026-08-06T13:00:00Z'),
            sourceDescription: 'Tuple call transcript',
        ),
    ]);

    expect($sent)->toBe(1);

    Http::assertSent(function (Request $request) {
        $message = $request['messages'][0];

        return $request->url() === 'http://graphiti.test/messages'
            && $request['group_id'] === 'team-eng'
            && $message['content'] === 'Bogdan Kharchenko: we ship on Tuesday'
            && $message['role'] === 'transcript'
            && $message['role_type'] === 'user'
            && $message['name'] === 'Standup (part 1/1)'
            && $message['timestamp'] === '2026-08-06T13:00:00+00:00'
            && $message['source_description'] === 'Tuple call transcript'
            && ! array_key_exists('uuid', $message);
    });
});

it('chunks large ingestions from a generator without materializing it', function () {
    Http::fake(['graphiti.test/messages' => Http::response(['success' => true], 202)]);

    $messages = function () {
        for ($i = 0; $i < 120; $i++) {
            yield new Message(content: "utterance {$i}", role: 'transcript');
        }
    };

    $sent = Graphiti::addMessages('team-eng', $messages(), chunkSize: 50);

    expect($sent)->toBe(120);

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request) => count($request['messages']) <= 50);
});

it('sends nothing when given no messages', function () {
    Http::fake();

    expect(Graphiti::addMessages('team-eng', []))->toBe(0);

    Http::assertNothingSent();
});

it('parses search results into facts with validity windows', function () {
    Http::fake(['graphiti.test/search' => Http::response(['facts' => [
        [
            'uuid' => 'fact-1',
            'name' => 'OWNS',
            'fact' => 'Marcus Webb owns the metering work.',
            'valid_at' => '2026-08-03T16:00:00Z',
            'invalid_at' => null,
            'created_at' => '2026-08-06T00:00:00Z',
            'expired_at' => null,
        ],
        [
            'uuid' => 'fact-2',
            'name' => 'OWNS',
            'fact' => 'Sarah Lin owns the metering work.',
            'valid_at' => '2026-07-21T16:00:00Z',
            'invalid_at' => '2026-08-03T16:00:00Z',
            'created_at' => '2026-08-06T00:00:00Z',
            'expired_at' => '2026-08-06T00:05:00Z',
        ],
    ]])]);

    $facts = Graphiti::search('who owns metering?', groupIds: ['team-eng'], maxFacts: 5);

    expect($facts)->toHaveCount(2)
        ->and($facts[0])->toBeInstanceOf(Fact::class)
        ->and($facts[0]->isCurrent())->toBeTrue()
        ->and($facts[1]->isCurrent())->toBeFalse()
        ->and($facts[1]->invalidAt->toIso8601ZuluString())->toBe('2026-08-03T16:00:00Z');

    Http::assertSent(fn (Request $request) => $request['query'] === 'who owns metering?'
        && $request['group_ids'] === ['team-eng']
        && $request['max_facts'] === 5);
});

it('omits group_ids from the search payload when empty', function () {
    Http::fake(['graphiti.test/search' => Http::response(['facts' => []])]);

    Graphiti::search('anything');

    Http::assertSent(fn (Request $request) => ! array_key_exists('group_ids', $request->data()));
});

it('fetches recent episodes with an explicit window', function () {
    Http::fake(['graphiti.test/episodes/*' => Http::response([
        [
            'uuid' => 'ep-1',
            'name' => 'Tuple call 2026-08-06 (part 1/5)',
            'group_id' => 'team-eng',
            'labels' => [],
            'created_at' => '2026-08-06T18:00:00Z',
            'source' => 'message',
            'source_description' => 'Tuple call transcript',
            'content' => 'Bogdan Kharchenko: morning',
            'valid_at' => '2026-08-06T13:00:00Z',
            'entity_edges' => [],
        ],
    ])]);

    $episodes = Graphiti::episodes('team-eng', lastN: 2);

    expect($episodes)->toHaveCount(1)
        ->and($episodes[0])->toBeInstanceOf(Episode::class)
        ->and($episodes[0]->groupId)->toBe('team-eng')
        ->and($episodes[0]->validAt->toIso8601ZuluString())->toBe('2026-08-06T13:00:00Z');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/episodes/team-eng')
        && str_contains($request->url(), 'last_n=2'));
});

it('deletes a group', function () {
    Http::fake(['graphiti.test/group/*' => Http::response(['success' => true])]);

    Graphiti::deleteGroup('dm-alice-bob');

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === 'http://graphiti.test/group/dm-alice-bob');
});

it('deletes an episode', function () {
    Http::fake(['graphiti.test/episode/*' => Http::response(['success' => true])]);

    Graphiti::deleteEpisode('ep-1');

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === 'http://graphiti.test/episode/ep-1');
});

it('throws after exhausting retries on persistent server errors', function () {
    Http::fake(['graphiti.test/*' => Http::response(null, 500)]);

    try {
        Graphiti::search('anything');
        $this->fail('Expected GraphitiRequestException');
    } catch (GraphitiRequestException $e) {
        expect($e->getMessage())->toContain('500');
    }

    Http::assertSentCount(3); // initial attempt + 2 retries
});

it('makes a single attempt when retries are disabled', function () {
    Http::fake(['graphiti.test/*' => Http::response(null, 500)]);

    $client = new \BogdanKharchenko\Graphiti\GraphitiClient(
        http: Http::getFacadeRoot(),
        baseUrl: 'http://graphiti.test',
        retryTimes: 0,
    );

    expect(fn () => $client->search('anything'))->toThrow(GraphitiRequestException::class);

    Http::assertSentCount(1);
});

it('throws a typed exception carrying the response on client errors', function () {
    Http::fake(['graphiti.test/*' => Http::response(['detail' => 'not found'], 404)]);

    try {
        Graphiti::search('anything');
        $this->fail('Expected GraphitiRequestException');
    } catch (GraphitiRequestException $e) {
        expect($e->getMessage())->toContain('404')
            ->and($e->response->status())->toBe(404);
    }

    Http::assertSentCount(1); // 4xx is not transient — no retries
});

it('retries transient server errors before succeeding', function () {
    Http::fakeSequence('graphiti.test/*')
        ->pushStatus(500)
        ->pushStatus(502)
        ->push(['facts' => []]);

    expect(Graphiti::search('anything'))->toBeEmpty();

    Http::assertSentCount(3);
});

it('wraps connection failures in the package exception', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    Graphiti::search('anything');
})->throws(GraphitiRequestException::class, 'Could not connect');

it('reports healthy when the service responds', function () {
    Http::fake(['graphiti.test/healthcheck' => Http::response(['status' => 'healthy'])]);

    expect(Graphiti::healthy())->toBeTrue();
});

it('reports unhealthy when the service is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    expect(Graphiti::healthy())->toBeFalse();
});
