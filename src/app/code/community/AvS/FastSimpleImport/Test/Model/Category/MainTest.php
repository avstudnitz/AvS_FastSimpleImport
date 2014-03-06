<?php
/**
 * Created by IntelliJ IDEA.
 * Date: 22.02.14
 * Time: 17:09
 */

class AvS_FastSimpleImport_Test_Model_Category_MainTest extends EcomDev_PHPUnit_Test_Case
{
    const CATEGORY_BASIC_COUNT = 2;

    /**
     * @test
     * @dataProvider dataProvider
     *
     */
    public function createCategoryTree($values)
    {
        Mage::getSingleton('core/resource')->getConnection('core_write')
        ->query('delete from catalog_category_entity where entity_id > 2');

        $collection = Mage::getModel('catalog/category')->getCollection();
        $this->assertCount(self::CATEGORY_BASIC_COUNT, $collection->getAllIds());
        $import = Mage::getModel('fastsimpleimport/import');
        $this->assertNotNull($import);
        $import->processCategoryImport($values);
        $this->assertCount(self::CATEGORY_BASIC_COUNT + count($values), $collection->getAllIds());
        $rootCategory = Mage::getModel('catalog/category')->load(1);
        $this->assertNotNull($rootCategory);
        $this->assertEquals(count($values) + 1, $rootCategory->getChildrenCount());
        $baseCategory = Mage::getModel('catalog/category')->load(2);
        $this->assertNotNull($baseCategory);
        $this->assertEquals(count($values), $baseCategory->getChildrenCount());

    }

    /**
     * @test
     * @dataProvider dataProvider
     *
     */
    public function deleteSingleCategory($values)
    {
        $collection = Mage::getModel('catalog/category')->getCollection();
        /** @var AvS_FastSimpleImport_Model_Import $import */
        $import = Mage::getModel('fastsimpleimport/import');
        $import->setBehavior(Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);
        $this->assertCount(5, $collection->getAllIds());
        $import->processCategoryImport($values);
        $this->assertCount(4, $collection->getAllIds());
        $rootCategory = Mage::getModel('catalog/category')->load(1);
        $this->assertNotNull($rootCategory);
        $this->assertEquals(3, $rootCategory->getChildrenCount());
        $baseCategory = Mage::getModel('catalog/category')->load(2);
        $this->assertNotNull($baseCategory);
        $this->assertEquals(2, $baseCategory->getChildrenCount());
    }


}