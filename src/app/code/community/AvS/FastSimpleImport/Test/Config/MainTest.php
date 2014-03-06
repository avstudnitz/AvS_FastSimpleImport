<?php
/**
 * Created by IntelliJ IDEA.
 * Date: 22.02.14
 * Time: 11:30
 */

class AvS_FastSimpleImport_Test_Config_MainTest extends EcomDev_PHPUnit_Test_Case_Config
{

    /**
     * @test
     */
    public function testIfCategoryEntityIsLoadedProperly()
    {
        $this->assertModelAlias('fastsimpleimport/import_entity_category', 'AvS_FastSimpleImport_Model_Import_Entity_Category');
    }

    /**
     * @test
     */
    public function testIfSimpleProductEntityIsLoadedProperly()
    {
        $this->assertModelAlias('fastsimpleimport/import_entity_product_type_simple', 'AvS_FastSimpleImport_Model_Import_Entity_Product_Type_Simple');
    }

    /**
     * @test
     */
    public function testIfConfigurableProductEntityIsLoadedProperly()
    {
        $this->assertModelAlias('fastsimpleimport/import_entity_product_type_configurable', 'AvS_FastSimpleImport_Model_Import_Entity_Product_Type_Configurable');
    }

    /**
     * @test
     */
    public function testIfVirtualProductEntityIsLoadedProperly()
    {
        $this->assertModelAlias('fastsimpleimport/import_entity_product_type_virtual', 'AvS_FastSimpleImport_Model_Import_Entity_Product_Type_Virtual');
    }

    /**
     * @test
     */
    public function testIfGroupedProductEntityIsLoadedProperly()
    {
        $this->assertModelAlias('fastsimpleimport/import_entity_product_type_Grouped', 'AvS_FastSimpleImport_Model_Import_Entity_Product_Type_Grouped');
    }

    /**
     * @test
     */
    public function testIfBundleProductEntityIsLoadedProperly()
    {
        $this->assertModelAlias('fastsimpleimport/import_entity_product_type_Bundle', 'AvS_FastSimpleImport_Model_Import_Entity_Product_Type_Bundle');
    }

}