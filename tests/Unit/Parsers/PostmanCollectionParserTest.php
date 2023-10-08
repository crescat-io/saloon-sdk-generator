<?php

use Crescat\SaloonSdkGenerator\Parsers\PostmanCollectionParser;

test('Parse collection', function () {
    $specFile = __DIR__ . '/../../Samples/openai.json';
    $parser = PostmanCollectionParser::build($specFile);
    $spec = $parser->parse();

    expect($spec->baseUrl->url)->toBe('https://api.openai.com/v1')
        ->and($spec->name)->toBe('OpenAI')
        ->and($spec->endpoints)->toHaveCount(28);

    $endpoint = $spec->endpoints[0];
    expect($endpoint->name)->toBe('List models')
        ->and($endpoint->pathSegments)->toBe(['models'])
        ->and($endpoint->method)->toBe(Crescat\SaloonSdkGenerator\Data\Generator\Method::GET);
});
