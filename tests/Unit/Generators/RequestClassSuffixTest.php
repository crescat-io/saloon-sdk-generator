<?php

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\BaseUrl;
use Crescat\SaloonSdkGenerator\Data\Generator\Components;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Method;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Generators\RequestGenerator;

test('it suffixes request classes when configured', function () {
    $generator = new RequestGenerator(new Config(
        connectorName: 'MyConnector',
        namespace: 'VendorName',
        suffixRequestClasses: true
    ));

    $spec = new ApiSpecification(
        name: 'ApiName',
        description: 'Example API',
        baseUrl: new BaseUrl(
            url: 'https://api.example.com/v1/',
            parameters: []
        ),
        securityRequirements: [],
        components: new Components,
        endpoints: [
            new Endpoint(
                name: 'getUser',
                method: Method::GET,
                pathSegments: ['users', ':id'],
                collection: 'Users',
                response: null,
                description: 'Get user',
                queryParameters: [],
                pathParameters: [new Parameter('int', false, 'id', 'ID of the user')],
                bodyParameters: []
            ),
        ]
    );

    $phpFiles = $generator->generate($spec);
    $class = $phpFiles[0]->getNamespaces()['VendorName\\Requests\\Users']->getClasses()['GetUserRequest'];

    expect($class->getName())->toBe('GetUserRequest');
});
