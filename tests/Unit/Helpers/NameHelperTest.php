<?php

use Crescat\SaloonSdkGenerator\Helpers\NameHelper;

it('normalizes values correctly', function () {
    $value = 'HelloWorld_Test-Cases';
    $expected = 'hello world test cases';
    $normalizedValue = NameHelper::normalize($value);
    expect($normalizedValue)->toBe($expected);
});

it('prevents name collisions correctly', function () {
    $value = 'void';
    $expected = 'voidClass';
    $preventionValue = NameHelper::preventNameCollisions($value);
    expect($preventionValue)->toBe($expected);
});

it('makes safe variable names correctly', function ($value, $expected) {
    $safeVariableName = NameHelper::safeVariableName($value);
    expect($safeVariableName)->toBe($expected);
})->with([

    ['1 method name', 'methodName'],
    ['# 1. Create Users', 'createUsers'],

    // Common
    ['list all (_new_) users', 'listAllNewUsers'],
    ['list all (*new*) users', 'listAllNewUsers'],
    ['create post', 'createPost'],

    // Whitespace
    ['list   all users       ', 'listAllUsers'],
    ['    list   all users', 'listAllUsers'],
    ['    list   all users     ', 'listAllUsers'],
    ['list   all users     ', 'listAllUsers'],
    ["\tlist \t  all \n users     ", 'listAllUsers'],
    // Foreign characters
    ['create pøst', 'createPost'],
    ['ø becomes o', 'oBecomesO'],
    ['å becomes a', 'aBecomesA'],
    ['create pøst', 'createPost'],
    ['lagPølse', 'lagPolse'],
]);

it('makes safe class names correctly', function () {
    $value = 'dashboard view';
    $expected = 'DashboardView';
    $safeClassName = NameHelper::safeClassName($value);
    expect($safeClassName)->toBe($expected);
});

it('makes resource class names correctly', function () {
    $value = 'User Profile';
    $expected = 'UserProfile';
    $resourceClassName = NameHelper::resourceClassName($value);
    expect($resourceClassName)->toBe($expected);
});

it('makes request class names correctly', function () {
    $value = 'Delete Post';
    $expected = 'DeletePost';
    $requestClassName = NameHelper::requestClassName($value);
    expect($requestClassName)->toBe($expected);
});

it('makes connector class names correctly', function () {
    $value = 'Social connect';
    $expected = 'SocialConnect';
    $connectorClassName = NameHelper::connectorClassName($value);
    expect($connectorClassName)->toBe($expected);
});
