<?php

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Components;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Method;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Generators\RequestGenerator;

test('Class properties', function () {
    $apiSpec = getApiSpec();
    $generator = new RequestGenerator(new Config('MyConnector', 'VendorName'));

    $phpFiles = $generator->generate($apiSpec);

    $class = $phpFiles[0]->getNamespaces()['VendorName\Requests\Users']->getClasses()['GetUser'];

    $property = $class->getProperty('method');

    expect($property)->toBeInstanceOf(\Nette\PhpGenerator\Property::class)
        ->and($property->getName())->toBe('method')
        ->and($property->getType())->toBe('Saloon\Enums\Method')
        ->and($property->getValue()->__toString())->toBe('Method::GET');
});

test('Constructor', closure: function () {

    $apiSpec = getApiSpec();
    $generator = new RequestGenerator(new Config('MyConnector', 'VendorName'));

    $phpFiles = $generator->generate($apiSpec);

    $class = $phpFiles[0]->getNamespaces()['VendorName\Requests\Users']->getClasses()['GetUser'];

    $constructor = $class->getMethods()['__construct'];
    expect($constructor)->toBeInstanceOf(\Nette\PhpGenerator\Method::class)
        ->and($constructor->getParameters())->toHaveCount(2);

    /** @var \Nette\PhpGenerator\PromotedParameter $channelIdParam */
    $channelIdParam = $constructor->getParameter('channelId');
    expect($channelIdParam->getName())->toBe('channelId')
        ->and($channelIdParam->getVisibility())->toBe('protected')
        ->and($channelIdParam->getType())->toBe('int')
        ->and($channelIdParam->hasDefaultValue())->toBeTrue()
        ->and($channelIdParam->getDefaultValue())->toBeNull()
        ->and($channelIdParam->isNullable())->toBeTrue();

    /** @var \Nette\PhpGenerator\PromotedParameter $channelIdParam */
    $userIdParam = $constructor->getParameter('userId');
    expect($userIdParam->getName())->toBe('userId')
        ->and($userIdParam->getType())->toBe('int')
        ->and($userIdParam->getVisibility())->toBe('protected')
        ->and($userIdParam->isNullable())->toBeFalse();
});

test('Resolve endpoint', function () {
    $apiSpec = getApiSpec();
    $generator = new RequestGenerator(new Config('MyConnector', 'VendorName'));

    $phpFiles = $generator->generate($apiSpec);

    $class = $phpFiles[0]->getNamespaces()['VendorName\Requests\Users']->getClasses()['GetUser'];
    $resolveEndpoint = $class->getMethods()['resolveEndpoint'];

    expect($resolveEndpoint)->toBeInstanceOf(\Nette\PhpGenerator\Method::class)
        ->and($resolveEndpoint->getReturnType())->toBe('string')
        ->and($resolveEndpoint->getBody())->toBe("return \"/users/{\$this->userId}\";\n");
});

test('Default Query', function () {
    $apiSpec = getApiSpec();
    $generator = new RequestGenerator(new Config('MyConnector', 'VendorName'));

    $phpFiles = $generator->generate($apiSpec);

    $class = $phpFiles[0]->getNamespaces()['VendorName\Requests\Users']->getClasses()['GetUser'];
    $defaultQuery = $class->getMethods()['defaultQuery'];


    expect($defaultQuery)->toBeInstanceOf(\Nette\PhpGenerator\Method::class)
        ->and($defaultQuery->getName())->toBe('defaultQuery')
        ->and($defaultQuery->getReturnType())->toBe('array')
        ->and($defaultQuery->getBody())->toBe("return array_filter(['channel_id' => \$this->channelId]);\n");

});

function getApiSpec(): ApiSpecification
{
    return new ApiSpecification(
        name: 'ApiName',
        description: 'Example API',
        baseUrl: 'https://api.example.com/v1/',
        securityRequirements: [],
        components: new Components(),
        endpoints: [
            new Endpoint(
                name: 'getUser',
                method: Method::GET,
                pathSegments: ['users', ':user_id'],
                collection: 'Users',
                response: null,
                description: 'Get user',
                queryParameters: [
                    new Parameter('int', true, 'channel_id', 'Channel ID to use for channel-level data.')
                ],
                pathParameters: [new Parameter('int', false, 'user_id', 'ID of the user')],
                bodyParameters: []
            )
        ]
    );
}
