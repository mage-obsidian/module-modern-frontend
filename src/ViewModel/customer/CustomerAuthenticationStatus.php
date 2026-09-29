<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

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
