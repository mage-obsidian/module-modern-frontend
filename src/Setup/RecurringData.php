<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Setup;

use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * Recurring data script: Magento runs this after every `setup:upgrade`,
 * regardless of module version. Regenerating the contract here closes the gap
 * the WriterPlugin seam cannot reach — adding or editing a module's
 * mage_obsidian_compatibility.xml without a deployment-config write leaves the
 * contract stale until the next upgrade picks it up.
 */
class RecurringData implements InstallDataInterface
{
    public function __construct(
        private readonly ConfigManagerInterface $configManager
    ) {
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context): void
    {
        $this->configManager->generate();
    }
}
