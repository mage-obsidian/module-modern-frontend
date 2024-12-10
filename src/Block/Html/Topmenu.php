<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Block\Html;

use Magento\Framework\Data\Tree\Node;
use Magento\Theme\Block\Html\Topmenu as BaseTopmenu;

class Topmenu extends BaseTopmenu
{
    /**
     * Get menu item classes.
     *
     * @param Node $item
     *
     * @return array
     */
    protected function _getMenuItemClasses(Node $item): array
    {
        $classes = [
                'level' . $item->getLevel(),
                $item->getPositionClass(),
        ];

        if ($item->getIsCategory()) {
            $classes[] = 'category-item';
        }

        if ($item->getIsFirst()) {
            $classes[] = 'first';
        }

        if ($item->getIsLast()) {
            $classes[] = 'last';
        }

        if ($item->getClass()) {
            $classes[] = $item->getClass();
        }

        if ($item->hasChildren()) {
            $classes[] = 'parent';
            $classes[] = 'group/' . $this->getLevelName($item->getLevel());
            $classes[] = 'group-hover/' . $this->getLevelName($item->getLevel()) . ':text-link';
        }

        return $classes;
    }

    /**
     * Add sub menu HTML code for current menu item
     *
     * @param Node $child
     * @param string $childLevel
     * @param string $childrenWrapClass
     * @param int $limit
     *
     * @return string HTML code
     */
    protected function _addSubMenu($child, $childLevel, $childrenWrapClass, $limit): string
    {
        $html = '';
        if (!$child->hasChildren()) {
            return $html;
        }

        // Clases específicas para submenús
        //getLevelName convertido a texcto
        $levelName = $this->getLevelName((int)$childLevel);
        $submenuClasses = [
                'hidden',
                'submenu',
                "group-hover/{$levelName}:flex"
        ];

        if ((int)$childLevel === 0) {
            $submenuClasses[] = 'left-0';
        } else {
            $submenuClasses[] = 'left-full top-0';
        }

        // Incluye el submenú como parte de un contenedor específico
        $html .= '<ul class="' . implode(' ', $submenuClasses) . '">';
        $html .= $this->_getHtml($child, $childrenWrapClass, $limit);
        $html .= '</ul>';

        return $html;
    }

    /**
     * Get level name.
     *
     * @param int $childLevel
     *
     * @return string
     */
    private function getLevelName(int $childLevel): string
    {
        if ($childLevel === 0) {
            return 'main';
        }
        // Generar dinámicamente el nombre del nivel para niveles mayores
        return 'sub' . str_repeat('-sub', max(0, $childLevel - 1));
    }
}
