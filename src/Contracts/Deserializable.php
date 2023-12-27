<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Contracts;

interface Deserializable
{
    public static function deserialize(mixed $data): mixed;
}
