<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

class Endpoint
{
    /**
     * @param  Parameter[]  $queryParameters
     * @param  Parameter[]  $pathParameters
     * @param  Parameter[]  $bodyParameters
     * @param  Parameter[]  $headerParameters
     */
    public function __construct(
        public string $name,
        public Method $method,
        public array $pathSegments,

        public ?string $collection,
        public ?array $response,

        public ?string $description = null,

        public array $queryParameters = [],
        public array $pathParameters = [],
        public array $bodyParameters = [],
        public array $headerParameters = [],
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
            ...$this->headerParameters,
        ];
    }

    public function pathAsString(): string
    {
        return implode('/', $this->pathSegments);
    }
}
