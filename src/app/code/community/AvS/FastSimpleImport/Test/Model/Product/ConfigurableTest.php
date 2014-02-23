<?php

class AvS_FastSimpleImport_Test_Model_Product_ConfigurableTest extends EcomDev_PHPUnit_Test_Case
{
    protected function setUp()
    {
        Mage::getSingleton('core/resource')->getConnection('core_write')
        ->query('delete from catalog_product_entity');

        $model = Mage::getModel('eav/entity_setup', 'core_setup');
        $attributeId = $model->getAttribute('catalog_product', 'color');
        $attributeSetId = $model->getDefaultAttributeSetId('catalog_product');
        //Get attribute group info
        $attributeGroupId = $model->getAttributeGroup('catalog_product', $attributeSetId, 'General');
        //add attribute to a set
        $model->addAttributeToSet('catalog_product', $attributeSetId, $attributeGroupId['attribute_group_id'], $attributeId['attribute_id']);

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
        Mage::getModel('fastsimpleimport/import')->setDropdownAttributes(array('color'))->processProductImport($values);

        $this->assertCount(3, $productBaseModel->getCollection()->getAllIds());

        $configurableProducts = $productBaseModel->getCollection()->addFieldToFilter('type_id' ,array('eq' => 'configurable'))->load();
        $this->assertCount(1, $configurableProducts->getAllIds());

        $sku = $configurableProducts->getFirstItem()->getSku();

        $code = $this->expected('%s-%s', $sku, 'children')->getCodes();
        $childrenSkus = $this->expected('%s-%s', $sku, 'children')->getSkus();
        /** @var Mage_Catalog_Model_Product_Type_Configurable $configurable */
        $productId = Mage::getModel('catalog/product')->getIdBySku($sku);
        $productModel = Mage::getModel('catalog/product')->load($productId);
        $configurable = $productModel->getTypeInstance(true);
        $configurableOptions = $configurable->getConfigurableAttributesAsArray($productModel);
        foreach ($configurableOptions as $option) {
            $this->assertTrue(in_array($option['attribute_code'], $code));
        }

        $usedProducts = $configurable->getUsedProducts(null, $productModel);
        foreach ($usedProducts as $product) {
            $this->assertTrue(in_array($product->getSku(), $childrenSkus));
        }


    }
}