<?php

namespace App\Data\Generator;

class Parameter
{
    public function __construct(
        public string $type,
        public string $name,
        public ?string $description = null
    ) {
    }
}
