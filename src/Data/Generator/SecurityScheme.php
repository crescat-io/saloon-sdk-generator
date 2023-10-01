<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

class SecurityScheme
{
    const TYPE_API_KEY = 'apiKey';
    const TYPE_HTTP = 'http';
    const TYPE_MUTUAL_TLS = 'mutualTLS';
    const TYPE_OAUTH2 = 'oauth2';
    const TYPE_OPEN_ID_CONNECT = 'openIdConnect';

    const IN_QUERY = 'query';
    const IN_HEADER = 'header';
    const IN_COOKIE = 'cookie';

    public function __construct(
        public readonly string  $type,
        public readonly ?string $name = null,
        public readonly ?string $in = null,
        public readonly ?string $scheme = null,
        public readonly ?string $description = null,
        public readonly ?string $bearerFormat = null,
        public readonly ?object $flows = null,
        public readonly ?string $openIdConnectUrl = null
    )
    {
    }
}
