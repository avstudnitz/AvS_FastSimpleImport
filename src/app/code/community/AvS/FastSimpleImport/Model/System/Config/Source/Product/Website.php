<?php

class AvS_FastSimpleImport_Model_System_Config_Source_Product_Website
{
    public static function toOptionArray()
    {
        $options = array(array('value' => '', 'label' => 'Please Select'));

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
