<?php

use Crescat\SaloonSdkGenerator\Data\Generator\ApiKeyLocation;
use Crescat\SaloonSdkGenerator\Data\Generator\SecuritySchemeType;
use Crescat\SaloonSdkGenerator\Parsers\OpenApiParser;

test('Parsed base url', function () {
    $specFile = sample_path('bigcommerce_abandoned_carts.v3.yml');
    $parser = OpenApiParser::build($specFile);
    $spec = $parser->parse();

    expect($spec->baseUrl->url)->toBe('https://api.bigcommerce.com/stores/{store_hash}/v3')
        ->and($spec->baseUrl->parameters)->toHaveCount(1)
        ->and($spec->baseUrl->parameters[0]->name)->toBe('store_hash')
        ->and($spec->baseUrl->parameters[0]->default)->toBe('store_hash')
        ->and($spec->baseUrl->parameters[0]->description)->toBe('Permanent ID of the BigCommerce store.');
});

test('Parsed security requirements', function () {

    $specFile = sample_path('bigcommerce_abandoned_carts.v3.yml');
    $parser = OpenApiParser::build($specFile);
    $spec = $parser->parse();

    expect($spec->securityRequirements)->toHaveCount(1)
        ->and($spec->securityRequirements[0]->name)->toBe('X-Auth-Token');
});

test('Parsed security requirements using bearer token', function () {

    $specFile = sample_path('kassalapp.json');
    $parser = OpenApiParser::build($specFile);
    $spec = $parser->parse();

    expect($spec->components)->not()->toBeNull()
        ->and($spec->components->securitySchemes)->toHaveCount(1)
        ->and($spec->components->securitySchemes[0]->name)->toBe(null)
        ->and($spec->components->securitySchemes[0]->type)->toBe(SecuritySchemeType::http)
        ->and($spec->components->securitySchemes[0]->bearerFormat)->toBe('token')
        ->and($spec->securityRequirements[0]->name)->toBe('http');
});

test('Parsed components: security schemes', function () {

    $specFile = sample_path('bigcommerce_abandoned_carts.v3.yml');
    $parser = OpenApiParser::build($specFile);
    $spec = $parser->parse();

    expect($spec->components)->not()->toBeNull()
        ->and($spec->components->securitySchemes)->toHaveCount(1)
        ->and($spec->components->securitySchemes[0]->name)->toBe('X-Auth-Token')
        ->and($spec->components->securitySchemes[0]->type)->toBe(SecuritySchemeType::apiKey)
        ->and($spec->components->securitySchemes[0]->in)->toBe(ApiKeyLocation::header);
});
