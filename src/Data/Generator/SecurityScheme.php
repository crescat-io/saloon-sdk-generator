<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

use cebe\openapi\spec\OAuthFlows;

class SecurityScheme
{
    public function __construct(
        public readonly SecuritySchemeType $type,

        // Only applies for apiKey
        public readonly ?string $name = null,
        public readonly ?ApiKeyLocation $in = null,

        // only applies for http
        public readonly ?string $scheme = null,
        public readonly ?string $bearerFormat = null,

        // Applies to all
        public readonly ?string $description = null,

        // Only applies for oauth2
        // See: https://swagger.io/specification/?sbsearch=tls#oauth-flows-object
        public readonly ?OAuthFlows $flows = null,

        // Only applies for openIdConnect
        public readonly ?string $openIdConnectUrl = null
    ) {
    }
}
