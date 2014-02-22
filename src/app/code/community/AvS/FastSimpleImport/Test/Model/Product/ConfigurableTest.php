<?php

class AvS_FastSimpleImport_Test_Model_Product_ConfigurableTest extends EcomDev_PHPUnit_Test_Case
{

    /**
     * @test
     * @loadExpectation
     * @loadFixture simpleProducts.yaml
     * @dataProvider dataProvider
     */
    public function createProduct($values)
    {
        Mage::getModel('fastsimpleimport/import')->processProductImport($values);

        $sku = (string) $values[0]['sku'];
        $product = Mage::getModel('catalog/product');
        $product->load($product->getIdBySku($sku));
        $expected = $this->expected('%s-%s', $sku, 1);

        foreach ($expected as $key => $value) {
            $this->assertEquals($value, $product->getData($key));
        }

        $stockExpectedItem = $this->expected('%s-%s', $sku, 'stock');
        $stock = $product->getStockItem();
        foreach ($stockExpectedItem as $key => $value) {
            $this->assertEquals($value, $stock->getData($key), null, 0);
        }
    }

    /**
     * @test
     * @loadExpectation
     * @loadFixture defaultValues.yaml
     * @dataProvider dataProvider
     */
    public function createProductWithDefault($values)
    {
        $this->assertEquals(2, Mage::getStoreConfig('fastsimpleimport/product/status'));
        $this->assertEquals(4, Mage::getStoreConfig('fastsimpleimport/product/tax_class_id'));
        $this->assertEquals(3, Mage::getStoreConfig('fastsimpleimport/product/visibility'));
        $this->assertEquals(12345, Mage::getStoreConfig('fastsimpleimport/product/weight'));
        Mage::getModel('fastsimpleimport/import')->processProductImport($values);
        $sku = (string) $values[0]['sku'];
        $product = Mage::getModel('catalog/product');
        $product->load($product->getIdBySku($sku));
        $expected = $this->expected('%s-%s', $sku, 1);

        foreach ($expected as $key => $value) {
            $this->assertEquals($value, $product->getData($key));
        }
    }


    /**
     * @test
     * @loadExpectation
     * @dataProvider dataProvider
     */
    public function updateProduct($values)
    {
        $origData = $values[0];
        $sku = (string) $values[0]['sku'];
        Mage::getModel('fastsimpleimport/import')->processProductImport(array($origData));

        $updateData = $values[1];
        Mage::getModel('fastsimpleimport/import')->processProductImport(array($updateData));

        $product = Mage::getModel('catalog/product');
        $product->load($product->getIdBySku($sku));
        $afterCreate = $this->expected('%s-%s', $sku, 'create');
        $afterUpdate = $this->expected('%s-%s', $sku, 'update');
        $afterMerge = array_merge($afterCreate->getData(),$afterUpdate->getData());
        foreach ($afterMerge as $key => $value) {
            $this->assertEquals($value, $product->getData($key));
        }

    }
}