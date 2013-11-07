<?php

class AvS_FastSimpleImport_Model_System_Config_Source_Product_Visibility
{
    public static function toOptionArray()
    {
        $options = array(array('value' => '', 'label' => 'Please Select'));
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
