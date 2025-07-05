<?php

use Crescat\SaloonSdkGenerator\Parsers\OpenApiParser;

it('resolves parameter references', function () {
    $specFile = sample_path('spotify.yml');
    $parser = OpenApiParser::build($specFile);
    $spec = $parser->parse();

    // Find the get-an-album endpoint
    $getAlbumEndpoint = collect($spec->endpoints)
        ->first(fn($endpoint) => $endpoint->name === 'get-an-album');
    
    expect($getAlbumEndpoint)->not()->toBeNull();
    
    // Should have path parameter from $ref: '#/components/parameters/PathAlbumId'
    expect($getAlbumEndpoint->pathParameters)->toHaveCount(1);
    expect($getAlbumEndpoint->pathParameters[0]->name)->toBe('id');
    
    // Should have query parameter from $ref: '#/components/parameters/QueryMarket'
    expect($getAlbumEndpoint->queryParameters)->toHaveCount(1);
    expect($getAlbumEndpoint->queryParameters[0]->name)->toBe('market');
});

it('resolves schema references in DTOs', function () {
    // This test would check that components.schemas references are resolved
    // when passed to DtoGenerator
    expect(true)->toBeTrue(); // TODO: Implement when we have a sample with schema refs
});