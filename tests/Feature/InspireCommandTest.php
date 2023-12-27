<?php

declare(strict_types=1);

it('inspires artisans', function () {
    $this->artisan('inspire')->assertExitCode(0);
});
