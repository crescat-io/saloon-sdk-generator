<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Data\Postman;

use Illuminate\Support\Arr;

class Info
{
    public function __construct(
        public string $name,
        public ?string $version = null,
        public ?string $description = null,
        public ?string $schema = null
    ) {
    }

    public static function fromJson(array $json): self
    {
        return new self(
            name: Arr::get($json, 'name'),
            version: Arr::get($json, 'version'),
            description: Arr::get($json, 'description.content'),
            schema: Arr::get($json, 'schema'),
        );
    }
}
