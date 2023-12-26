<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

class ApiSpecification
{
    /**
     * @param  ?string  $name
     * @param  ?string  $description
     * @param  ?string  $baseUrl
     * @param  Endpoint[]  $endpoints
     * @param  Schema[]  $schemas
     */
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?string $baseUrl,
        public array $endpoints,
        public array $schemas = [],
    ) {
    }
}
