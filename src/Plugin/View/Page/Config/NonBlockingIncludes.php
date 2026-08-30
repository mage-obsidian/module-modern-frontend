<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Plugin\View\Page\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Framework\View\Page\Config;
use Magento\Store\Model\ScopeInterface;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;

class NonBlockingIncludes
{
    public const string BLOCKING_ATTRIBUTE = 'data-obsidian-blocking';
    public const string SHEET_ATTRIBUTE = 'data-obsidian-include-sheet';

    private const array PRESERVED_SHEET_ATTRIBUTES = [
        'media',
        'integrity',
        'crossorigin',
        'referrerpolicy',
        'id',
        'title',
    ];

    private const string OPEN_TAG_PATTERN =
        '/<(script|link)(?=[\s\/>])((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/iA';
    private const string ATTRIBUTE_PATTERN =
        '/([^\s"\'>\/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]*)))?/A';

    private const string SWAP_SCRIPT = <<<'JS'
(function () {
    var sheets = document.querySelectorAll('link[data-obsidian-include-sheet]');
    if (!sheets.length) {
        return;
    }
    Array.prototype.forEach.call(sheets, function (sheet) {
        var apply = function () {
            sheet.rel = 'stylesheet';
        };
        sheet.addEventListener('load', apply, { once: true });
        document.addEventListener('DOMContentLoaded', apply, { once: true });
    });
})();
JS;

    public function __construct(
        private readonly ObsidianThemeDetector $themeDetector,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly SecureHtmlRenderer $secureRenderer
    ) {
    }

    public function afterGetIncludes(Config $subject, string $result): string
    {
        if (trim($result) === '') {
            return $result;
        }

        if (!$this->themeDetector->isActive()) {
            return $result;
        }

        $deferScripts = $this->isEnabled(ConfigProvider::HEAD_INCLUDES_DEFER_SCRIPTS);
        $deferStyles = $this->isEnabled(ConfigProvider::HEAD_INCLUDES_DEFER_STYLES);
        if (!$deferScripts && !$deferStyles) {
            return $result;
        }

        $edits = [];
        $sheets = 0;
        $inlineAhead = false;

        foreach (array_reverse($this->scan($result)) as $token) {
            if ($token['name'] === 'script') {
                if (!$this->hasValue($token['attributes'], 'src')) {
                    $inlineAhead = true;
                    continue;
                }
                if ($deferScripts && !$inlineAhead && $this->isDeferrableScript($token['attributes'])) {
                    $edits[] = [$token['start'], strlen($token['tag']), $this->withDefer($token['tag'])];
                }
                continue;
            }

            if ($deferStyles && $this->isDeferrableSheet($token['attributes'])) {
                $edits[] = [
                    $token['start'],
                    strlen($token['tag']),
                    $this->sheetMarkup($token['attributes']),
                ];
                $sheets++;
            }
        }

        if ($edits === []) {
            return $result;
        }

        foreach ($edits as [$start, $length, $replacement]) {
            $result = substr_replace($result, $replacement, $start, $length);
        }

        if ($sheets > 0) {
            $result .= $this->secureRenderer->renderTag('script', [], self::SWAP_SCRIPT, false);
        }

        return $result;
    }

    private function isEnabled(string $path): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE);
    }

    private function scan(string $html): array
    {
        $tokens = [];
        $length = strlen($html);
        $offset = 0;

        while (($position = strpos($html, '<', $offset)) !== false) {
            if (substr($html, $position, 4) === '<!--') {
                $end = strpos($html, '-->', $position + 4);
                $offset = $end === false ? $length : $end + 3;
                continue;
            }

            if (!preg_match(self::OPEN_TAG_PATTERN, $html, $matches, 0, $position)) {
                $offset = $position + 1;
                continue;
            }

            $tag = $matches[0];
            $name = strtolower($matches[1]);
            $tokens[] = [
                'name' => $name,
                'tag' => $tag,
                'start' => $position,
                'attributes' => $this->parseAttributes($matches[2]),
            ];
            $offset = $position + strlen($tag);

            if ($name === 'script' && !$this->isSelfClosing($tag)) {
                $close = stripos($html, '</script', $offset);
                if ($close !== false) {
                    $offset = $close;
                }
            }
        }

        return $tokens;
    }

    private function parseAttributes(string $raw): array
    {
        $attributes = [];
        $length = strlen($raw);
        $offset = 0;

        while ($offset < $length) {
            if (!preg_match(self::ATTRIBUTE_PATTERN, $raw, $matches, 0, $offset) || $matches[0] === '') {
                $offset++;
                continue;
            }
            $value = '';
            foreach ([2, 3, 4] as $group) {
                if (isset($matches[$group]) && $matches[$group] !== '') {
                    $value = $matches[$group];
                    break;
                }
            }
            $attributes[strtolower($matches[1])] = $value;
            $offset += strlen($matches[0]);
        }

        return $attributes;
    }

    private function isDeferrableScript(array $attributes): bool
    {
        if (array_key_exists(self::BLOCKING_ATTRIBUTE, $attributes)) {
            return false;
        }
        if (array_key_exists('async', $attributes) || array_key_exists('defer', $attributes)) {
            return false;
        }

        return strtolower(trim($attributes['type'] ?? '')) !== 'module';
    }

    private function isDeferrableSheet(array $attributes): bool
    {
        if (array_key_exists(self::BLOCKING_ATTRIBUTE, $attributes)) {
            return false;
        }
        if (!$this->hasValue($attributes, 'href')) {
            return false;
        }

        if ($this->isPrintOnly($attributes)) {
            return false;
        }

        $rel = preg_split('/\s+/', strtolower(trim($attributes['rel'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);

        return $rel === ['stylesheet'];
    }

    private function isPrintOnly(array $attributes): bool
    {
        return preg_replace('/\s+/', '', strtolower($attributes['media'] ?? '')) === 'print';
    }

    private function hasValue(array $attributes, string $name): bool
    {
        return trim($attributes[$name] ?? '') !== '';
    }

    private function isSelfClosing(string $tag): bool
    {
        return str_ends_with(rtrim(substr($tag, 0, -1)), '/');
    }

    private function withDefer(string $tag): string
    {
        $inner = rtrim(substr($tag, 0, -1));
        $selfClosing = $this->isSelfClosing($tag);
        if ($selfClosing) {
            $inner = rtrim(substr($inner, 0, -1));
        }

        return $inner . ' defer' . ($selfClosing ? '/' : '') . '>';
    }

    private function sheetMarkup(array $attributes): string
    {
        $href = $this->attributeValue($attributes['href']);
        $preserved = '';
        foreach ($attributes as $name => $value) {
            if (in_array($name, self::PRESERVED_SHEET_ATTRIBUTES, true)) {
                $preserved .= ' ' . $name . $this->attributeAssignment($value);
            }
        }

        return '<link rel="preload" as="style" href="' . $href . '"' . $preserved . ' '
            . self::SHEET_ATTRIBUTE . '/>'
            . '<noscript><link rel="stylesheet" href="' . $href . '"' . $preserved . '/></noscript>';
    }

    private function attributeAssignment(string $value): string
    {
        return trim($value) === '' ? '' : '="' . $this->attributeValue($value) . '"';
    }

    private function attributeValue(string $value): string
    {
        return str_replace('"', '&quot;', trim($value));
    }
}
