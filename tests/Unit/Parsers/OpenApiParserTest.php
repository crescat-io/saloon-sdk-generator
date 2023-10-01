<?php

use Crescat\SaloonSdkGenerator\Parsers\OpenApiParser;

test('Parsed security requirements', function () {

    $specFile = __DIR__ . '/../../Samples/bigcommerce/abandoned_carts.v3.yml';
    $parser = OpenApiParser::build($specFile);
    $spec = $parser->parse();

    expect($spec->securityRequirements)->toHaveCount(1)
        ->and($spec->securityRequirements[0]->name)->toBe('X-Auth-Token');
});

test('Parsed components: security schemes', function () {

    $specFile = __DIR__ . '/../../Samples/bigcommerce/abandoned_carts.v3.yml';
    $parser = OpenApiParser::build($specFile);
    $spec = $parser->parse();

    expect($spec->components)->not()->toBeNull()
        ->and($spec->components->securitySchemes)->toHaveCount(1)
        ->and($spec->components->securitySchemes[0]->name)->toBe('X-Auth-Token')
        ->and($spec->components->securitySchemes[0]->type)->toBe('apiKey')
        ->and($spec->components->securitySchemes[0]->in)->toBe('header');
});
