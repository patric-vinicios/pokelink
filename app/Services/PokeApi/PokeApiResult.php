<?php

namespace App\Services\PokeApi;

final readonly class PokeApiResult
{
    public function __construct(
        public PokeApiStatus $status,
        public ?array $data,
        public string $resource,
        public ?string $identifier = null,
    ) {}

    public function successful(): bool
    {
        return $this->status === PokeApiStatus::Success;
    }

    public function notFound(): bool
    {
        return $this->status === PokeApiStatus::NotFound;
    }

    public function unavailable(): bool
    {
        return $this->status === PokeApiStatus::Unavailable;
    }

    public function data(): ?array
    {
        return $this->data;
    }

    /**
     * @return array{status: string, data: array|null, resource: string, identifier: string|null}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'data' => $this->data,
            'resource' => $this->resource,
            'identifier' => $this->identifier,
        ];
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            status: PokeApiStatus::from($payload['status']),
            data: $payload['data'] ?? null,
            resource: $payload['resource'],
            identifier: $payload['identifier'] ?? null,
        );
    }
}
