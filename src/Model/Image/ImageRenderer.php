<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\Image;

use InvalidArgumentException;

/**
 * Builds a Core-Web-Vitals-friendly `<img>` (or `<picture>` when alternative
 * sources are given). Setting `width`/`height` reserves layout space (kills
 * CLS); `loading`/`fetchpriority` let the caller defer below-the-fold images and
 * prioritize the LCP image.
 *
 * Pure (no Magento dependencies, escapes with htmlspecialchars like PropsEncoder)
 * so the attribute and `<picture>` assembly rules are unit-testable in isolation.
 */
class ImageRenderer
{
    /**
     * @param string $src Image URL (required).
     * @param array{
     *     alt?: string,
     *     width?: int|string|null,
     *     height?: int|string|null,
     *     loading?: string,
     *     decoding?: string,
     *     fetchpriority?: string,
     *     class?: string,
     *     sizes?: string,
     *     srcset?: string,
     *     sources?: list<array{srcset:string, type?:string, media?:string, sizes?:string}>,
     *     attributes?: array<string, string|int|bool>
     * } $options
     *
     * @return string
     * @throws InvalidArgumentException When $src is empty.
     */
    public function render(string $src, array $options = []): string
    {
        if ($src === '') {
            throw new InvalidArgumentException('Cannot render an image with an empty src.');
        }

        $img = $this->renderImg($src, $options);

        $sources = $options['sources'] ?? [];
        if ($sources === []) {
            return $img;
        }

        $markup = '<picture>';
        foreach ($sources as $source) {
            $markup .= $this->renderSource($source);
        }

        return $markup . $img . '</picture>';
    }

    /**
     * @param array<string,mixed> $options
     */
    private function renderImg(string $src, array $options): string
    {
        $fetchpriority = $options['fetchpriority'] ?? null;

        // Never lazy-load the LCP image: when it's flagged high priority and the
        // caller didn't pin a strategy, default to eager. Otherwise lazy.
        $loading = $options['loading'] ?? ($fetchpriority === 'high' ? 'eager' : 'lazy');

        $attributes = [
            'src' => $src,
            'alt' => (string)($options['alt'] ?? ''),
            'width' => $this->stringOrNull($options['width'] ?? null),
            'height' => $this->stringOrNull($options['height'] ?? null),
            'loading' => $loading,
            'decoding' => $options['decoding'] ?? 'async',
            'fetchpriority' => $fetchpriority,
            'sizes' => $options['sizes'] ?? null,
            'srcset' => $options['srcset'] ?? null,
            'class' => $options['class'] ?? null,
        ];

        foreach (($options['attributes'] ?? []) as $name => $value) {
            $attributes[$name] = $value;
        }

        return '<img' . $this->buildAttributes($attributes) . '>';
    }

    /**
     * @param array{srcset:string, type?:string, media?:string, sizes?:string} $source
     */
    private function renderSource(array $source): string
    {
        return '<source' . $this->buildAttributes([
            'srcset' => $source['srcset'] ?? null,
            'type' => $source['type'] ?? null,
            'media' => $source['media'] ?? null,
            'sizes' => $source['sizes'] ?? null,
        ]) . '>';
    }

    /**
     * Serialize an ordered attribute map, skipping null values. A `true` boolean
     * renders the bare attribute name; `false` is skipped. Everything is
     * entity-escaped for a double-quoted attribute.
     *
     * @param array<string, string|int|bool|null> $attributes
     */
    private function buildAttributes(array $attributes): string
    {
        $out = '';
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            $safeName = htmlspecialchars((string)$name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            if ($value === true) {
                $out .= ' ' . $safeName;
                continue;
            }

            $safeValue = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $out .= ' ' . $safeName . '="' . $safeValue . '"';
        }

        return $out;
    }

    private function stringOrNull(int|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string)$value;
    }
}
