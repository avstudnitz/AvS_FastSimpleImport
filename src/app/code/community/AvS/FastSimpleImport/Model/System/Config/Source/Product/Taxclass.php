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
        $options = array(array('value' => '', 'label' => ''));

        $taxClasses = Mage::getModel('tax/class')
            ->getCollection()
            ->addFieldToFilter('class_type', Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT);

        foreach ($taxClasses as $taxClass)
        {
            $options[] = array(
                'value' => $taxClass['class_id'],
                'label' => $taxClass['class_name'],
            );
        }
        return $options;
    }
}
