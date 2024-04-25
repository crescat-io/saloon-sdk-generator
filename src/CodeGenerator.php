<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator;

use Crescat\SaloonSdkGenerator\Contracts\FileHandler;
use Crescat\SaloonSdkGenerator\Contracts\Generator;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\GeneratedCode;
use Crescat\SaloonSdkGenerator\Exceptions\ParserNotRegisteredException;
use Crescat\SaloonSdkGenerator\FileHandlers\BasicFileHandler;
use Crescat\SaloonSdkGenerator\Generators\BaseDtoGenerator;
use Crescat\SaloonSdkGenerator\Generators\BaseRequestGenerator;
use Crescat\SaloonSdkGenerator\Generators\BaseResourceGenerator;
use Crescat\SaloonSdkGenerator\Generators\BaseResponseGenerator;
use Crescat\SaloonSdkGenerator\Generators\ConnectorGenerator;
use Crescat\SaloonSdkGenerator\Generators\DtoGenerator;
use Crescat\SaloonSdkGenerator\Generators\RequestGenerator;
use Crescat\SaloonSdkGenerator\Generators\ResourceGenerator;
use Crescat\SaloonSdkGenerator\Generators\ResponseGenerator;
use Crescat\SaloonSdkGenerator\Generators\SupportingFilesGenerator;

class CodeGenerator
{
    public function __construct(
        protected ?Config $config = null,
        protected ?Generator $requestGenerator = null,
        protected ?Generator $responseGenerator = null,
        protected ?Generator $resourceGenerator = null,
        protected ?Generator $dtoGenerator = null,
        protected ?Generator $connectorGenerator = null,
        protected ?Generator $baseRequestGenerator = null,
        protected ?Generator $baseResponseGenerator = null,
        protected ?Generator $baseResourceGenerator = null,
        protected ?Generator $baseDtoGenerator = null,
        protected ?Generator $supportingFilesGenerator = null,
        protected ?FileHandler $fileHandler = null,
    ) {
        $this->config = $config ?? Config::load();
        $this->requestGenerator ??= new RequestGenerator($config);
        $this->responseGenerator ??= new ResponseGenerator($config);
        $this->resourceGenerator ??= new ResourceGenerator($config);
        $this->dtoGenerator ??= new DtoGenerator($config);
        $this->connectorGenerator ??= new ConnectorGenerator($config);
        $this->baseRequestGenerator ??= new BaseRequestGenerator($config);
        $this->baseResponseGenerator ??= new BaseResponseGenerator($config);
        $this->baseResourceGenerator ??= new BaseResourceGenerator($config);
        $this->baseDtoGenerator ??= new BaseDtoGenerator($config);
        $this->supportingFilesGenerator ??= new SupportingFilesGenerator($config);
        $this->fileHandler ??= new BasicFileHandler($config);
    }

    /**
     * Run the generator and return the generated code.
     *
     * @param  string|ApiSpecification  $specification  The specification to generate code from. If a string is provided,
     *                                                  it is used as the path to a file containing the specification.
     *
     * @throws ParserNotRegisteredException
     */
    public function run(string|ApiSpecification $specification): GeneratedCode
    {
        if (is_string($specification)) {
            $specification = Factory::parse($this->config->type, $specification);
        }

        return new GeneratedCode(
            config: $this->config,
            requestClasses: $this->requestGenerator->generate($specification),
            responseClasses: $this->responseGenerator->generate($specification),
            resourceClasses: $this->resourceGenerator->generate($specification),
            dtoClasses: $this->dtoGenerator->generate($specification),
            connectorClass: $this->connectorGenerator->generate($specification),
            resourceBaseClass: $this->baseResourceGenerator->generate($specification),
            responseBaseClasses: $this->baseResponseGenerator->generate($specification),
            requestBaseClass: $this->baseRequestGenerator->generate($specification),
            dtoBaseClass: $this->baseDtoGenerator->generate($specification),
            supportingFiles: $this->supportingFilesGenerator->generate($specification),
            fileHandler: $this->fileHandler,
        );
    }
}
