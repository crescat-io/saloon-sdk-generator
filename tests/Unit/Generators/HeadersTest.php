<?php

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\BaseUrl;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Method;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Generators\RequestGenerator;
use Crescat\SaloonSdkGenerator\Generators\ResourceGenerator;

it('generates request with headers', function () {
    $endpoint = new Endpoint(
        name: 'GetUserProfile',
        method: Method::GET,
        pathSegments: ['users', ':userId', 'profile'],
        collection: 'Users',
        response: null,
        description: 'Get user profile with authentication',
        queryParameters: [
            new Parameter('string', true, 'include', 'Include related data'),
        ],
        pathParameters: [
            new Parameter('string', false, ':userId', 'User ID'),
        ],
        bodyParameters: [],
        headerParameters: [
            new Parameter('string', false, 'Authorization', 'Bearer token for authentication'),
            new Parameter('string', true, 'X-Request-ID', 'Optional request tracking ID'),
            new Parameter('string', true, 'Accept-Language', 'Preferred language'),
        ]
    );

    $config = new Config(
        connectorName: 'TestAPI',
        namespace: 'Test\\Generated',
        resourceNamespaceSuffix: 'Resource',
        requestNamespaceSuffix: 'Requests',
        dtoNamespaceSuffix: 'Dto'
    );

    $apiSpec = new ApiSpecification(
        name: 'Test API',
        description: 'Test API with headers',
        baseUrl: new BaseUrl('https://api.example.com'),
        endpoints: [$endpoint]
    );

    // Test Request Generation
    $requestGenerator = new RequestGenerator($config);
    $requestFiles = $requestGenerator->generate($apiSpec);
    $requestFile = $requestFiles[0];
    
    $generatedRequest = (string) $requestFile;
    
    // Check that headers are in constructor (Authorization and Accept-Language are filtered out by default)
    expect($generatedRequest)->not->toContain('protected string $authorization');
    expect($generatedRequest)->toContain('protected ?string $xRequestId');
    expect($generatedRequest)->not->toContain('protected ?string $acceptLanguage');
    
    // Check that defaultHeaders method is generated
    expect($generatedRequest)->toContain('public function defaultHeaders(): array');
    expect($generatedRequest)->not->toContain("'Authorization' => \$this->authorization");
    expect($generatedRequest)->toContain("'X-Request-ID' => \$this->xRequestId");
    expect($generatedRequest)->not->toContain("'Accept-Language' => \$this->acceptLanguage");
    expect($generatedRequest)->toContain('array_filter');

    // Test Resource Generation
    $resourceGenerator = new ResourceGenerator($config);
    $resourceFiles = $resourceGenerator->generate($apiSpec);
    $resourceFile = $resourceFiles[0];
    
    $generatedResource = (string) $resourceFile;
    
    // Check that resource method includes header parameters
    expect($generatedResource)->toContain('string $authorization');
    expect($generatedResource)->toContain('?string $xRequestId');
    expect($generatedResource)->toContain('?string $acceptLanguage');
    
    // Check that headers are passed to request constructor (with correct parameter order)
    expect($generatedResource)->toContain('$userId, $include, $authorization, $xRequestId, $acceptLanguage');
});

it('handles endpoints without headers', function () {
    $endpoint = new Endpoint(
        name: 'GetPublicData',
        method: Method::GET,
        pathSegments: ['public', 'data'],
        collection: 'Public',
        response: null,
        description: 'Get public data without authentication',
        queryParameters: [],
        pathParameters: [],
        bodyParameters: [],
        headerParameters: [] // No headers
    );

    $config = new Config(
        connectorName: 'TestAPI',
        namespace: 'Test\\Generated',
        resourceNamespaceSuffix: 'Resource',
        requestNamespaceSuffix: 'Requests',
        dtoNamespaceSuffix: 'Dto'
    );

    $apiSpec = new ApiSpecification(
        name: 'Test API',
        description: 'Test API',
        baseUrl: new BaseUrl('https://api.example.com'),
        endpoints: [$endpoint]
    );

    $requestGenerator = new RequestGenerator($config);
    $requestFiles = $requestGenerator->generate($apiSpec);
    $requestFile = $requestFiles[0];
    
    $generatedRequest = (string) $requestFile;
    
    // Should not have defaultHeaders method
    expect($generatedRequest)->not->toContain('defaultHeaders');
});