<?php

namespace BogdanKharchenko\Graphiti\Exceptions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RuntimeException;

class GraphitiRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?Response $response = null,
        ?ConnectionException $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $detail = $response->json('detail');

        return new self(
            sprintf(
                'Graphiti request failed with status %d%s',
                $response->status(),
                $detail ? ': '.json_encode($detail) : '',
            ),
            response: $response,
        );
    }

    public static function fromConnectionFailure(ConnectionException $exception): self
    {
        return new self(
            'Could not connect to the Graphiti service: '.$exception->getMessage(),
            previous: $exception,
        );
    }
}
