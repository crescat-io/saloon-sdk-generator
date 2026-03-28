<?php

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\BaseUrl;
use Crescat\SaloonSdkGenerator\Data\Generator\Components;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\GeneratedCode;
use Crescat\SaloonSdkGenerator\Data\Generator\Method;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Generators\ConnectorGenerator;
use Crescat\SaloonSdkGenerator\Generators\PestTestGenerator;

it('generates test with DTO imports when endpoint has DTO parameters', function () {
    $config = new Config(
        connectorName: 'TestAPI',
        namespace: 'Acme\\TestSDK',
        resourceNamespaceSuffix: 'Resource',
        requestNamespaceSuffix: 'Requests',
        dtoNamespaceSuffix: 'Dto'
    );

    // Create an endpoint with DTO parameters
    $endpoint = new Endpoint(
        name: 'CreateUser',
        method: Method::POST,
        pathSegments: ['users'],
        collection: 'Users',
        response: null,
        description: 'Create a new user',
        bodyParameters: [
            new Parameter(
                type: 'Acme\TestSDK\Dto\UserRequest',
                nullable: false,
                name: 'user',
                description: 'User data'
            ),
            new Parameter(
                type: 'Acme\TestSDK\Dto\AddressRequest',
                nullable: true,
                name: 'address',
                description: 'User address'
            ),
        ],
        queryParameters: [
            new Parameter(
                type: 'string',
                nullable: false,
                name: 'notify',
                description: 'Send notification'
            ),
        ]
    );

    $apiSpec = new ApiSpecification(
        name: 'Test API',
        description: 'A test API',
        baseUrl: new BaseUrl('https://api.example.com'),
        components: new Components(
            schemas: [],
            securitySchemes: []
        ),
        endpoints: [$endpoint]
    );

    // Generate the connector first so we have GeneratedCode structure
    $connectorGenerator = new ConnectorGenerator;
    $connectorGenerator->setConfig($config);
    $connectorClass = $connectorGenerator->generate($apiSpec);

    $generatedCode = new GeneratedCode;
    $generatedCode->connectorClass = $connectorClass;

    $pestGenerator = new PestTestGenerator;
    $result = $pestGenerator->process($config, $apiSpec, $generatedCode);

    // Find the Users test file
    $usersTest = null;
    foreach ($result as $file) {
        if (str_contains($file->path, 'UsersTest.php')) {
            $usersTest = $file;
            break;
        }
    }

    expect($usersTest)->not->toBeNull('UsersTest.php file should be generated');

    $testContent = $usersTest->file;

    // Should import the request class
    expect($testContent)->toContain('use Acme\TestSDK\Requests\Users\CreateUser;');

    // Should import DTOs that are used as parameters
    expect($testContent)->toContain('use Acme\TestSDK\Dto\UserRequest;');
    expect($testContent)->toContain('use Acme\TestSDK\Dto\AddressRequest;');

    // Should not import non-DTO types (like 'string')
    expect($testContent)->not->toContain('use string;');
});

it('generates test without DTO imports when endpoint has no DTO parameters', function () {
    $config = new Config(
        connectorName: 'TestAPI',
        namespace: 'Acme\\TestSDK',
        resourceNamespaceSuffix: 'Resource',
        requestNamespaceSuffix: 'Requests',
        dtoNamespaceSuffix: 'Dto'
    );

    // Create an endpoint with only primitive parameters
    $endpoint = new Endpoint(
        name: 'GetUser',
        method: Method::GET,
        pathSegments: ['users', ':id'],
        collection: 'Users',
        response: null,
        description: 'Get a user',
        pathParameters: [
            new Parameter(
                type: 'int',
                nullable: false,
                name: 'id',
                description: 'User ID'
            ),
        ],
        queryParameters: [
            new Parameter(
                type: 'string',
                nullable: true,
                name: 'fields',
                description: 'Fields to include'
            ),
        ]
    );

    $apiSpec = new ApiSpecification(
        name: 'Test API',
        description: 'A test API',
        baseUrl: new BaseUrl('https://api.example.com'),
        endpoints: [$endpoint]
    );

    $connectorGenerator = new ConnectorGenerator;
    $connectorGenerator->setConfig($config);
    $connectorClass = $connectorGenerator->generate($apiSpec);

    $generatedCode = new GeneratedCode;
    $generatedCode->connectorClass = $connectorClass;

    $pestGenerator = new PestTestGenerator;
    $result = $pestGenerator->process($config, $apiSpec, $generatedCode);

    // Find the Users test file
    $usersTest = null;
    foreach ($result as $file) {
        if (str_contains($file->path, 'UsersTest.php')) {
            $usersTest = $file;
            break;
        }
    }

    expect($usersTest)->not->toBeNull('UsersTest.php file should be generated');

    $testContent = $usersTest->file;

    // Should not have a DTO imports section with content
    $hasNoDto = ! str_contains($testContent, 'use Acme\TestSDK\Dto\\');
    expect($hasNoDto)->toBeTrue('Should not import any DTOs');
});

it('handles mixed DTO and primitive parameters correctly', function () {
    $config = new Config(
        connectorName: 'TestAPI',
        namespace: 'Acme\\TestSDK',
        resourceNamespaceSuffix: 'Resource',
        requestNamespaceSuffix: 'Requests',
        dtoNamespaceSuffix: 'Dto'
    );

    $endpoint = new Endpoint(
        name: 'UpdateProduct',
        method: Method::PATCH,
        pathSegments: ['products', ':id'],
        collection: 'Products',
        response: null,
        pathParameters: [
            new Parameter(
                type: 'string',
                nullable: false,
                name: 'id',
            ),
        ],
        bodyParameters: [
            new Parameter(
                type: 'Acme\TestSDK\Dto\ProductUpdate',
                nullable: false,
                name: 'product',
            ),
            new Parameter(
                type: 'string',
                nullable: true,
                name: 'reason',
            ),
        ],
        headerParameters: [
            new Parameter(
                type: 'Acme\TestSDK\Dto\MetadataHeader',
                nullable: true,
                name: 'metadata',
            ),
        ]
    );

    $apiSpec = new ApiSpecification(
        name: 'Test API',
        description: 'A test API',
        baseUrl: new BaseUrl('https://api.example.com'),
        endpoints: [$endpoint]
    );

    $connectorGenerator = new ConnectorGenerator;
    $connectorGenerator->setConfig($config);
    $connectorClass = $connectorGenerator->generate($apiSpec);

    $generatedCode = new GeneratedCode;
    $generatedCode->connectorClass = $connectorClass;

    $pestGenerator = new PestTestGenerator;
    $result = $pestGenerator->process($config, $apiSpec, $generatedCode);

    $productsTest = null;
    foreach ($result as $file) {
        if (str_contains($file->path, 'ProductsTest.php')) {
            $productsTest = $file;
            break;
        }
    }

    expect($productsTest)->not->toBeNull();

    $testContent = $productsTest->file;

    // Should import only the DTOs
    expect($testContent)->toContain('use Acme\TestSDK\Dto\ProductUpdate;');
    expect($testContent)->toContain('use Acme\TestSDK\Dto\MetadataHeader;');

    // Should not import primitive types
    expect($testContent)->not->toContain('use string;');
});
