<?php

it('can test', fn () => expect(true)->toBeTrue());

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();
