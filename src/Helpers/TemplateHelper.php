<?php

namespace Crescat\SaloonSdkGenerator\Helpers;

class TemplateHelper
{
    public static function render(string $template, array $variables = []): string
    {
        $result = preg_replace_callback(
            '/{(.+?)}/',
            function ($matches) use ($variables) {
                return $variables[$matches[1]];
            },
            $template
        );

        return $result ?? $template;
    }
}
