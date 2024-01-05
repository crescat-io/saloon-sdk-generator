<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

class BaseUrl
{
    /**
     * @param  ServerParameter[]  $parameters
     */
    public function __construct(
        public readonly string $url,
        public readonly array $parameters = [],
    ) {
    }
}
