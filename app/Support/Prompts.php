<?php

namespace App\Support;

use RuntimeException;

/**
 * Prompts aus resources/prompts/*.md (versioniert, nicht im Code verstreut, docs/grundgeruest.md). Platzhalter
 * {{ name }} werden ersetzt; Arrays als JSON. Unbekannte Platzhalter bleiben leer, damit ein Tippfehler auffaellt.
 */
class Prompts
{
    /**
     * @param  array<string, mixed>  $vars
     */
    public static function render(string $name, array $vars = []): string
    {
        $path = resource_path('prompts/'.$name.'.md');

        if (! is_file($path)) {
            throw new RuntimeException('Prompt fehlt: resources/prompts/'.$name.'.md');
        }

        $text = (string) file_get_contents($path);

        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function (array $m) use ($vars): string {
            $value = $vars[$m[1]] ?? '';

            if (is_array($value)) {
                return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            }

            return trim((string) $value);
        }, $text);
    }
}
