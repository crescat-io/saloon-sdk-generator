<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

enum ApiKeyLocation: string
{
    case query = 'query';
    case header = 'header';
    case cookie = 'cookie';
}
