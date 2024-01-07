<?php

namespace Crescat\SaloonSdkGenerator\Converters;

use Crescat\SaloonSdkGenerator\Exceptions\ConversionFailedException;
use Illuminate\Support\Facades\Http;

/**
 * Converts an OpenApi 2.0 file into an OpenApi 3.0.1 file.
 *
 * Thin wrapper around https://converter.swagger.io/ only works for json files.
 *
 *
 * @see https://converter.swagger.io/
 */
class OpenApiConverter
{
    /**
     * @throws ConversionFailedException
     */
    public static function convert(string $content): string
    {
        $response = Http::asJson()
            ->acceptJson()
            ->withUserAgent('Saloon SDK Generator (https://github.com/crescat-io/saloon-sdk-generator)')
            ->withBody($content)
            ->post('https://converter.swagger.io/api/convert');

        if ($response->failed()) {
            throw new ConversionFailedException(
                $response->json('message') ?? 'Failed to convert file'
            );
        }

        return $response->body();
    }
}
