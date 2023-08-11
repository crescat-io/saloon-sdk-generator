<?php

namespace App\Data\Postman;

use Illuminate\Support\Arr;

class Request
{
    public function __construct(
        public string|Url $url,
        public string $method,
        public ?string $description = null,
        public ?array $header = null,
        public ?Body $body = null
    ) {
    }

    public static function fromJson(string|array $json): self
    {
        return is_string($json)
            ? new self(
                url: $json,
                method: 'GET',
            )
            : new self(
                url: Url::fromJson(Arr::get($json, 'url')),
                method: Arr::get($json, 'method', 'GET'),
                description: Arr::get($json, 'description.content'),
                header: Arr::get($json, 'header'),
                body: Arr::get($json, 'body') ? Body::fromJson(Arr::get($json, 'body')) : null,
            );

    }
}
