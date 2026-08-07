<?php

namespace BogdanKharchenko\Graphiti;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use BogdanKharchenko\Graphiti\Data\Episode;
use BogdanKharchenko\Graphiti\Data\Fact;
use BogdanKharchenko\Graphiti\Data\Message;
use BogdanKharchenko\Graphiti\Exceptions\GraphitiRequestException;

class GraphitiClient
{
    public function __construct(
        protected readonly Factory $http,
        protected readonly string $baseUrl,
        protected readonly int $timeout = 30,
        protected readonly int $retryTimes = 2,
        protected readonly int $retrySleepMs = 200,
    ) {}

    /**
     * Queue messages for ingestion, POSTing in chunks of $chunkSize.
     * Returns the number of messages sent.
     *
     * @param iterable<Message> $messages
     */
    public function addMessages(string $groupId, iterable $messages, int $chunkSize = 50): int
    {
        $sent = 0;

        // LazyCollection::make() rejects raw generators; wrapping accepts any iterable.
        $stream = LazyCollection::make(function () use ($messages) {
            yield from $messages;
        });

        foreach ($stream->chunk($chunkSize) as $chunk) {
            $payload = $chunk->map(fn (Message $message) => $message->toArray())->values()->all();

            $this->post('/messages', ['group_id' => $groupId, 'messages' => $payload]);
            $sent += count($payload);
        }

        return $sent;
    }

    /**
     * Hybrid semantic + keyword search over facts, relevance-ranked and
     * capped at $maxFacts. The API offers no cursor.
     *
     * @param list<string> $groupIds
     * @return Collection<int, Fact>
     */
    public function search(string $query, array $groupIds = [], int $maxFacts = 10): Collection
    {
        $payload = ['query' => $query, 'max_facts' => $maxFacts];

        if ($groupIds !== []) {
            $payload['group_ids'] = array_values($groupIds);
        }

        return $this->post('/search', $payload)
            ->collect('facts')
            ->map(fn (array $fact) => Fact::fromArray($fact));
    }

    /**
     * The most recent $lastN episodes in a group, oldest first, including
     * their full source content.
     *
     * @return Collection<int, Episode>
     */
    public function episodes(string $groupId, int $lastN = 10): Collection
    {
        return $this->get('/episodes/'.rawurlencode($groupId), ['last_n' => $lastN])
            ->collect()
            ->map(fn (array $episode) => Episode::fromArray($episode));
    }

    /**
     * Pre-seed a canonical entity so extraction resolves mentions against
     * it instead of creating near-duplicate nodes.
     */
    public function addEntityNode(string $groupId, string $uuid, string $name, string $summary = ''): void
    {
        $this->post('/entity-node', [
            'uuid' => $uuid,
            'group_id' => $groupId,
            'name' => $name,
            'summary' => $summary,
        ]);
    }

    public function entityEdge(string $uuid): Fact
    {
        return Fact::fromArray($this->get('/entity-edge/'.rawurlencode($uuid))->json());
    }

    public function deleteEntityEdge(string $uuid): void
    {
        $this->delete('/entity-edge/'.rawurlencode($uuid));
    }

    /**
     * Facts relevant to a conversation's recent messages, optionally
     * centered on an entity node.
     *
     * @param iterable<Message> $messages
     * @return Collection<int, Fact>
     */
    public function getMemory(
        string $groupId,
        iterable $messages,
        ?string $centerNodeUuid = null,
        int $maxFacts = 10,
    ): Collection {
        return $this->post('/get-memory', [
            'group_id' => $groupId,
            'center_node_uuid' => $centerNodeUuid,
            'messages' => collect($messages)->map(fn (Message $message) => $message->toArray())->values()->all(),
            'max_facts' => $maxFacts,
        ])
            ->collect('facts')
            ->map(fn (array $fact) => Fact::fromArray($fact));
    }

    public function deleteEpisode(string $uuid): void
    {
        $this->delete('/episode/'.rawurlencode($uuid));
    }

    public function deleteGroup(string $groupId): void
    {
        $this->delete('/group/'.rawurlencode($groupId));
    }

    /**
     * Wipe the entire graph across all groups and rebuild indices.
     * Irreversible.
     */
    public function clearGraph(): void
    {
        $this->post('/clear', []);
    }

    public function healthy(): bool
    {
        try {
            return $this->get('/healthcheck')->successful();
        } catch (GraphitiRequestException) {
            return false;
        }
    }

    protected function post(string $uri, array $payload): Response
    {
        return $this->send(fn (PendingRequest $request) => $request->post($uri, $payload));
    }

    protected function get(string $uri, array $query = []): Response
    {
        return $this->send(fn (PendingRequest $request) => $request->get($uri, $query));
    }

    protected function delete(string $uri): Response
    {
        return $this->send(fn (PendingRequest $request) => $request->delete($uri));
    }

    /**
     * @param callable(PendingRequest): Response $callback
     */
    protected function send(callable $callback): Response
    {
        try {
            $response = $callback($this->pending());
        } catch (ConnectionException $exception) {
            throw GraphitiRequestException::fromConnectionFailure($exception);
        }

        if ($response->failed()) {
            throw GraphitiRequestException::fromResponse($response);
        }

        return $response;
    }

    protected function pending(): PendingRequest
    {
        $request = $this->http
            ->baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson();

        if ($this->retryTimes > 0) {
            // retry() counts total attempts, not retries.
            $request = $request->retry(
                $this->retryTimes + 1,
                $this->retrySleepMs,
                fn (mixed $exception) => $exception instanceof ConnectionException
                    || ($exception->response ?? null)?->serverError(),
                throw: false,
            );
        }

        return $request;
    }
}
