<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Service\Csp;

use Magento\Csp\Model\SubresourceIntegrity\SriEnabledActions;
use Magento\Framework\ObjectManagerInterface;

class PaymentPages
{
    private ?bool $coreDeclaresPaymentPages = null;

    private ?object $coreActions = null;

    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly array $fallbackActions = [],
        private readonly string $coreActionsClass = SriEnabledActions::class
    ) {
    }

    public function isPaymentPage(string $fullActionName): bool
    {
        $this->coreDeclaresPaymentPages ??= class_exists($this->coreActionsClass);

        if (!$this->coreDeclaresPaymentPages) {
            return in_array($fullActionName, $this->fallbackActions, true);
        }

        $this->coreActions ??= $this->objectManager->get($this->coreActionsClass);

        return $this->coreActions->isPaymentPageAction($fullActionName);
    }
}
