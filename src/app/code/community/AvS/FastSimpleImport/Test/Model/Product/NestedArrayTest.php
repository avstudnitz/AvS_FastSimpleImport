<?php

class AvS_FastSimpleImport_Test_Model_Product_NestedArrayTest extends EcomDev_PHPUnit_Test_Case
{
    protected function setUp()
    {
        Mage::getSingleton('core/resource')->getConnection('core_write')
            ->query('delete from catalog_product_entity');

        parent::setUp();
    }
    /**
     * @test
     * @loadExpectation
     * @dataProvider dataProvider
     */
    public function createProduct($values)
    {
        $productBaseModel = Mage::getModel('catalog/product');

        $this->assertCount(0, $productBaseModel->getCollection()->getAllIds());
        Mage::getModel('fastsimpleimport/import')->setUseNestedArrays(true)->processProductImport($values);

        $this->assertCount(3, $productBaseModel->getCollection()->getAllIds());

        $groupedProducts = $productBaseModel->getCollection()->addFieldToFilter('type_id', array('eq' => 'grouped'))->load();
        $this->assertCount(1, $groupedProducts->getAllIds());

        $sku = $groupedProducts->getFirstItem()->getSku();

        $childrenSkus = $this->expected('%s-%s', $sku, 'children')->getSkus();
        /** @var Mage_Catalog_Model_Product_Type_Grouped $groupedTypeInstance */
        $productId = Mage::getModel('catalog/product')->getIdBySku($sku);
        $productModel = Mage::getModel('catalog/product')->load($productId);
        $groupedTypeInstance = $productModel->getTypeInstance(true);

        $usedProducts = $groupedTypeInstance->getAssociatedProductCollection($productModel);
        foreach ($usedProducts as $product) {
            $this->assertTrue(in_array($product->getSku(), $childrenSkus));
        }
    }
}