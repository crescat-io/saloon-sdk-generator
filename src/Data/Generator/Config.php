<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

class Config
{
    /**
     * @param  string|null  $connectorName  The name of the connector class.
     * @param  string|null  $namespace  The main namespace for the generated SDK.
     * @param  string|null  $resourceNamespaceSuffix  The suffix for the resource namespace.
     * @param  string|null  $requestNamespaceSuffix  The suffix for the request namespace.
     * @param  string|null  $dtoNamespaceSuffix  The suffix for the DTO namespace.
     * @param  string|null  $factoryNamespaceSuffix  The suffix for the Factory namespace.
     * @param  string|null  $fallbackResourceName  The default name to use for resources if none could be inferred from the specification.
     * @param  array  $ignoredQueryParams  List of query parameters that should be ignored.
     * @param  array  $ignoredBodyParams  List of body parameters that should be ignored.
     * @param  array  $ignoredHeaderParams  List of header parameters that should be ignored.
     * @param  array  $extra  Additional configuration for custom code generators.
     */
    public function __construct(
        public readonly ?string $connectorName,
        public readonly ?string $namespace,
        public readonly ?string $resourceNamespaceSuffix = 'Resource',
        public readonly ?string $requestNamespaceSuffix = 'Requests',
        public readonly ?string $dtoNamespaceSuffix = 'Dto',
        public readonly ?string $factoryNamespaceSuffix = 'Factories',
        public readonly ?string $fallbackResourceName = 'Misc',
        public readonly array $ignoredQueryParams = [],
        public readonly array $ignoredBodyParams = [],
        public readonly array $ignoredHeaderParams = ['Authorization', 'Content-Type', 'Accept', 'Accept-Language', 'User-Agent'],
        public readonly array $extra = [],

    ) {}
}
