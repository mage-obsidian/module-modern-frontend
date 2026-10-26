<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Csp;

use Magento\Csp\Model\SubresourceIntegrity\SriEnabledActions;
use Magento\Framework\ObjectManagerInterface;
use MageObsidian\ModernFrontend\Service\Csp\PaymentPages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaymentPagesTest extends TestCase
{
    private const string MISSING_CORE_CLASS = 'Magento\\Csp\\Model\\SubresourceIntegrity\\AbsentFromThisRelease';

    private const array FALLBACK = [
        'checkout_index_index',
        'checkout_onepage_success',
        'multishipping_checkout_billing',
    ];

    public function testAnActionTheCoreDeclaresIsAPaymentPageAndTheCoreIsResolvedOnce(): void
    {
        $this->requireCoreActions();
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->once())
            ->method('get')
            ->with(SriEnabledActions::class)
            ->willReturn(new SriEnabledActions(['checkout_index_index']));

        $pages = new PaymentPages($objectManager, self::FALLBACK, SriEnabledActions::class);

        $this->assertTrue($pages->isPaymentPage('checkout_index_index'));
        $this->assertFalse($pages->isPaymentPage('cms_index_index'));
    }

    public function testTheCoreListWinsOverTheFallbackWhenTheCoreHasOne(): void
    {
        $this->requireCoreActions();
        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn(new SriEnabledActions(['merchant_pay_index']));

        $pages = new PaymentPages($objectManager, self::FALLBACK, SriEnabledActions::class);

        $this->assertTrue($pages->isPaymentPage('merchant_pay_index'));
        $this->assertFalse($pages->isPaymentPage('checkout_index_index'));
    }

    public static function fallbackActions(): array
    {
        return array_map(static fn (string $action): array => [$action], array_combine(self::FALLBACK, self::FALLBACK));
    }

    #[DataProvider('fallbackActions')]
    public function testWithoutTheCoreClassTheFallbackActionsArePaymentPages(string $action): void
    {
        $this->assertTrue($this->withoutCore()->isPaymentPage($action));
    }

    public function testWithoutTheCoreClassAnyOtherActionIsNotAPaymentPage(): void
    {
        $this->assertFalse($this->withoutCore()->isPaymentPage('catalog_category_view'));
    }

    public function testWithoutTheCoreClassTheObjectManagerIsNeverAsked(): void
    {
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->never())->method('get');

        $pages = new PaymentPages($objectManager, self::FALLBACK, self::MISSING_CORE_CLASS);
        $pages->isPaymentPage('checkout_index_index');
        $pages->isPaymentPage('catalog_category_view');
    }

    private function withoutCore(): PaymentPages
    {
        return new PaymentPages(
            $this->createStub(ObjectManagerInterface::class),
            self::FALLBACK,
            self::MISSING_CORE_CLASS
        );
    }

    private function requireCoreActions(): void
    {
        if (!class_exists(SriEnabledActions::class)) {
            $this->markTestSkipped('This Magento_Csp does not declare payment pages.');
        }
    }
}
