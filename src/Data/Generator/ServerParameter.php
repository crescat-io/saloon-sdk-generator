<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

class ServerParameter
{
    public function __construct(
        public readonly string  $name,
        public readonly string  $default,
        public readonly ?string $description = null
    )
    {
    }
}
