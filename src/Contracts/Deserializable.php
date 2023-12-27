<?php

namespace Crescat\SaloonSdkGenerator\Contracts;

interface Deserializable
{
    public static function deserialize(mixed $data): mixed;
}
