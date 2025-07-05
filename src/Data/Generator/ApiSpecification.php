<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

class ApiSpecification
{
    /**
     * @param  ?string  $name
     * @param  ?string  $description
     * @param  ?BaseUrl  $baseUrl
     * @param  SecurityRequirement[]  $securityRequirements
     * @param  ?Components  $components
     * @param  Endpoint[]  $endpoints
     */
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?BaseUrl $baseUrl,
        public array $securityRequirements = [],
        public ?Components $components = null,
        public array $endpoints = [],
    ) {
    }
}
