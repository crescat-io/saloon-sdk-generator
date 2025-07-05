<?php

use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Parsers\PostmanCollectionParser;

it('parses headers from postman collection', function () {
    $parser = PostmanCollectionParser::build(__DIR__ . '/../../Samples/test-headers-postman.json');
    $apiSpec = $parser->parse();
    
    // Find the List Users endpoint
    $listUsersEndpoint = collect($apiSpec->endpoints)
        ->first(fn($endpoint) => $endpoint->name === 'List Users');
    
    expect($listUsersEndpoint)->not->toBeNull();
    expect($listUsersEndpoint->headerParameters)->toHaveCount(3);
    
    // Check that headers are parsed correctly
    $headers = collect($listUsersEndpoint->headerParameters);
    
    $apiKeyHeader = $headers->first(fn($h) => $h->name === 'X-API-Key');
    expect($apiKeyHeader)->not->toBeNull();
    expect($apiKeyHeader->type)->toBe('string');
    expect($apiKeyHeader->nullable)->toBe(true); // All Postman headers are nullable
    expect($apiKeyHeader->description)->toBe('API Key for authentication');
    
    $tenantHeader = $headers->first(fn($h) => $h->name === 'X-Tenant-ID');
    expect($tenantHeader)->not->toBeNull();
    expect($tenantHeader->description)->toBe('Tenant identifier for multi-tenant access');
    
    // Find the Create User endpoint
    $createUserEndpoint = collect($apiSpec->endpoints)
        ->first(fn($endpoint) => $endpoint->name === 'Create User');
    
    expect($createUserEndpoint)->not->toBeNull();
    // Should have 4 headers (including disabled one)
    expect($createUserEndpoint->headerParameters)->toHaveCount(4);
    
    $authHeader = collect($createUserEndpoint->headerParameters)
        ->first(fn($h) => $h->name === 'Authorization');
    expect($authHeader->description)->toBe('Bearer token for authentication');
    
    // Check that disabled header is still included
    $debugHeader = collect($createUserEndpoint->headerParameters)
        ->first(fn($h) => $h->name === 'X-Debug-Mode');
    expect($debugHeader)->not->toBeNull();
    expect($debugHeader->description)->toBe('Enable debug mode (disabled by default)');
});

it('handles endpoints without headers in postman collection', function () {
    $parser = PostmanCollectionParser::build(__DIR__ . '/../../Samples/test-headers-postman.json');
    $apiSpec = $parser->parse();
    
    // Find the Get Status endpoint
    $statusEndpoint = collect($apiSpec->endpoints)
        ->first(fn($endpoint) => $endpoint->name === 'Get Status');
    
    expect($statusEndpoint)->not->toBeNull();
    expect($statusEndpoint->headerParameters)->toBe([]);
});

it('generates postman collection code with headers correctly', function () {
    $parser = PostmanCollectionParser::build(__DIR__ . '/../../Samples/test-headers-postman.json');
    $apiSpec = $parser->parse();
    
    $config = new Config(
        connectorName: 'TestHeadersAPI',
        namespace: 'TestHeaders\\Generated'
    );
    
    // Generate the requests
    $requestGenerator = new \Crescat\SaloonSdkGenerator\Generators\RequestGenerator($config);
    $requestFiles = $requestGenerator->generate($apiSpec);
    
    // Find the Track Event request - the filename will be TrackEvent.php
    $trackEventFile = collect($requestFiles)
        ->first(fn($file) => str_contains((string)$file, 'TrackEvent'));
    
    expect($trackEventFile)->not->toBeNull();
    
    $generatedCode = (string) $trackEventFile;
    
    // Check that all headers are in the constructor (Authorization is filtered out by default)
    expect($generatedCode)->not->toContain('protected ?string $authorization');
    expect($generatedCode)->toContain('protected ?string $xClientVersion');
    expect($generatedCode)->toContain('protected ?string $xDeviceId');
    expect($generatedCode)->toContain('protected ?string $xSessionId');
    
    // Check defaultHeaders method
    expect($generatedCode)->toContain('public function defaultHeaders(): array');
    expect($generatedCode)->not->toContain("'Authorization' => \$this->authorization");
    expect($generatedCode)->toContain("'X-Client-Version' => \$this->xClientVersion");
    expect($generatedCode)->toContain("'X-Device-ID' => \$this->xDeviceId");
    expect($generatedCode)->toContain("'X-Session-ID' => \$this->xSessionId");
});