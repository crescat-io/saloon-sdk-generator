<?php

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Components;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\SecurityRequirement;
use Crescat\SaloonSdkGenerator\Data\Generator\SecurityScheme;
use Crescat\SaloonSdkGenerator\Generators\ConnectorGenerator;

test('Constructor', function () {

    $generator = new ConnectorGenerator(new Config('MyConnector', 'VendorName'));

    $apiSpec = new ApiSpecification(
        name: 'ApiName',
        description: 'Example API',
        baseUrl: 'https://api.example.com/v1/',
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

    $phpFile = $generator->generate($apiSpec);

    $class = $phpFile->getNamespaces()['VendorName']->getClasses()['MyConnector'];
    expect($class)->toBeInstanceOf(\Nette\PhpGenerator\ClassType::class);

    $constructor = $class->getMethods()['__construct'];
    expect($constructor)->toBeInstanceOf(\Nette\PhpGenerator\Method::class)
        ->and($constructor->getParameters())->toHaveCount(1)
        ->and($constructor->getBody())->toBe("\$this->withTokenAuth(\$authToken);\n");

    $authTokenParam = $constructor->getParameter('authToken');
    expect($authTokenParam)->not->toBeNull()
        ->and($authTokenParam->getType())->toBe('string')
        ->and($authTokenParam->isNullable())->toBeFalse();
});
