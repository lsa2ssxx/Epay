<?php

use PHPUnit\Framework\TestCase;

final class BepusdtPluginMetadataTest extends TestCase
{
    public function testCatalogContainsTonTypesAndFitsExpandedDatabaseColumn(): void
    {
        require_once EPAY_TEST_ROOT.'/plugins/bepusdt/bepusdt_plugin.php';
        $names = array_column(bepusdt_plugin::tradeTypeCatalog(), 'name');

        self::assertContains('usdt.ton', $names);
        self::assertContains('ton.gram', $names);
        self::assertSame(count($names), count(array_unique($names)));
        self::assertLessThanOrEqual(500, strlen(implode(',', $names)));
    }

    public function testTonCategoryAndIconsResolve(): void
    {
        self::assertSame(['USDT', 'TON'], pay_type_category_derive('usdt.ton'));
        self::assertSame(['GRAM', null], pay_type_category_derive('ton.gram'));

        $overlay = pay_type_chain_overlay_for('usdt.ton');
        self::assertSame('logo', $overlay['type']);
        self::assertSame('/assets/icon/chain/ton.svg', $overlay['src']);
        self::assertSame('/assets/icon/ton.gram.svg', pay_type_icon_src('ton.gram'));
    }
}
