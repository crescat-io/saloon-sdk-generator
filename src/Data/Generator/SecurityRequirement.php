<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

class SecurityRequirement
{
    /**
     * @param  string[]  $scopes
     */
    public function __construct(
        public readonly ?string $name,
        public readonly array $scopes = [],
    ) {
    }
}
