<?php

class AvS_FastSimpleImport_Model_System_Config_Source_Product_Taxclass
{
    public static function toOptionArray()
    {
        $options = array(array('value' => '', 'label' => 'Please Select'));

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
