<?php

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\BaseUrl;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Method;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Generators\ResourceGenerator;

it('generates resource methods with nullable parameters having default values', function () {
    $config = new Config(
        connectorName: 'TestConnector',
        namespace: 'TestNamespace'
    );
    
    $endpoint = new Endpoint(
        name: 'GetUser',
        method: Method::GET,
        pathSegments: ['users', ':id'],
        collection: 'Users',
        response: null,
        description: 'Get a user by ID',
        queryParameters: [
            new Parameter('string', false, 'id', 'User ID'),
            new Parameter('string', true, 'include', 'Include related data'),
            new Parameter('bool', true, 'active', 'Filter by active status'),
        ],
        pathParameters: [
            new Parameter('string', false, 'id', 'User ID'),
        ],
    );
    
    $spec = new ApiSpecification(
        name: 'Test API',
        description: 'Test API',
        baseUrl: new BaseUrl('https://api.example.com'),
        endpoints: [$endpoint],
    );
    
    $generator = new ResourceGenerator($config);
    $files = $generator->generate($spec);
    
    expect($files)->toHaveCount(1);
    
    $resourceFile = $files[0];
    $resourceCode = (string) $resourceFile;
    
    // Check that the method signature has nullable parameters with default values
    expect($resourceCode)->toContain('public function getUser(string $id, ?string $include = null, ?bool $active = null)');
    
    // Check the PHPDoc comments
    expect($resourceCode)->toContain('@param string $id User ID');
    expect($resourceCode)->toContain('@param string $include Include related data');
    expect($resourceCode)->toContain('@param bool $active Filter by active status');
});

it('generates resource methods with required parameters without default values', function () {
    $config = new Config(
        connectorName: 'TestConnector',
        namespace: 'TestNamespace'
    );
    
    $endpoint = new Endpoint(
        name: 'CreateUser',
        method: Method::POST,
        pathSegments: ['users'],
        collection: 'Users',
        response: null,
        description: 'Create a new user',
        bodyParameters: [
            new Parameter('string', false, 'name', 'User name'),
            new Parameter('string', false, 'email', 'User email'),
            new Parameter('string', true, 'phone', 'User phone'),
        ],
    );
    
    $spec = new ApiSpecification(
        name: 'Test API',
        description: 'Test API',
        baseUrl: new BaseUrl('https://api.example.com'),
        endpoints: [$endpoint],
    );
    
    $generator = new ResourceGenerator($config);
    $files = $generator->generate($spec);
    
    expect($files)->toHaveCount(1);
    
    $resourceFile = $files[0];
    $resourceCode = (string) $resourceFile;
    
    // Check that required parameters don't have default values
    expect($resourceCode)->toContain('public function createUser(string $name, string $email, ?string $phone = null)');
});

it('respects ignored query parameters in resource methods', function () {
    $config = new Config(
        connectorName: 'TestConnector',
        namespace: 'TestNamespace',
        ignoredQueryParams: ['per_page', 'page']
    );
    
    $endpoint = new Endpoint(
        name: 'ListUsers',
        method: Method::GET,
        pathSegments: ['users'],
        collection: 'Users',
        response: null,
        queryParameters: [
            new Parameter('string', true, 'search', 'Search term'),
            new Parameter('int', true, 'per_page', 'Items per page'),
            new Parameter('int', true, 'page', 'Page number'),
            new Parameter('string', true, 'sort', 'Sort field'),
        ],
    );
    
    $spec = new ApiSpecification(
        name: 'Test API',
        description: 'Test API',
        baseUrl: new BaseUrl('https://api.example.com'),
        endpoints: [$endpoint],
    );
    
    $generator = new ResourceGenerator($config);
    $files = $generator->generate($spec);
    
    $resourceFile = $files[0];
    $resourceCode = (string) $resourceFile;
    
    // Should include non-ignored parameters
    expect($resourceCode)->toContain('?string $search = null');
    expect($resourceCode)->toContain('?string $sort = null');
    
    // Should not include ignored parameters
    expect($resourceCode)->not->toContain('per_page');
    expect($resourceCode)->not->toContain('page');
});