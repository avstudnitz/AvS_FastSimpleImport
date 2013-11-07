<?php
/**
 * Visibility Source Model
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
class AvS_FastSimpleImport_Model_System_Config_Source_Product_Visibility
{
    public static function toOptionArray()
    {
        $options = array(array('value' => '', 'label' => ''));
        foreach (Mage_Catalog_Model_Product_Visibility::getOptionArray() as $value => $label)
        {
            $options[] = array(
                'value' => $value,
                'label' => $label,
            );
        }
        return $options;
    }
}
