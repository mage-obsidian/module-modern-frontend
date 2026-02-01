<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Migrate stored config from the legacy hyphenated namespace `mage-obsidian/*`
 * to `mage_obsidian/*`.
 *
 * The hyphen is illegal in both system.xml section ids and <config_path>
 * patterns ([a-zA-Z0-9_]+), so the prefix had to become underscore-based to be
 * editable from the admin. config.xml defaults already moved; this patch carries
 * over any value an operator persisted (e.g. an HMR toggle) so it is not silently
 * lost. Idempotent: only rows still under the old prefix are touched, and a
 * conflicting new-prefix row (unique on scope/scope_id/path) is removed first so
 * the explicit legacy value wins.
 */
class MigrateConfigNamespace implements DataPatchInterface
{
    private const LEGACY_PREFIX = 'mage-obsidian/';
    private const NEW_PREFIX = 'mage_obsidian/';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');

        $legacyRows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['config_id', 'scope', 'scope_id', 'path'])
                ->where('path LIKE ?', self::LEGACY_PREFIX . '%')
        );

        foreach ($legacyRows as $row) {
            $newPath = self::NEW_PREFIX . substr($row['path'], strlen(self::LEGACY_PREFIX));

            // Drop a pre-existing target row (same scope) so the unique
            // scope/scope_id/path key does not reject the rename.
            $connection->delete($table, [
                'scope = ?' => $row['scope'],
                'scope_id = ?' => (int)$row['scope_id'],
                'path = ?' => $newPath,
            ]);

            $connection->update(
                $table,
                ['path' => $newPath],
                ['config_id = ?' => (int)$row['config_id']]
            );
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
