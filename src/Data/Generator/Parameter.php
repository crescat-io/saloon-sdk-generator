<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

class Parameter
{
    public function __construct(
        public string $type,
        public bool $nullable,
        public string $name,
        public ?string $description = null
    ) {
    }
}
