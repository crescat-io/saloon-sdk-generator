<?php

use Illuminate\Support\Facades\Process;

test('All of the sample APIs can be generated without throwing crashing', function () {
    $process = Process::run($command = 'composer generate:all');

    expect($process->successful())
        ->toBeTrue(
            sprintf("Command '%s' failed to run without error:\n%s", $command, $process->errorOutput())
        );
});

afterAll(fn () => Process::run('composer clean'));
