<?php

namespace Crescat\SaloonSdkGenerator;

use Crescat\SaloonSdkGenerator\Contracts\Generator;
use Crescat\SaloonSdkGenerator\Contracts\PostProcessor;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\GeneratedCode;
use Crescat\SaloonSdkGenerator\Generators\ConnectorGenerator;
use Crescat\SaloonSdkGenerator\Generators\DtoGenerator;
use Crescat\SaloonSdkGenerator\Generators\RequestGenerator;
use Crescat\SaloonSdkGenerator\Generators\ResourceGenerator;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class CodeGenerator
{
    protected array $generators = [];

    protected array $postProcessors = [];

    public function __construct(
        protected Config $config,
        protected ?Generator $requestGenerator = null,
        protected ?Generator $resourceGenerator = null,
        protected ?Generator $dtoGenerator = null,
        protected ?Generator $connectorGenerator = null,
        ?array $additionalGenerators = [],
        ?array $postProcessors = [],
    ) {
        // Register default generators.
        $this->requestGenerator ??= new RequestGenerator($config);
        $this->resourceGenerator ??= new ResourceGenerator($config);
        $this->dtoGenerator ??= new DtoGenerator($config);
        $this->connectorGenerator ??= new ConnectorGenerator($config);

        // Register additional generators and post processors
        $this->registerGenerators($additionalGenerators);
        $this->registerPostProcessors($postProcessors);
    }

    public function registerPostProcessor(PostProcessor $postProcessor): static
    {
        $this->postProcessors[] = $postProcessor;

        return $this;
    }

    public function registerPostProcessors(?array $postProcessors = []): static
    {
        foreach ($postProcessors as $postProcessor) {
            if (! $postProcessor instanceof PostProcessor) {
                throw new InvalidArgumentException(sprintf('Post processor must implement %s but got %s', PostProcessor::class, get_class($postProcessor)));
            }

            $this->registerPostProcessor($postProcessor);
        }

        return $this;
    }

    public function registerGenerator(Generator $generator): static
    {
        $this->generators[] = $generator;

        return $this;
    }

    public function registerGenerators(?array $generators = []): static
    {
        foreach ($generators as $generator) {
            if (! $generator instanceof Generator) {
                throw new InvalidArgumentException(sprintf('Generator must implement %s but got %s', PostProcessor::class, get_class($generator)));
            }

            $this->registerGenerator($generator);
        }

        return $this;
    }

    public function run(ApiSpecification $specification): GeneratedCode
    {
        // TODO: Pre-processors if needed in the future

        $generatedCode = new GeneratedCode(
            requestClasses: $this->requestGenerator->generate($specification),
            resourceClasses: $this->resourceGenerator->generate($specification),
            dtoClasses: $this->dtoGenerator->generate($specification),
            connectorClass: $this->connectorGenerator->generate($specification),
            additionalFiles: $this->runGenerators($specification),
        );

        $additionalFiles = Arr::collapse($this->runPostProcessors($specification, $generatedCode));

        foreach ($additionalFiles as $additionalFile) {
            $generatedCode->addAdditionalFile($additionalFile);
        }

        return $generatedCode;
    }

    protected function runGenerators(ApiSpecification $specification): array
    {
        return collect($this->generators)
            ->each(fn (Generator $generator) => $generator->setConfig($this->config))
            ->map(fn (Generator $generator) => $generator->generate($specification))
            ->toArray();
    }

    protected function runPostProcessors(ApiSpecification $specification, GeneratedCode $generatedCode): array
    {
        return collect($this->postProcessors)
            ->map(fn (PostProcessor $postProcessor) => $postProcessor->process($this->config, $specification, $generatedCode))
            ->toArray();
    }

    protected function runCodeGenerators()
    {

    }
}
