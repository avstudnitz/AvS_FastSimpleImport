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


    public function createOrUpdateAttributeValue($attCode, $attValue) {
        $attribute_code=Mage::getModel('eav/entity_attribute')->getIdByCode('catalog_product', $attCode);
        $attributeInfo = Mage::getModel('eav/entity_attribute')->load($attribute_code);
        $attribute_table = Mage::getModel('eav/entity_attribute_source_table')->setAttribute($attributeInfo);
        $aopt = $attribute_table->getAllOptions(false);
        $opt = $attValue;
        $option = array(self::COL_VALUE => array(), self::COL_ORDER => array(), self::COL_DELETE => array());
        if (is_array($attValue)) {
            foreach ($aopt as $ao) {
                if ($ao['label'] == $attValue[0]) {
                    $i = 0;
                    foreach ($attValue as $aV) {
                        $option[self::COL_VALUE][$ao['value']][$i] = $aV;
                        $i++;
                    }
                    $attributeInfo->setOption($option);
                    $attributeInfo->save();
                    return;
                }
            }
        } else {
            foreach ($aopt as $ao) {
                if ($ao['label'] == $attValue) {
                    $i = 0;
                    $option[self::COL_VALUE][$ao['value']][0] = $attValue;
                    $attributeInfo->setOption($option);
                    $attributeInfo->save();
                    return;
                }
            }
        }


        foreach ($aopt as $ao) {
            if ($ao['label'] == $attValue[0]) {
                $option[self::COL_VALUE][$ao['value']][0] = $attValue[0];
                $option[self::COL_VALUE][$ao['value']][1] = $attValue[1];
                print_r($option);
                $attributeInfo->setOption($option);
                $attributeInfo->save();
                return;
            }
        }
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