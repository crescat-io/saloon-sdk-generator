<?php

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\BaseUrl;
use Crescat\SaloonSdkGenerator\Data\Generator\Components;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\SecurityRequirement;
use Crescat\SaloonSdkGenerator\Data\Generator\SecurityScheme;
use Crescat\SaloonSdkGenerator\Data\Generator\ServerParameter;
use Crescat\SaloonSdkGenerator\Generators\ConnectorGenerator;

test('Constructor', function () {

    $generator = new ConnectorGenerator(new Config('MyConnector', 'VendorName'));
    $apiSpec = getApiSpec();
    $phpFile = $generator->generate($apiSpec);
    $class = $phpFile->getNamespaces()['VendorName']->getClasses()['MyConnector'];

    expect($class)->toBeInstanceOf(\Nette\PhpGenerator\ClassType::class);

    $constructor = $class->getMethods()['__construct'];
    expect($constructor)->toBeInstanceOf(\Nette\PhpGenerator\Method::class)
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
    $generator = new ConnectorGenerator(new Config('MyConnector', 'VendorName'));
    $apiSpec = getApiSpec();
    $phpFile = $generator->generate($apiSpec);
    $class = $phpFile->getNamespaces()['VendorName']->getClasses()['MyConnector'];

    $resolveBaseUrl = $class->getMethods()['resolveBaseUrl'];
    expect($resolveBaseUrl->getParameters())->toBeEmpty()
        ->and($resolveBaseUrl->getBody())->toBe("return \"https://api-{\$this->region}.example.com/v1/\";");
});

function getApiSpec(): ApiSpecification
{
    return new ApiSpecification(
        name: 'ApiName',
        description: 'Example API',
        baseUrl: new BaseUrl(
            'https://api-{region}.example.com/v1/',
            [new ServerParameter('region', 'eu')]
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
                )
            ]
        ),
        endpoints: []
    );
}
