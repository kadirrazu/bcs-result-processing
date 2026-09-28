<?php

namespace App\Support\Ui;

final class UiColorScheme
{
    public const DEFAULT = 'default';

    /** @return array<string, array{label:string,description:string,swatches:list<string>}> */
    public static function options(): array
    {
        return [
            self::DEFAULT => ['label' => 'Default', 'description' => 'Original application appearance with no color override.', 'swatches' => ['#ffffff', '#f6f8fb', '#206bc4']],
            'soft-blue' => ['label' => 'Soft Blue', 'description' => 'Cool institutional blue with a clearly blue but restrained workspace.', 'swatches' => ['#f0f6fc', '#dcebf8', '#3f78aa']],
            'mist-teal' => ['label' => 'Aqua Teal', 'description' => 'Fresh blue-green tint with a calm administrative character.', 'swatches' => ['#eff8f7', '#d7eeea', '#3f8279']],
            'sage' => ['label' => 'Sage Green', 'description' => 'Muted natural green with a soft neutral background for long sessions.', 'swatches' => ['#f3f7f0', '#e0ebda', '#66805d']],
            'warm-sand' => ['label' => 'Warm Sand', 'description' => 'Light cream and muted brown accents for a warmer formal workspace.', 'swatches' => ['#faf7f0', '#efe5d2', '#8b7656']],
            'dusty-rose' => ['label' => 'Dusty Rose', 'description' => 'Restrained rose-grey accents with low saturation and professional contrast.', 'swatches' => ['#faf5f6', '#eedfe2', '#916b73']],
            'slate-blue' => ['label' => 'Cool Gray', 'description' => 'Neutral blue-gray styling with crisp, understated administrative contrast.', 'swatches' => ['#f3f6f8', '#e1e8ed', '#607789']],
            'soft-indigo' => ['label' => 'Lavender Slate', 'description' => 'Muted lavender-slate accents that remain light and formal rather than vivid.', 'swatches' => ['#f7f4fa', '#e8e0ef', '#776b8f']],
        ];
    }

    public static function isValid(?string $scheme): bool
    {
        return $scheme !== null && array_key_exists($scheme, self::options());
    }

    public static function cssClass(?string $scheme): string
    {
        if (! self::isValid($scheme) || $scheme === self::DEFAULT) {
            return '';
        }

        return 'ui-scheme-'.preg_replace('/[^a-z0-9-]/', '', (string) $scheme);
    }
}
