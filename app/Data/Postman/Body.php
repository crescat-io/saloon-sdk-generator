<?php

namespace App\Data\Postman;

use Illuminate\Support\Arr;

class Body
{
    public function __construct(
        public string $mode,
        public ?string $raw,
        public ?array $urlencoded,
        public ?array $formData,
        public ?array $file,
        public ?array $graphql,
        public ?array $options,
        public bool $disabled,
    ) {

    }

    public function rawAsJson(): ?array
    {
        return $this->raw ? json_decode($this->raw, true) : null;
    }

    public static function fromJson(array $json): self
    {
        return new self(
            mode: Arr::get($json, 'mode'),
            raw: Arr::get($json, 'raw'),
            urlencoded: Arr::get($json, 'urlencoded'),
            formData: Arr::get($json, 'formdata'),
            file: Arr::get($json, 'file'),
            graphql: Arr::get($json, 'graphql'),
            options: Arr::get($json, 'options'),
            disabled: Arr::get($json, 'disabled', false),
        );
    }
}
