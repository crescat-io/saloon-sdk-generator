<?php

namespace App\Data\Postman;

use Illuminate\Support\Arr;

class Url
{
    public function __construct(
        public ?string $raw,
        public ?string $protocol,
        public null|string|array $host,
        public null|string|array $path,
        public ?string $port,
        public ?array $query,
        public ?string $hash,
        public ?array $variable,
    ) {
    }

    public static function fromJson($json): Url|string
    {
        if (is_string($json)) {
            return $json;
        }

        return new self(
            raw: Arr::get($json, 'raw'),
            protocol: Arr::get($json, 'protocol'),
            host: Arr::get($json, 'host'),
            path: Arr::get($json, 'path'),
            port: Arr::get($json, 'port'),
            query: Arr::get($json, 'query'),
            hash: Arr::get($json, 'hash'),
            variable: Arr::get($json, 'variable'),
        );

    }
}
