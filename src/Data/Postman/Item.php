<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Data\Postman;

use Illuminate\Support\Arr;

class Item
{
    public function __construct(
        public ?string $id,
        public ?string $name,
        public ?string $description,
        public ?Request $request,
        public ?array $response
    ) {
    }

    public static function fromJson(array $json): self
    {
        return new self(
            id: Arr::get($json, 'id'),
            name: Arr::get($json, 'name'),
            description: Arr::get($json, 'description'),
            request: Arr::get($json, 'request') ? Request::fromJson(Arr::get($json, 'request')) : null,
            response: Arr::get($json, 'response'),
        );
    }
}
