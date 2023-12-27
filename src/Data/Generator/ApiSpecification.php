<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Data\Generator;

class ApiSpecification
{
    /**
     * @param  ?string  $name
     * @param  ?string  $description
     * @param  ?string  $baseUrl
     * @param  Endpoint[]  $endpoints
     * @param  Schema[]  $schemas
     * @param  Schema[]  $responses
     */
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?string $baseUrl,
        public array $endpoints,
        public array $schemas = [],
        public array $responses = [],
    ) {
    }
}
