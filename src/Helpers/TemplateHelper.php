<?php

namespace Crescat\SaloonSdkGenerator\Helpers;

class TemplateHelper
{
    public static function render(string $template, array $variables = []): string
    {
        $result = preg_replace_callback(
            pattern: '/\{([^{}]+)\}/',
            callback: fn ($matched) => $variables[$matched[1]],
            subject: $template
        );

        return $result ?? $template;
    }
}
