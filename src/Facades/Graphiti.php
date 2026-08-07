<?php

namespace BogdanKharchenko\Graphiti\Facades;

use Illuminate\Support\Facades\Facade;
use BogdanKharchenko\Graphiti\GraphitiClient;

/**
 * @method static int addMessages(string $groupId, iterable $messages, int $chunkSize = 50)
 * @method static \Illuminate\Support\Collection search(string $query, array $groupIds = [], int $maxFacts = 10)
 * @method static \Illuminate\Support\Collection episodes(string $groupId, int $lastN = 10)
 * @method static void addEntityNode(string $groupId, string $uuid, string $name, string $summary = '')
 * @method static \BogdanKharchenko\Graphiti\Data\Fact entityEdge(string $uuid)
 * @method static void deleteEntityEdge(string $uuid)
 * @method static \Illuminate\Support\Collection getMemory(string $groupId, iterable $messages, ?string $centerNodeUuid = null, int $maxFacts = 10)
 * @method static void deleteEpisode(string $uuid)
 * @method static void deleteGroup(string $groupId)
 * @method static void clearGraph()
 * @method static bool healthy()
 *
 * @see GraphitiClient
 */
class Graphiti extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GraphitiClient::class;
    }
}
