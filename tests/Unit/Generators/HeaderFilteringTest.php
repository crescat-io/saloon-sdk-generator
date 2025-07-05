<?php

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\BaseUrl;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Method;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Generators\RequestGenerator;

it('filters out default ignored headers', function () {
    $config = new Config(
        connectorName: 'TestConnector',
        namespace: 'TestNamespace'
    );
    
    $endpoint = new Endpoint(
        name: 'TestRequest',
        method: Method::GET,
        pathSegments: ['/test'],
        collection: null,
        response: null,
        headerParameters: [
            new Parameter('string', false, 'Authorization'),
            new Parameter('string', false, 'Content-Type'),
            new Parameter('string', false, 'Accept'),
            new Parameter('string', false, 'Accept-Language'),
            new Parameter('string', false, 'X-Custom-Header'),
            new Parameter('string', false, 'X-API-Key'),
        ]
    );
    
    $spec = new ApiSpecification(
        name: 'Test API',
        description: 'Test API',
        baseUrl: new BaseUrl('https://api.example.com'),
        endpoints: [$endpoint],
    );
    
    $generator = new RequestGenerator($config);
    $files = $generator->generate($spec);
    
    expect($files)->toHaveCount(1);
    
    $requestFile = $files[0];
    $requestCode = (string) $requestFile;
    
    // Should not include ignored headers
    expect($requestCode)->not->toContain('Authorization');
    expect($requestCode)->not->toContain('Content-Type');
    expect($requestCode)->not->toContain('Accept');
    expect($requestCode)->not->toContain('Accept-Language');
    
    // Should include custom headers
    expect($requestCode)->toContain('X-Custom-Header');
    expect($requestCode)->toContain('X-API-Key');
    expect($requestCode)->toContain('defaultHeaders()');
});

it('allows custom header filtering configuration', function () {
    $config = new Config(
        connectorName: 'TestConnector',
        namespace: 'TestNamespace',
        ignoredHeaderParams: ['X-Custom-Header', 'X-Internal-Header']
    );
    
    $endpoint = new Endpoint(
        name: 'TestRequest',
        method: Method::GET,
        pathSegments: ['/test'],
        collection: null,
        response: null,
        headerParameters: [
            new Parameter('string', false, 'Authorization'),
            new Parameter('string', false, 'X-Custom-Header'),
            new Parameter('string', false, 'X-Internal-Header'),
            new Parameter('string', false, 'X-API-Key'),
        ]
    );
    
    $spec = new ApiSpecification(
        name: 'Test API',
        description: 'Test API',
        baseUrl: new BaseUrl('https://api.example.com'),
        endpoints: [$endpoint],
    );
    
    $generator = new RequestGenerator($config);
    $files = $generator->generate($spec);
    
    expect($files)->toHaveCount(1);
    
    $requestFile = $files[0];
    $requestCode = (string) $requestFile;
    
    // Should not include custom ignored headers
    expect($requestCode)->not->toContain('X-Custom-Header');
    expect($requestCode)->not->toContain('X-Internal-Header');
    
    // Should include non-ignored headers (even default ones if not in custom list)
    expect($requestCode)->toContain('Authorization');
    expect($requestCode)->toContain('X-API-Key');
});

it('handles endpoints with no headers gracefully', function () {
    $config = new Config(
        connectorName: 'TestConnector',
        namespace: 'TestNamespace'
    );
    
    $endpoint = new Endpoint(
        name: 'TestRequest',
        method: Method::GET,
        pathSegments: ['/test'],
        collection: null,
        response: null,
        headerParameters: []
    );
    
    $spec = new ApiSpecification(
        name: 'Test API',
        description: 'Test API',
        baseUrl: new BaseUrl('https://api.example.com'),
        endpoints: [$endpoint],
    );
    
    $generator = new RequestGenerator($config);
    $files = $generator->generate($spec);
    
    expect($files)->toHaveCount(1);
    
    $requestFile = $files[0];
    $requestCode = (string) $requestFile;
    
    // Should not have defaultHeaders method
    expect($requestCode)->not->toContain('defaultHeaders()');
});

it('generates proper header method with mixed parameters', function () {
    $config = new Config(
        connectorName: 'TestConnector',
        namespace: 'TestNamespace'
    );
    
    $endpoint = new Endpoint(
        name: 'TestRequest',
        method: Method::POST,
        pathSegments: ['/test'],
        collection: null,
        response: null,
        headerParameters: [
            new Parameter('string', false, 'X-API-Key'),
            new Parameter('string', true, 'X-Optional-Header'),
            new Parameter('int', false, 'X-Request-ID'),
        ]
    );
    
    $spec = new ApiSpecification(
        name: 'Test API',
        description: 'Test API',
        baseUrl: new BaseUrl('https://api.example.com'),
        endpoints: [$endpoint],
    );
    
    $generator = new RequestGenerator($config);
    $files = $generator->generate($spec);
    
    expect($files)->toHaveCount(1);
    
    $requestFile = $files[0];
    $requestCode = (string) $requestFile;
    
    // Check constructor parameters
    expect($requestCode)->toContain('protected string $xApiKey');
    expect($requestCode)->toContain('protected ?string $xOptionalHeader = null');
    expect($requestCode)->toContain('protected int $xRequestId');
    
    // Check defaultHeaders method
    expect($requestCode)->toContain('public function defaultHeaders(): array');
    expect($requestCode)->toContain("'X-API-Key' => \$this->xApiKey");
    expect($requestCode)->toContain("'X-Optional-Header' => \$this->xOptionalHeader");
    expect($requestCode)->toContain("'X-Request-ID' => \$this->xRequestId");
});