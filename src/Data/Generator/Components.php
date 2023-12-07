<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

class Components
{
    /**
     * @param  SecurityScheme[]  $securitySchemes
     */
    public function __construct(
        public readonly array $schemas = [],
        public readonly array $responses = [],
        public readonly array $parameters = [],
        public readonly array $examples = [],
        public readonly array $requestBodies = [],
        public readonly array $headers = [],
        public readonly array $securitySchemes = [],
        public readonly array $links = [],
        public readonly array $callbacks = [],
        public readonly array $pathItems = []
    ) {
    }
}
