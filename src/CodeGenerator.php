<?php

namespace Crescat\SaloonSdkGenerator;

use Crescat\SaloonSdkGenerator\Contracts\Generator;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\GeneratedCode;
use Crescat\SaloonSdkGenerator\Generators\BaseResourceGenerator;
use Crescat\SaloonSdkGenerator\Generators\ConnectorGenerator;
use Crescat\SaloonSdkGenerator\Generators\DtoGenerator;
use Crescat\SaloonSdkGenerator\Generators\RequestGenerator;
use Crescat\SaloonSdkGenerator\Generators\ResourceGenerator;

class CodeGenerator
{
    public function __construct(
        protected Config $config,
        protected ?Generator $requestGenerator = null,
        protected ?Generator $resourceGenerator = null,
        protected ?Generator $dtoGenerator = null,
        protected ?Generator $connectorGenerator = null,
        protected ?Generator $baseResourceGenerator = null,
    ) {
        $this->requestGenerator ??= new RequestGenerator($config);
        $this->resourceGenerator ??= new ResourceGenerator($config);
        $this->dtoGenerator ??= new DtoGenerator($config);
        $this->connectorGenerator ??= new ConnectorGenerator($config);
        $this->baseResourceGenerator ??= new BaseResourceGenerator($config);
    }

    public function run(ApiSpecification $specification): GeneratedCode
    {
        return new GeneratedCode(
            requestClasses: $this->requestGenerator->generate($specification),
            resourceClasses: $this->resourceGenerator->generate($specification),
            dtoClasses: $this->dtoGenerator->generate($specification),
            connectorClass: $this->connectorGenerator->generate($specification),
            resourceBaseClass: $this->baseResourceGenerator->generate($specification),
        );
    }
}
