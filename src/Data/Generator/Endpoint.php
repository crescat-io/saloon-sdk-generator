<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Data\Generator;

class Endpoint
{
    /**
     * @param  Parameter[]  $queryParameters
     * @param  Parameter[]  $pathParameters
     */
    public function __construct(
        public string $name,
        public Method $method,
        public array $pathSegments,

        public ?string $collection,
        public ?array $responses,

        public ?string $description = null,

        public array $queryParameters = [],
        public array $headerParameters = [],
        public array $pathParameters = [],
        public ?Schema $bodySchema = null,
    ) {
    }

    /**
     * @return Parameter[]
     */
    public function allParameters(): array
    {
        return [
            ...$this->pathParameters,
            ...$this->queryParameters,
            ...$this->headerParameters,
        ];
    }

    public function pathAsString(): string
    {
        return implode('/', $this->pathSegments);
    }
}
