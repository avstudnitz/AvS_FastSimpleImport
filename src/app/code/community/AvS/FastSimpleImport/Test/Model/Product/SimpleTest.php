<?php

/**
 * Created by IntelliJ IDEA.
 * Date: 22.02.14
 * Time: 11:42
 * @loadFixture defaultEnvironment.yaml
 */
class AvS_FastSimpleImport_Test_Model_Product_SimpleTest extends EcomDev_PHPUnit_Test_Case
{

    /**
     * @test
     * @loadExpectation
     * @dataProvider dataProvider
     *
     * @param array $values Test values
     * @return void
     */
    public function createProduct($values)
    {
        $this->_getImportModel()->processProductImport($values);

        $sku = (string) $values[0]['sku'];

        /** @var Mage_Catalog_Model_Product $product */
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

        $this->assertNull($product->getNotExistingAttribute());
    }

    /**
     * @test
     * @loadExpectation
     * @loadFixture defaultValues.yaml
     * @dataProvider dataProvider
     *
     * @param array $values Test values
     * @return void
     */
    public function createProductWithDefault($values)
    {
        $this->assertEquals(2, Mage::getStoreConfig('fastsimpleimport/product/status'));
        $this->assertEquals(4, Mage::getStoreConfig('fastsimpleimport/product/tax_class_id'));
        $this->assertEquals(3, Mage::getStoreConfig('fastsimpleimport/product/visibility'));
        $this->assertEquals(12345, Mage::getStoreConfig('fastsimpleimport/product/weight'));

        $this->_getImportModel()->processProductImport($values);
        $sku = (string) $values[0]['sku'];

        /** @var Mage_Catalog_Model_Product $product */
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
     *
     * @param array $values Test values
     * @return void
     */
    public function updateProduct($values)
    {
        $origData = $values[0];
        $sku = (string) $values[0]['sku'];
        $this->_getImportModel()->processProductImport([$origData]);

        $updateData = $values[1];
        $this->_getImportModel()->processProductImport([$updateData]);

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');
        $product->load($product->getIdBySku($sku));
        $afterCreate = $this->expected('%s-%s', $sku, 'create');
        $afterUpdate = $this->expected('%s-%s', $sku, 'update');
        $afterMerge = array_merge($afterCreate->getData(),$afterUpdate->getData());
        foreach ($afterMerge as $key => $value) {
            $this->assertEquals($value, $product->getData($key));
        }

    }

    /**
     * Get the import model
     *
     * @return AvS_FastSimpleImport_Model_Import
     */
    protected function _getImportModel()
    {
        return Mage::getModel('fastsimpleimport/import');
    }

}
