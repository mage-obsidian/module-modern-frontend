<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Dev;

use Magento\Framework\HTTP\Client\CurlFactory;

/**
 * Probes URLs with cURL, tuned for self-checks against a local dev stack: no
 * proxy (the dev host is internal), relaxed TLS (self-signed certs) and a short
 * timeout so the doctor never hangs. A fresh client per call avoids option bleed.
 */
class CurlHttpProber implements HttpProberInterface
{
    private const TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly CurlFactory $curlFactory
    ) {
    }

    public function probe(string $url): ProbeResult
    {
        try {
            $curl = $this->curlFactory->create();
            $curl->setOptions([
                CURLOPT_PROXY => '',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $curl->get($url);

            $status = $curl->getStatus();
            $headers = $curl->getHeaders();
            $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
            if (is_array($contentType)) {
                $contentType = implode('; ', $contentType);
            }

            return new ProbeResult(
                $status >= 200 && $status < 400,
                $status,
                (string)$contentType
            );
        } catch (\Throwable $e) {
            return new ProbeResult(false, 0, '', $e->getMessage());
        }
    }
}
