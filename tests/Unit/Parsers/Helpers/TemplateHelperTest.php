<?php


use Crescat\SaloonSdkGenerator\Helpers\TemplateHelper;

test('Render template', function () {
    expect(TemplateHelper::render('This is a test'))->toBe('This is a test')
        ->and(TemplateHelper::render('This is a {test}', ['test' => 'house']))->toBe('This is a house');

});
