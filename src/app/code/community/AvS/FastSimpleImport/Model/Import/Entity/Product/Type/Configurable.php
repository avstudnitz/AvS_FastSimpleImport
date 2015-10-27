<?php

/**
 * Configurable Products Import Model
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
/**
 * @property AvS_FastSimpleImport_Model_Import_Entity_Product $_entityModel
 */
class AvS_FastSimpleImport_Model_Import_Entity_Product_Type_Configurable
    extends Mage_ImportExport_Model_Import_Entity_Product_Type_Configurable
{
    /**
     * Prepare attributes values for save: remove non-existent, remove empty values, remove static.
     *
     * @param array $rowData
     * @param bool $withDefaultValue
     * @return array
     */
    public function prepareAttributesForSave(array $rowData, $withDefaultValue = true)
    {
        $resultAttrs = array();

        foreach ($this->_getProductAttributes($rowData) as $attrCode => $attrParams) {
            if (!$attrParams['is_static']) {
                if (isset($rowData[$attrCode]) && strlen($rowData[$attrCode])) {
                    $resultAttrs[$attrCode] =
                        ('select' == $attrParams['type'] || 'multiselect' == $attrParams['type'])
                            ? $attrParams['options'][strtolower($rowData[$attrCode])]
                            : $rowData[$attrCode];
                } elseif (array_key_exists($attrCode, $rowData)) {
                    $resultAttrs[$attrCode] = $rowData[$attrCode];
                } elseif ($this->_isSkuNew($rowData['sku'])) {
                    $defaultValue = $this->_getDefaultValue($attrParams);
                    if (null !== $defaultValue) {
                        $resultAttrs[$attrCode] = $defaultValue;
                    }
                }
            }
        }
        return $resultAttrs;
    }

    /**
     * Validate row attributes. Pass VALID row data ONLY as argument.
     *
     * @param array $rowData
     * @param int $rowNum
     * @param boolean $checkRequiredAttributes OPTIONAL Flag which can disable validation required values.
     * @return boolean
     */
    public function isRowValid(array $rowData, $rowNum, $checkRequiredAttributes = true)
    {
        $error    = false;
        $rowScope = $this->_entityModel->getRowScope($rowData);

        if (Mage_ImportExport_Model_Import_Entity_Product::SCOPE_NULL != $rowScope) {
            foreach ($this->_getProductAttributes($rowData) as $attrCode => $attrParams) {
                // check value for non-empty in the case of required attribute?
                if (isset($rowData[$attrCode]) && strlen($rowData[$attrCode])) {
                    $error |= !$this->_entityModel->isAttributeValid($attrCode, $attrParams, $rowData, $rowNum);
                } elseif (
                    $this->_isAttributeRequiredCheckNeeded($attrCode)
                    && $checkRequiredAttributes
                    && Mage_ImportExport_Model_Import_Entity_Product::SCOPE_DEFAULT == $rowScope
                    && $attrParams['is_required']
                    && is_null($this->_getDefaultValue($attrParams))
                ) {
                    $this->_entityModel->addRowError(
                        Mage_ImportExport_Model_Import_Entity_Product::ERROR_VALUE_IS_REQUIRED, $rowNum, $attrCode
                    );
                    $error = true;
                }
            }
        }
        $error |= !$this->_isParticularAttributesValid($rowData, $rowNum);

        return !$error;
    }

    /**
     * Get configured default value for attribute
     *
     * @param array $attrParams
     * @return mixed|null
     */
    protected function _getDefaultValue($attrParams)
    {
        switch ($attrParams['code']) {

            case 'tax_class_id':
            case 'status':
            case 'visibility':
            case 'weight':
                $defaultValue = Mage::getStoreConfig('fastsimpleimport/product/' . $attrParams['code']);
                if (strlen($defaultValue)) {
                    return $defaultValue;
                }
                break;
        }

        if (null !== $attrParams['default_value']) {
            return $attrParams['default_value'];
        }

        return null;
    }

    /**
     * Check if the given sku belongs to a new product or an existing one
     *
     * @param $sku
     * @return bool
     */
    protected function _isSkuNew($sku)
    {
        if ($sku == '') {
            return false;
        }
        $oldSkus = $this->_entityModel->getOldSku();
        return !isset($oldSkus[$sku]);
    }


    /**
     * Save product type specific data.
     *
     * @throws Exception
     * @return Mage_ImportExport_Model_Import_Entity_Product_Type_Abstract
     */
    public function saveData()
    {
        $connection      = $this->_entityModel->getConnection();
        $mainTable       = Mage::getSingleton('core/resource')->getTableName('catalog/product_super_attribute');
        $labelTable      = Mage::getSingleton('core/resource')->getTableName('catalog/product_super_attribute_label');
        $priceTable      = Mage::getSingleton('core/resource')->getTableName('catalog/product_super_attribute_pricing');
        $linkTable       = Mage::getSingleton('core/resource')->getTableName('catalog/product_super_link');
        $relationTable   = Mage::getSingleton('core/resource')->getTableName('catalog/product_relation');
        $newSku          = $this->_entityModel->getNewSku();
        $oldSku          = $this->_entityModel->getOldSku();
        $productSuperData = array();
        $productData     = null;
        $nextAttrId      = Mage::getResourceHelper('importexport')->getNextAutoincrement($mainTable);

        if ($this->_entityModel->getBehavior() == Mage_ImportExport_Model_Import::BEHAVIOR_APPEND) {
            $this->_loadSkuSuperData();
        }
        $this->_loadSkuSuperAttributeValues();

        while ($bunch = $this->_entityModel->getNextBunch()) {
            $superAttributes = array(
                'attributes' => array(),
                'labels'     => array(),
                'pricing'    => array(),
                'super_link' => array(),
                'relation'   => array()
            );
            $superAttributePosition = 0;
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->_entityModel->isRowAllowedToImport($rowData, $rowNum)) {
                    continue;
                }
                $this->_entityModel->filterRowData($rowData);
                // remember SCOPE_DEFAULT row data
                $scope = $this->_entityModel->getRowScope($rowData);
                if (Mage_ImportExport_Model_Import_Entity_Product::SCOPE_DEFAULT == $scope) {
                    $productData = $newSku[$rowData[Mage_ImportExport_Model_Import_Entity_Product::COL_SKU]];

                    if ($this->_type != $productData['type_id']) {
                        $productData = null;
                        continue;
                    }
                    $productId = $productData['entity_id'];

                    $this->_processSuperData($productSuperData, $superAttributes);

                    $productSuperData = array(
                        'product_id'      => $productId,
                        'attr_set_code'   => $productData['attr_set_code'],
                        'used_attributes' => empty($this->_skuSuperData[$productId])
                                             ? array() : $this->_skuSuperData[$productId],
                        'assoc_ids'       => array()
                    );
                } elseif (null === $productData) {
                    continue;
                }
                if (!empty($rowData['_super_products_sku'])) {
                    if (isset($newSku[$rowData['_super_products_sku']])) {
                        $productSuperData['assoc_ids'][$newSku[$rowData['_super_products_sku']]['entity_id']] = true;
                    } elseif (isset($oldSku[$rowData['_super_products_sku']])) {
                        $productSuperData['assoc_ids'][$oldSku[$rowData['_super_products_sku']]['entity_id']] = true;
                    }
                }
                if (empty($rowData['_super_attribute_code'])) {
                    continue;
                }
                $attrParams = $this->_superAttributes[$rowData['_super_attribute_code']];

                if ($this->_getSuperAttributeId($productId, $attrParams['id'])) {
                    $productSuperAttrId = $this->_getSuperAttributeId($productId, $attrParams['id']);
                } elseif (!isset($superAttributes['attributes'][$productId][$attrParams['id']])) {
                    $productSuperAttrId = $nextAttrId++;

                    if(($rowData['sku'] !== NULL) && ($rowData['_type'] == 'configurable')) {
                        /*
                            Positioning of super attribute will be reset if a new configurable product is detected
                        */
                        $superAttributePosition = 0;
                    }

                    $superAttributes['attributes'][$productId][$attrParams['id']] = array(
                        'product_super_attribute_id' => $productSuperAttrId, 'position' => $superAttributePosition++
                    );
                    $superAttributes['labels'][] = array(
                        'product_super_attribute_id' => $productSuperAttrId,
                        'store_id'    => 0,
                        'use_default' => 1,
                        'value'       => $attrParams['frontend_label']
                    );
                }
                if (isset($rowData['_super_attribute_option']) && strlen($rowData['_super_attribute_option'])) {
                    $optionId = $attrParams['options'][strtolower($rowData['_super_attribute_option'])];

                    if (!isset($productSuperData['used_attributes'][$attrParams['id']][$optionId])) {
                        $productSuperData['used_attributes'][$attrParams['id']][$optionId] = false;
                    }
                    if (!empty($rowData['_super_attribute_price_corr'])) {
                        $superAttributes['pricing'][] = array(
                            'product_super_attribute_id' => $productSuperAttrId,
                            'value_index'   => $optionId,
                            'is_percent'    => '%' == substr($rowData['_super_attribute_price_corr'], -1),
                            'pricing_value' => (float) rtrim($rowData['_super_attribute_price_corr'], '%'),
                            'website_id'    => 0
                        );
                    }
                }
            }
            // save last product super data
            $this->_processSuperData($productSuperData, $superAttributes);

            // remove old data if needed
            if ($this->_entityModel->getBehavior() != Mage_ImportExport_Model_Import::BEHAVIOR_APPEND
                && $superAttributes['attributes']) {
                $quoted = $connection->quoteInto('IN (?)', array_keys($superAttributes['attributes']));
                $connection->delete($mainTable, "product_id {$quoted}");
                $connection->delete($linkTable, "parent_id {$quoted}");
                $connection->delete($relationTable, "parent_id {$quoted}");
            }
            $mainData = array();

            foreach ($superAttributes['attributes'] as $productId => $attributesData) {
                foreach ($attributesData as $attrId => $row) {
                    $row['product_id']   = $productId;
                    $row['attribute_id'] = $attrId;
                    $mainData[]          = $row;
                }
            }
            if ($mainData) {
                $connection->insertOnDuplicate($mainTable, $mainData);
            }
            if ($superAttributes['labels']) {
                $connection->insertOnDuplicate($labelTable, $superAttributes['labels']);
            }
            if ($superAttributes['pricing']) {
                $connection->insertOnDuplicate(
                    $priceTable,
                    $superAttributes['pricing'],
                    array('is_percent', 'pricing_value')
                );
            }
            if ($superAttributes['super_link']) {
                $connection->insertOnDuplicate($linkTable, $superAttributes['super_link']);
            }
            if ($superAttributes['relation']) {
                $connection->insertOnDuplicate($relationTable, $superAttributes['relation']);
            }
        }
        return $this;
    }
}
