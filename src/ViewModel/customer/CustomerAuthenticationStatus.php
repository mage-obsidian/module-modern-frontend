<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos
 */

namespace MageObsidian\ModernFrontend\ViewModel\customer;

use Magento\Framework\App\Http\Context;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class CustomerAuthenticationStatus implements ArgumentInterface
{
    /**
     * Status constructor.
     *
     * @param Context $httpContext
     */
    public function __construct(
        private readonly Context $httpContext
    ) {
    }

    /**
     * Checking customer login status
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return (bool)$this->httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_AUTH);
    }
}
