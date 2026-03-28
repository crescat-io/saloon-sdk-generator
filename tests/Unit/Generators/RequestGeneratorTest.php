<?php

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\BaseUrl;
use Crescat\SaloonSdkGenerator\Data\Generator\Components;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Method;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Data\Generator\ServerParameter;
use Crescat\SaloonSdkGenerator\Generators\RequestGenerator;

beforeEach(function () {

    $this->generator = new RequestGenerator(new Config(
        connectorName: 'MyConnector',
        namespace: 'VendorName'
    ));

    $this->dummySpec = new ApiSpecification(
        name: 'ApiName',
        description: 'Example API',
        baseUrl: new BaseUrl(
            url: 'https://api-{region}.example.com/v1/',
            parameters: [
                new ServerParameter('region', 'eu'),
            ]
        ),
        securityRequirements: [],
        components: new Components,
        endpoints: [
            new Endpoint(
                name: 'getUser',
                method: Method::GET,
                pathSegments: ['users', ':user_id'],
                collection: 'Users',
                response: null,
                description: 'Get user',
                queryParameters: [
                    new Parameter('int', true, 'channel_id', 'Channel ID to use for channel-level data.'),
                ],
                pathParameters: [new Parameter('int', false, 'user_id', 'ID of the user')],
                bodyParameters: []
            ),
        ]
    );
});

test('Class properties', function () {
    $phpFiles = $this->generator->generate($this->dummySpec);

    $class = $phpFiles[0]->getNamespaces()['VendorName\Requests\Users']->getClasses()['GetUser'];

    $property = $class->getProperty('method');

    expect($property)->toBeInstanceOf(\Nette\PhpGenerator\Property::class)
        ->and($property->getName())->toBe('method')
        ->and($property->getType())->toBe('Saloon\Enums\Method')
        ->and($property->getValue()->__toString())->toBe('Method::GET');
});

test('Constructor', closure: function () {

    $phpFiles = $this->generator->generate($this->dummySpec);

    $class = $phpFiles[0]->getNamespaces()['VendorName\Requests\Users']->getClasses()['GetUser'];

    $constructor = $class->getMethods()['__construct'];
    expect($constructor)->toBeInstanceOf(\Nette\PhpGenerator\Method::class)
        ->and($constructor->getParameters())->toHaveCount(2);

    /** @var \Nette\PhpGenerator\PromotedParameter $userIdIdParam */
    $userIdIdParam = $constructor->getParameter('userIdId');
    expect($userIdIdParam->getName())->toBe('userIdId')
        ->and($userIdIdParam->getType())->toBe('int')
        ->and($userIdIdParam->getVisibility())->toBe('protected')
        ->and($userIdIdParam->isNullable())->toBeFalse();

    /** @var \Nette\PhpGenerator\PromotedParameter $channelIdParam */
    $channelIdParam = $constructor->getParameter('channelId');
    expect($channelIdParam->getName())->toBe('channelId')
        ->and($channelIdParam->getVisibility())->toBe('protected')
        ->and($channelIdParam->getType())->toBe('int')
        ->and($channelIdParam->hasDefaultValue())->toBeTrue()
        ->and($channelIdParam->getDefaultValue())->toBeNull()
        ->and($channelIdParam->isNullable())->toBeTrue();
});

test('Resolve endpoint', function () {
    $phpFiles = $this->generator->generate($this->dummySpec);

    $class = $phpFiles[0]->getNamespaces()['VendorName\Requests\Users']->getClasses()['GetUser'];
    $resolveEndpoint = $class->getMethods()['resolveEndpoint'];

    expect($resolveEndpoint)->toBeInstanceOf(\Nette\PhpGenerator\Method::class)
        ->and($resolveEndpoint->getReturnType())->toBe('string')
        ->and($resolveEndpoint->getBody())->toBe("return \"/users/{\$this->userIdId}\";\n");
});

test('Default Query', function () {
    $phpFiles = $this->generator->generate($this->dummySpec);

    $class = $phpFiles[0]->getNamespaces()['VendorName\Requests\Users']->getClasses()['GetUser'];
    $defaultQuery = $class->getMethods()['defaultQuery'];

    expect($defaultQuery)->toBeInstanceOf(\Nette\PhpGenerator\Method::class)
        ->and($defaultQuery->getName())->toBe('defaultQuery')
        ->and($defaultQuery->getReturnType())->toBe('array')
        ->and($defaultQuery->getBody())->toBe("return array_filter(['channel_id' => \$this->channelId]);\n");

});
