<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Controller\Cms;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Filesystem;
use MageObsidian\ModernFrontend\Service\Cms\DeltaStylesheet;

/**
 * Serves the delta stylesheet under a URL that never changes.
 *
 * Not a plain media file: Magento's stock nginx config sends `expires +1y` for
 * any `.css` under `/media/`, so a stable URL there could not be invalidated for
 * a year, and a versioned one would put a changing href in the head of every
 * page — invalidating the full page cache each time an author writes a new class.
 *
 * Here the href stays fixed and the ETag does the work: the page cache is never
 * touched, and a client picks up new CSS on its next revalidation.
 */
class Css implements HttpGetActionInterface
{
    private const int MAX_AGE = 300;
    private const int STALE_WHILE_REVALIDATE = 86400;

    public function __construct(
        private readonly HttpRequest $request,
        private readonly HttpResponse $response,
        private readonly Filesystem $filesystem,
        private readonly DeltaStylesheet $delta
    ) {
    }

    public function execute(): ResultInterface|HttpResponse
    {
        $state = $this->delta->state();
        $etag = '"' . ($state['hash'] ?: 'empty') . '"';

        $this->response->setHeader('Content-Type', 'text/css; charset=UTF-8', true);
        $this->response->setHeader('ETag', $etag, true);
        $this->response->setHeader(
            'Cache-Control',
            sprintf('public, max-age=%d, stale-while-revalidate=%d', self::MAX_AGE, self::STALE_WHILE_REVALIDATE),
            true
        );

        if ($this->request->getHeader('If-None-Match') === $etag) {
            $this->response->setHttpResponseCode(304);

            return $this->response;
        }

        $this->response->setBody($this->read());

        return $this->response;
    }

    private function read(): string
    {
        $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $path = $this->delta->relativePath();

        return $media->isExist($path) ? (string)$media->readFile($path) : '';
    }
}
