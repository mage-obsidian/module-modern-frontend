<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ProductCondition implements OptionSourceInterface
{
    public const string NEW_CONDITION = 'NewCondition';
    public const string REFURBISHED_CONDITION = 'RefurbishedCondition';
    public const string USED_CONDITION = 'UsedCondition';
    public const string DAMAGED_CONDITION = 'DamagedCondition';

    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('Do not emit')],
            ['value' => self::NEW_CONDITION, 'label' => __('New')],
            ['value' => self::REFURBISHED_CONDITION, 'label' => __('Refurbished')],
            ['value' => self::USED_CONDITION, 'label' => __('Used')],
            ['value' => self::DAMAGED_CONDITION, 'label' => __('Damaged')],
        ];
    }
}
