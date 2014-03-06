<?php
/**
 * Website Source Model
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
class AvS_FastSimpleImport_Model_System_Config_Source_Product_Website
{
    public static function toOptionArray()
    {
        $options = array(array('value' => '', 'label' => ''));

        $websites = Mage::getModel('core/website')
            ->getCollection();

        foreach ($websites as $website)
        {
            $options[] = array(
                'value' => $website['website_id'],
                'label' => $website['name'],
            );
        }
        return $options;
    }
}
