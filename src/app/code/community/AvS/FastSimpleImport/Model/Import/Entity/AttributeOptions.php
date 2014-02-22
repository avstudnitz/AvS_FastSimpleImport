<?php
/**
 * Created by PhpStorm.
 * User: tb
 * Date: 2/22/14
 * Time: 6:57 PM
 */
class AvS_FastSimpleImport_Model_Import_Entity_AttributeOptions
{
    const COL_VALUE  = 'value';
    const COL_ORDER  = 'order';
    const COL_DELETE = 'delete';


    public function createAttributeValue($attCode, $attValue) {
        $attribute_code=Mage::getModel('eav/entity_attribute')->getIdByCode('catalog_product', $attCode);
        $attributeInfo = Mage::getModel('eav/entity_attribute')->load($attribute_code);
        $attribute_table = Mage::getModel('eav/entity_attribute_source_table')->setAttribute($attributeInfo);
        $opt = $attValue;

        $option = array(self::COL_VALUE => array(), self::COL_ORDER => array(), self::COL_DELETE => array());
        if (is_array($opt)) {
            $i = 0;
            foreach ($opt as $o) {
                $option[self::COL_VALUE][0][$i] = $o;
                $i++;
            }
        } else {
            $option[self::COL_VALUE][0] = array(0 => $opt);
        }
        $attributeInfo->setOption($option);
        $attributeInfo->save();
    }

    public function deleteAttributeValue($attCode, $attValue) {
        $attribute_code=Mage::getModel('eav/entity_attribute')->getIdByCode('catalog_product', $attCode);
        $attributeInfo = Mage::getModel('eav/entity_attribute')->load($attribute_code);
        $attribute_table = Mage::getModel('eav/entity_attribute_source_table')->setAttribute($attributeInfo);
        $opt = $attribute_table->getAllOptions(false);
        $option = array(self::COL_VALUE => array(), self::COL_ORDER => array(), self::COL_DELETE => array());
        foreach ($opt as $o) {
            if ($o['label'] == $attValue) {
                $option[self::COL_DELETE][$o['value']] = true;
                $option[self::COL_VALUE][$o['value']] = true;
            }
        }
        $attributeInfo->setOption($option);
        $attributeInfo->save();
    }


}