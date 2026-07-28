<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;
use MageObsidian\ModernFrontend\Service\Cms\IslandRenderer;

/**
 * The "Insert Widget" path to a Vue island: an author picks a registered
 * component from a dropdown instead of writing a marker by hand.
 *
 * Emits the marker directly rather than through a template — there is nothing
 * to lay out around it, and a wrapper would end up inside the hydration
 * boundary of whatever gets mounted.
 */
class Island extends Template implements BlockInterface
{
    public const string COMPONENT = 'component';
    public const string PROPS = 'props';
    public const string STRATEGY = 'strategy';

    public function __construct(
        Context $context,
        private readonly IslandRenderer $renderer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        $component = $this->getData(self::COMPONENT);
        if (!is_string($component) || $component === '') {
            return '';
        }

        $props = $this->getData(self::PROPS);

        return $this->renderer->render(
            $component,
            is_string($props) || is_array($props) ? $props : null,
            (string)$this->getData(self::STRATEGY)
        );
    }
}
