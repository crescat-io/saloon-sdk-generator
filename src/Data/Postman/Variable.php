<?php

namespace Crescat\SaloonSdkGenerator\Data\Postman;

use Illuminate\Support\Arr;

class Variable
{
    public function __construct(
        public ?string $id,
        public string $key,
        public string $value,
        public ?string $type,
        public ?bool $disabled
    ) {
    }

    public static function fromJson(array $json): self
    {
        return new self(
            id: Arr::get($json, 'id'),
            key: Arr::get($json, 'key'),
            value: Arr::get($json, 'value'),
            type: Arr::get($json, 'type'),
            disabled: Arr::get($json, 'disabled'),
        );
    }
}
