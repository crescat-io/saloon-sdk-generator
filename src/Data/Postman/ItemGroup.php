<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Data\Postman;

use Illuminate\Support\Arr;

class ItemGroup
{
    public function __construct(
        public ?string $id,
        public ?string $name,
        public ?string $description,
        public array $item
    ) {
    }

    public static function parseItem($item): ItemGroup|Item
    {
        return isset($item['item']) ? ItemGroup::fromJson($item) : Item::fromJson($item);
    }

    public static function fromJson(array $json): self
    {
        return new self(
            id: Arr::get($json, 'id'),
            name: Arr::get($json, 'name'),
            description: Arr::get($json, 'description'),
            item: array_map(fn ($item) => ItemGroup::parseItem($item), $json['item'])

        );
    }
}
