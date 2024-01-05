<?php

use Crescat\SaloonSdkGenerator\Helpers\TemplateHelper;

test('Render template', function ($template, $replace, $expected) {

    $rendered = TemplateHelper::render($template, $replace);

    expect($rendered)->toBe($expected);

})->with([
    ['This is a test', [], 'This is a test'],
    ['This is a {test}', ['test' => 'house'], 'This is a house'],
    ['This is a {{replace_me}}', ['replace_me' => '/slash/dot/things'], 'This is a {/slash/dot/things}'],
    ['This is a {replace_me}', ['replace_me' => '/slash/dot/things'], 'This is a /slash/dot/things'],
    ['/api/v1/{resource}', ['resource' => 'users'], '/api/v1/users'],
    ['/api/v1/{resource}', ['resource' => 'users/me'], '/api/v1/users/me'],
    ['/api/v1/{resource}', ['resource' => 'users/me/'], '/api/v1/users/me/'],
    ['/api/v1/{resource}/{subresource}', ['resource' => 'users', 'subresource' => 'comments'], '/api/v1/users/comments'],
    ['/api/v1/{{resource}}/{{subresource}}', ['resource' => 'users', 'subresource' => 'comments'], '/api/v1/{users}/{comments}'],
]);
