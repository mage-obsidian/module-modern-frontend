<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

class PrePaintConfig implements ArgumentInterface
{
    private const STORAGE_KEY = 'mage-cache-storage';

    private const VERSION_KEY = 'mage-cache-storage-section-version';

    private const VERSION_COOKIE = 'private_content_version';

    private const SESSION_COOKIE = 'mage-cache-sessid';

    private const KIND_SIZE = 'size';

    public function __construct(
        private readonly SectionDataConfig $sectionDataConfig,
        private readonly ViteResolver $viteResolver,
        private readonly array $counters = [],
        private readonly array $flags = []
    ) {
    }

    public function getConfig(): array
    {
        $sections = $this->sectionDataConfig->getConfig();

        return [
            'storageKey' => self::STORAGE_KEY,
            'versionKey' => self::VERSION_KEY,
            'versionCookie' => self::VERSION_COOKIE,
            'sessionCookie' => self::SESSION_COOKIE,
            'lifetime' => $sections['lifetime'],
            'expirable' => $sections['expirable'],
            'counters' => $this->buildCounters(),
            'flags' => $this->buildFlags(),
        ];
    }

    public function isEmpty(): bool
    {
        return $this->buildCounters() === [] && $this->buildFlags() === [];
    }

    private function buildCounters(): array
    {
        $counters = [];
        foreach ($this->validEntries($this->counters) as $name => $entry) {
            if (($entry['island'] ?? '') === '') {
                continue;
            }
            $counters[] = [
                'section' => $entry['section'],
                'field' => $entry['field'],
                'kind' => $entry['kind'],
                'island' => $this->viteResolver->getComponentFile($entry['island']),
                'property' => '--mo-count-' . $name,
                'attribute' => 'data-mo-count-' . $name,
            ];
        }

        return $counters;
    }

    private function buildFlags(): array
    {
        $flags = [];
        foreach ($this->validEntries($this->flags) as $name => $entry) {
            $flags[] = [
                'section' => $entry['section'],
                'field' => $entry['field'],
                'attribute' => 'data-mo-' . $name,
            ];
        }

        return $flags;
    }

    private function validEntries(array $entries): array
    {
        $valid = [];
        foreach ($entries as $name => $entry) {
            if (!is_string($name) || preg_match('/^[a-z0-9-]+$/', $name) !== 1) {
                continue;
            }
            if (!is_array($entry) || ($entry['section'] ?? '') === '' || ($entry['field'] ?? '') === '') {
                continue;
            }
            $valid[$name] = [
                'section' => (string)$entry['section'],
                'field' => (string)$entry['field'],
                'kind' => ($entry['kind'] ?? '') === self::KIND_SIZE ? self::KIND_SIZE : '',
                'island' => (string)($entry['island'] ?? ''),
            ];
        }

        return $valid;
    }
}
