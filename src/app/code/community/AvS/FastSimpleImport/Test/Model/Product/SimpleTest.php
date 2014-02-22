<?php
/**
 * Created by IntelliJ IDEA.
 * Date: 22.02.14
 * Time: 11:42
 */

class AvS_FastSimpleImport_Test_Model_Product_SimpleTest extends EcomDev_PHPUnit_Test_Case
{

    /**
     * @test
     * @loadExpectation
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
        foreach($stockExpectedItem as $key => $value) {
            $this->assertEquals($value, $stock->getData($key),null,0);
        }

        $this->assertNull($product->getNotExistingAttribute());
    }
}