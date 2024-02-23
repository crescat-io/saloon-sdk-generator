<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

use Saloon\Enums\Method as SaloonMethod;

enum Method: string
{
    case GET = 'GET';
    case HEAD = 'HEAD';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case OPTIONS = 'OPTIONS';
    case CONNECT = 'CONNECT';
    case TRACE = 'TRACE';

    public static function parse($value, ?self $fallback = self::GET): ?self
    {
        // Should be the same, this enum is a clone of Saloon´s enum.
        if ($value instanceof SaloonMethod) {
            return self::from($value->value);
        }

        return self::tryFrom(strtoupper(trim($value)));
    }

    public function isGet(): bool
    {
        return $this == self::GET;
    }

    public function isHead(): bool
    {
        return $this == self::HEAD;
    }

    public function isPost(): bool
    {
        return $this == self::POST;
    }

    public function isPut(): bool
    {
        return $this == self::PUT;
    }

    public function isPatch(): bool
    {
        return $this == self::PATCH;
    }

    public function isDelete(): bool
    {
        return $this == self::DELETE;
    }

    public function isOptions(): bool
    {
        return $this == self::OPTIONS;
    }

    public function isConnect(): bool
    {
        return $this == self::CONNECT;
    }

    public function isTrace(): bool
    {
        return $this == self::TRACE;
    }

    public function actionLabel()
    {
        return match ($this) {
            self::GET => 'Get',
            self::HEAD => 'Head',
            self::POST => 'Create',
            self::PUT => 'Update',
            self::PATCH => 'Update',
            self::DELETE => 'Delete',
            self::OPTIONS => 'Options',
            self::CONNECT => 'Connect',
            self::TRACE => 'Trace',
        };
    }
}
