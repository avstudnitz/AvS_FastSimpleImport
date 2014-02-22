<?php
/**
 * Created by IntelliJ IDEA.
 * Date: 22.02.14
 * Time: 11:30
 */

class AvS_FastSimpleImport_Test_Config_DefaultValueTest extends EcomDev_PHPUnit_Test_Case_Config
{

    public function testIfDefaultValuesAreProperlyDefined()
    {
        $this->assertDefaultConfigValue('fastsimpleimport/product/status', 1);
        $this->assertDefaultConfigValue('fastsimpleimport/product/visibility', 4);
        $this->assertDefaultConfigValue('fastsimpleimport/product/weight', 0);

        $this->assertDefaultConfigValue('fastsimpleimport/general/import_behavior', 'replace');
        $this->assertDefaultConfigValue('fastsimpleimport/general/partial_indexing', 0);
        $this->assertDefaultConfigValue('fastsimpleimport/general/continue_after_errors', 0);
        $this->assertDefaultConfigValue('fastsimpleimport/general/error_limit', 100);
        $this->assertDefaultConfigValue('fastsimpleimport/general/support_nested_arrays', 0);
    }
}