<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

class Endpoint
{
    /**
     * @param  Parameter[]  $queryParameters
     * @param  Parameter[]  $pathParameters
     * @param  Parameter[]  $bodyParameters
     */
    public function __construct(
        public string $name,
        public string $method,
        public array $pathSegments,

        public ?string $collection,
        public ?array $response,

        public ?string $description = null,

        public array $queryParameters = [],
        public array $pathParameters = [],
        public array $bodyParameters = [],
    ) {
    }

    /**
     * @return Parameter[]
     */
    public function allParameters(): array
    {
        return [
            ...$this->pathParameters,
            ...$this->bodyParameters,
            ...$this->queryParameters,
        ];
    }

    public function pathAsString(): string
    {
        return implode('/', $this->pathSegments);
    }
}
