<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

// TODO: Make our own abstraction around the Openapi types.
class Components
{
    /**
     * @param  Schema[]|Reference[]|array|null  $schemas
     * @param  SecurityScheme[]  $securitySchemes
     */
    public function __construct(
        public readonly array $schemas = [],
        public readonly array $securitySchemes = [],
    ) {
    }
}
