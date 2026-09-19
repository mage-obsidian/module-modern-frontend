<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\I18n;

class TwigPhraseExtractor
{
    private const OUTPUT_OPEN = '{{';
    private const OUTPUT_CLOSE = '}}';
    private const STATEMENT_OPEN = '{%';
    private const STATEMENT_CLOSE = '%}';
    private const COMMENT_OPEN = '{#';
    private const COMMENT_CLOSE = '#}';

    public function extractFromString(string $contents): array
    {
        $phrases = [];
        $length = strlen($contents);
        $position = 0;
        $verbatim = false;

        while ($position < $length) {
            $opening = substr($contents, $position, 2);

            if ($opening === self::COMMENT_OPEN) {
                $closing = strpos($contents, self::COMMENT_CLOSE, $position + 2);
                $position = $closing === false ? $length : $closing + 2;
                continue;
            }

            if ($opening !== self::OUTPUT_OPEN && $opening !== self::STATEMENT_OPEN) {
                $position++;
                continue;
            }

            $close = $opening === self::OUTPUT_OPEN ? self::OUTPUT_CLOSE : self::STATEMENT_CLOSE;
            $end = $this->endOfBlock($contents, $position + 2, $close);
            $block = substr($contents, $position + 2, $end - $position - 2);
            $position = $end + 2;

            if ($opening === self::STATEMENT_OPEN) {
                $keyword = strtolower(trim($block, " \t\r\n-"));
                if ($keyword === 'verbatim') {
                    $verbatim = true;
                    continue;
                }
                if ($keyword === 'endverbatim') {
                    $verbatim = false;
                    continue;
                }
            }

            if ($verbatim) {
                continue;
            }

            foreach ($this->phrasesIn($block) as $phrase) {
                if (!in_array($phrase, $phrases, true)) {
                    $phrases[] = $phrase;
                }
            }
        }

        return $phrases;
    }

    private function endOfBlock(string $contents, int $from, string $close): int
    {
        $length = strlen($contents);
        $position = $from;

        while ($position < $length) {
            $character = $contents[$position];
            if ($character === '"' || $character === "'") {
                $position = $this->endOfLiteral($contents, $position) + 1;
                continue;
            }
            if (substr($contents, $position, 2) === $close) {
                return $position;
            }
            $position++;
        }

        return $length;
    }

    private function endOfLiteral(string $contents, int $from): int
    {
        $quote = $contents[$from];
        $length = strlen($contents);
        $position = $from + 1;

        while ($position < $length) {
            if ($contents[$position] === '\\') {
                $position += 2;
                continue;
            }
            if ($contents[$position] === $quote) {
                return $position;
            }
            $position++;
        }

        return $length;
    }

    private function phrasesIn(string $block): array
    {
        $phrases = [];
        $length = strlen($block);
        $position = 0;

        while ($position < $length) {
            $character = $block[$position];

            if ($character === '"' || $character === "'") {
                $position = $this->endOfLiteral($block, $position) + 1;
                continue;
            }

            if ($character !== '_' || substr($block, $position, 2) !== '__') {
                $position++;
                continue;
            }

            if (!$this->isStandaloneCall($block, $position)) {
                $position += 2;
                continue;
            }

            $phrase = $this->literalArgument($block, $position + 2);
            $position += 2;
            if ($phrase !== null && $phrase !== '') {
                $phrases[] = $phrase;
            }
        }

        return $phrases;
    }

    private function isStandaloneCall(string $block, int $position): bool
    {
        $before = $position > 0 ? $block[$position - 1] : ' ';
        if ($before === '.' || $before === '_' || ctype_alnum($before)) {
            return false;
        }

        $after = $position + 2;
        $length = strlen($block);
        while ($after < $length && ctype_space($block[$after])) {
            $after++;
        }

        return $after < $length && $block[$after] === '(';
    }

    private function literalArgument(string $block, int $from): ?string
    {
        $length = strlen($block);
        $position = $from;

        while ($position < $length && ctype_space($block[$position])) {
            $position++;
        }
        if ($position >= $length || $block[$position] !== '(') {
            return null;
        }

        $position++;
        while ($position < $length && ctype_space($block[$position])) {
            $position++;
        }
        if ($position >= $length) {
            return null;
        }

        $quote = $block[$position];
        if ($quote !== '"' && $quote !== "'") {
            return null;
        }

        $end = $this->endOfLiteral($block, $position);
        if ($end >= $length) {
            return null;
        }

        $raw = substr($block, $position + 1, $end - $position - 1);
        $after = $end + 1;
        while ($after < $length && ctype_space($block[$after])) {
            $after++;
        }
        if ($after >= $length || ($block[$after] !== ',' && $block[$after] !== ')')) {
            return null;
        }

        if ($quote === '"' && str_contains($raw, '#{')) {
            return null;
        }

        return $this->unescape($raw, $quote);
    }

    private function unescape(string $raw, string $quote): string
    {
        $replacements = ['\\\\' => '\\', '\\' . $quote => $quote];
        if ($quote === '"') {
            $replacements += ['\\n' => "\n", '\\r' => "\r", '\\t' => "\t"];
        }

        return strtr($raw, $replacements);
    }
}
