<?php

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\BaseUrl;
use Crescat\SaloonSdkGenerator\Data\Generator\Components;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\SecurityRequirement;
use Crescat\SaloonSdkGenerator\Data\Generator\SecurityScheme;
use Crescat\SaloonSdkGenerator\Data\Generator\ServerParameter;
use Crescat\SaloonSdkGenerator\Generators\ConnectorGenerator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;

beforeEach(function () {
    $this->generator = new ConnectorGenerator(new Config(
        connectorName: 'MyConnector',
        namespace: 'VendorName'
    )
    );

    $this->dummySpec = new ApiSpecification(
        name: 'ApiName',
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
            securitySchemes: [
                new SecurityScheme(
                    type: 'apiKey',
                    name: 'X-Auth-Token',
                    in: 'header',
                ),
            ]
        ),
        endpoints: []
    );
});

test('Constructor', function () {

    $phpFile = $this->generator->generate($this->dummySpec);
    $class = $phpFile->getNamespaces()['VendorName']->getClasses()['MyConnector'];

    expect($class)->toBeInstanceOf(ClassType::class);

    $constructor = $class->getMethods()['__construct'];
    expect($constructor)->toBeInstanceOf(Method::class)
        ->and($constructor->getParameters())->toHaveCount(2)
        ->and($constructor->getBody())->toBe("\$this->withTokenAuth(\$authToken);\n");

    $regionParam = $constructor->getParameter('region');
    expect($regionParam)->not->toBeNull()
        ->and($regionParam->getType())->toBe('string')
        ->and($regionParam->isNullable())->toBeFalse();

    $authTokenParam = $constructor->getParameter('authToken');
    expect($authTokenParam)->not->toBeNull()
        ->and($authTokenParam->getType())->toBe('string')
        ->and($authTokenParam->isNullable())->toBeFalse();
});

test('Resolve Base URL', function () {
    $phpFile = $this->generator->generate($this->dummySpec);
    $class = $phpFile->getNamespaces()['VendorName']->getClasses()['MyConnector'];

    $resolveBaseUrl = $class->getMethods()['resolveBaseUrl'];
    expect($resolveBaseUrl->getParameters())->toBeEmpty()
        ->and($resolveBaseUrl->getBody())->toBe('return "https://api-{$this->region}.example.com/v1/";');
});
