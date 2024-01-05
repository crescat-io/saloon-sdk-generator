<?php

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\BaseUrl;
use Crescat\SaloonSdkGenerator\Data\Generator\Components;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\SecurityRequirement;
use Crescat\SaloonSdkGenerator\Data\Generator\ServerParameter;
use Crescat\SaloonSdkGenerator\Generators\ConnectorGenerator;

beforeEach(function () {
    $this->generator = new ConnectorGenerator(new Config(
        connectorName: 'Client',
        namespace: 'Crescat'
    ));
});

it('Supports server parameter in the baseUrl', function () {
    $phpFile = $this->generator->generate(new ApiSpecification(
        name: 'Example',
        description: 'Example API',
        baseUrl: new BaseUrl(
            url: 'https://api-{region}.example.com/v1/',
            parameters: [
                new ServerParameter('region', 'eu'),
            ]
        ),
        securityRequirements: [
            new SecurityRequirement('X-Auth-Token'),
        ],
        components: new Components(
            securitySchemes: []
        ),
        endpoints: []
    ));
    $class = $phpFile->getNamespaces()['Crescat']->getClasses()['Client'];

    $constructor = $class->getMethods()['__construct'];
    $regionParam = $constructor->getParameter('region');
    expect($regionParam)->not->toBeNull()
        ->and($regionParam->getType())->toBe('string')
        ->and($regionParam->isNullable())->toBeFalse();

    $resolveBaseUrl = $class->getMethods()['resolveBaseUrl'];
    expect($resolveBaseUrl->getParameters())->toBeEmpty()
        ->and($resolveBaseUrl->getBody())->toBe('return "https://api-{$this->region}.example.com/v1/";');
});

it('Supports multiple server parameters in the baseUrl', function () {
    $phpFile = $this->generator->generate(new ApiSpecification(
        name: 'Example',
        description: 'Example API',
        baseUrl: new BaseUrl(
            url: 'https://api-{region}-{environment}.example.com/v1/',
            parameters: [
                new ServerParameter('region', 'eu'),
                new ServerParameter('environment', 'staging'),
            ]
        ),
        securityRequirements: [
            new SecurityRequirement('X-Auth-Token'),
        ],
        components: new Components(
            securitySchemes: []
        ),
        endpoints: []
    ));
    $class = $phpFile->getNamespaces()['Crescat']->getClasses()['Client'];

    $constructor = $class->getMethods()['__construct'];
    $regionParam = $constructor->getParameter('region');
    expect($regionParam)->not->toBeNull()
        ->and($regionParam->getType())->toBe('string')
        ->and($regionParam->isNullable())->toBeFalse();

    $resolveBaseUrl = $class->getMethods()['resolveBaseUrl'];
    expect($resolveBaseUrl->getParameters())->toBeEmpty()
        ->and($resolveBaseUrl->getBody())->toBe('return "https://api-{$this->region}-{$this->environment}.example.com/v1/";');
});
