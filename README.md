# Laravel Graphiti

A Laravel client for the [Graphiti](https://github.com/getzep/graphiti) REST
service — the temporal knowledge-graph engine that turns episodic content
(meeting transcripts, Slack threads, tickets) into extracted, deduplicated,
time-aware facts stored in Neo4j.

This package talks to the `zepai/graphiti` REST server. It does not talk to
Neo4j directly; for raw Cypher queries pair it with
[`laudis/neo4j-php-client`](https://github.com/neo4j-php/neo4j-php-client).

## Installation

```sh
composer require bogdankharchenko/laravel-graphiti
```

Set the service URL in `.env` (config publishes via
`php artisan vendor:publish --tag=graphiti-config` if you need more):

```
GRAPHITI_URL=http://localhost:8000
```

## Ingesting

Each `Message` becomes an episode: a unit of source content the service
extracts entities and facts from. Ingestion is asynchronous server-side —
the call returns as soon as the messages are queued.

```php
use BogdanKharchenko\Graphiti\Data\Message;
use BogdanKharchenko\Graphiti\Facades\Graphiti;

Graphiti::addMessages('team-eng', [
    new Message(
        content: $transcriptChunk,          // "Speaker: text" lines
        role: 'transcript',
        name: 'Standup 2026-08-06 (part 1/3)',
        timestamp: $meetingStartedAt,       // real-world time, not ingestion time
        sourceDescription: 'Tuple call transcript',
    ),
]);
```

### Large backfills

`addMessages()` accepts any iterable and POSTs in chunks (default 50), so a
generator or `LazyCollection` streams an arbitrarily large backfill in
constant memory:

```php
$messages = LazyCollection::make(function () use ($calls) {
    foreach ($calls as $call) {
        yield from $this->toMessages($call);   // never all in memory at once
    }
});

$sent = Graphiti::addMessages('team-eng', $messages, chunkSize: 25);
```

## Searching

Hybrid semantic + keyword search over facts. Facts carry a validity window:
a fact with `invalidAt` set has been superseded by a later episode.

```php
$facts = Graphiti::search(
    'who owns metering?',
    groupIds: $user->allowedGroupIds(),   // always resolved server-side
    maxFacts: 15,
);

foreach ($facts as $fact) {
    $fact->fact;          // "Marcus Webb owns the metering work."
    $fact->isCurrent();   // false once superseded
    $fact->validAt;       // CarbonImmutable|null
    $fact->invalidAt;     // CarbonImmutable|null
}
```

## Everything else

The client covers the full REST surface of the Graphiti server:

```php
Graphiti::episodes('team-eng', lastN: 10);   // Collection<Episode>, provenance content included

// Pre-seed canonical entities (people, projects) so extraction resolves
// mentions against them instead of inventing near-duplicates:
Graphiti::addEntityNode('team-eng', uuid: $person->id, name: 'Bogdan Kharchenko');

// Agent memory: facts relevant to an in-flight conversation
Graphiti::getMemory('team-eng', $recentMessages, centerNodeUuid: null, maxFacts: 10);

Graphiti::entityEdge($uuid);                 // one Fact by uuid
Graphiti::deleteEntityEdge($uuid);
Graphiti::deleteEpisode($uuid);
Graphiti::deleteGroup('dm-alice-bob');       // e.g. offboarding, data deletion requests
Graphiti::clearGraph();                      // wipes EVERYTHING — dev/test only
Graphiti::healthy();                         // bool, never throws
```

## Pagination — the honest note

The Graphiti REST API has **no cursors**. `search()` is capped by `maxFacts`
(results are relevance-ranked — ask for what you intend to use) and
`episodes()` returns the most recent `lastN`. Both are bounded windows, not
pages. For deep, offset-based listings query Neo4j directly with
`SKIP`/`LIMIT` via `laudis/neo4j-php-client`. Memory management on the write
path is covered by chunked, lazy ingestion (above).

## Errors and retries

Transient failures (connection errors, 5xx) are retried
(`GRAPHITI_RETRY_TIMES`, default 2) before a `GraphitiRequestException` is
thrown; 4xx responses fail fast. The exception exposes the `Response` for
inspection. All requests honor `GRAPHITI_TIMEOUT` (default 30s).

## Testing your app

The client uses Laravel's HTTP client, so `Http::fake()` works as usual:

```php
Http::fake(['*/search' => Http::response(['facts' => []])]);
```

## Running the package tests

```sh
composer install
composer test
```

Coverage is enforced at 100% in CI (`pest --coverage --min=100`). Locally it
needs a coverage driver (PCOV or Xdebug); without one installed, run it in a
container:

```sh
docker run --rm -v "$PWD":/app -w /app php:8.3-cli bash -c \
  "pecl install pcov && docker-php-ext-enable pcov && php vendor/bin/pest --coverage --min=100"
```
