<?php
/**
 * Tax Class Source Model
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
class AvS_FastSimpleImport_Model_System_Config_Source_Product_Taxclass
{
    public static function toOptionArray()
    {
        $options = array(array('value' => '', 'label' => Mage::helper('fastsimpleimport')->__('Please Select')));

        $tax_classes = Mage::getModel('tax/class')
            ->getCollection();

        foreach ($tax_classes as $tax_class)
        {
            $options[] = array(
                'value' => $tax_class['class_id'],
                'label' => $tax_class['class_name'],
            );
        }
        return $options;
    }
}
