<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CheckoutLayout implements OptionSourceInterface
{
    public const string STEPPED = 'stepped';
    public const string ONEPAGE = 'onepage';

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::STEPPED, 'label' => __('Stepped wizard (Identification → Shipping → Payment → Review)')],
            ['value' => self::ONEPAGE, 'label' => __('One-page (all sections on a single screen)')],
        ];
    }
}
