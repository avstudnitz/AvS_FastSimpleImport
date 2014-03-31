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


    public function updateAttributeOptions($data)
    {
        $this->isAttributeCodeValid($data['attribute_code']);
        $id = Mage::getModel('eav/entity_attribute')->getIdByCode('catalog_product', $data['attribute_code']);
        $attr = Mage::getModel('eav/entity_attribute')->load($id);
//        var_dump(Mage::app()->getStore()->setId(0));
        $allOptions = $attr->setStoreId(0)->getSource()->getAllOptions(false);
        //Mage::app()->getWebsites());
        foreach (Mage::app()->getStores() as $store) {
            $stores[$store->getName()] = $store->getId();
        }

        foreach ($allOptions as $option) {
            if ($option['label'] == $data['admin']) {
                if (isset($data['view']) && is_array($data['view'])) {
                    if (isset($data['admin_new'])) {
                        $values[] = $data['admin_new'];
                    } else {
                        $values[] = $data['admin'];
                    }
                    foreach ($data['view'] as $viewKey => $view) {
                        $values[$stores[$viewKey]] = $view;
                    }

                }
                $updated = array(self::COL_ORDER => array($option['value'] => $data['order']),
                                 self::COL_VALUE => array($option['value'] => $values),
                );
            }
        }
        if (isset($data['label']));
        $labels = array();
        foreach ($attr->getStoreLabels() as $label) {
            foreach ($data['label'] as $labelKey => $viewLabel) {
                $labels[$stores[$labelKey]] = $viewLabel;
            }
        }
        $attr->setData('store_labels', $labels);


        $attr->setOption($updated);
        $attr->save();



    }


    /**
     * createOrUpdateAttributeValue
     *
     * @param array|string $attValue
     * @param string $attrCode
     *
     */

    public function createOrUpdateAttributeValue($attrCode, $attValue) {
        $this->isAttributeCodeValid($attrCode);
        $attribute_code=Mage::getModel('eav/entity_attribute')->getIdByCode('catalog_product', $attrCode);
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
                    return true;
                }
            }
        } else {
            foreach ($aopt as $ao) {
                if ($ao['label'] == $attValue) {
                    $i = 0;
                    $option[self::COL_VALUE][$ao['value']][0] = $attValue;
                    $attributeInfo->setOption($option);
                    $attributeInfo->save();
                    return true;
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
                return true;
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

    /**
     * deleteAttributeValue
     *
     * @param string $attValue
     * @param string $attrCode
     *
     */

    public function deleteAttributeValue($attrCode, $attValue) {
        $this->isAttributeCodeValid($attrCode);
        $attribute_code=Mage::getModel('eav/entity_attribute')->getIdByCode('catalog_product', $attrCode);
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
        return true;
    }

    /**
     * Check one attributecode. Can be overridden in child.
     *
     * @param string $attrCode Attribute code
     * @return boolean
     */

    public function isAttributeCodeValid($attrCode) {
        $valid = true;
        $attribute_code=Mage::getModel('eav/entity_attribute')->getIdByCode('catalog_product', $attrCode);
        $attributeInfo = Mage::getModel('eav/entity_attribute')->load($attribute_code);
        if ($attributeInfo->getFrontendInput() != 'multiselect' && $attributeInfo->getFrontendInput() != 'select') {
            $valid = false;
            $message = "Attribute: '".$attrCode."'. Not multiselect nor select \n'";
        }
        if (!$valid) {
            Mage::throwException($message);
        }
        return (bool) $valid;
    }


}