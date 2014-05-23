<?php

/**
 * Grouped Products Import Model
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
/**
 * @property AvS_FastSimpleImport_Model_Import_Entity_Product $_entityModel
 */
class AvS_FastSimpleImport_Model_Import_Entity_Product_Type_Grouped
    extends Mage_ImportExport_Model_Import_Entity_Product_Type_Grouped
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
     * @return Mage_ImportExport_Model_Import_Entity_Product_Type_Abstract
     */
    public function saveData()
    {
        $groupedLinkId = Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED;
        $connection    = Mage::getSingleton('core/resource')->getConnection('write');
        $resource      = Mage::getResourceModel('catalog/product_link');
        $mainTable     = $resource->getMainTable();
        $relationTable = $resource->getTable('catalog/product_relation');
        $newSku        = $this->_entityModel->getNewSku();
        $oldSku        = $this->_entityModel->getOldSku();
        $attributes    = array();

        // pre-load attributes parameters
        $select = $connection->select()
            ->from($resource->getTable('catalog/product_link_attribute'), array(
                'id'   => 'product_link_attribute_id',
                'code' => 'product_link_attribute_code',
                'type' => 'data_type'
            ))->where('link_type_id = ?', $groupedLinkId);
        foreach ($connection->fetchAll($select) as $row) {
            $attributes[$row['code']] = array(
                'id' => $row['id'],
                'table' => $resource->getAttributeTypeTable($row['type'])
            );
        }
        while ($bunch = $this->_entityModel->getNextBunch()) {
            $linksData     = array(
                'product_ids'      => array(),
                'links'            => array(),
                'attr_product_ids' => array(),
                'position'         => array(),
                'qty'              => array(),
                'relation'         => array()
            );
            foreach ($bunch as $rowNum => $rowData) {
                $this->_entityModel->filterRowData($rowData);
                if (!$this->_entityModel->isRowAllowedToImport($rowData, $rowNum)
                    || empty($rowData['_associated_sku'])
                ) {
                    continue;
                }
                if (isset($newSku[$rowData['_associated_sku']])) {
                    $linkedProductId = $newSku[$rowData['_associated_sku']]['entity_id'];
                } elseif (isset($oldSku[$rowData['_associated_sku']])) {
                    $linkedProductId = $oldSku[$rowData['_associated_sku']]['entity_id'];
                } else {
                    continue;
                }
                $scope = $this->_entityModel->getRowScope($rowData);
                if (Mage_ImportExport_Model_Import_Entity_Product::SCOPE_DEFAULT == $scope) {
                    $productData = $newSku[$rowData[Mage_ImportExport_Model_Import_Entity_Product::COL_SKU]];
                } else {
                    $colAttrSet = Mage_ImportExport_Model_Import_Entity_Product::COL_ATTR_SET;
                    $rowData[$colAttrSet] = $productData['attr_set_code'];
                    $rowData[Mage_ImportExport_Model_Import_Entity_Product::COL_TYPE] = $productData['type_id'];
                }
                $productId = $productData['entity_id'];

                if ($this->_type != $rowData[Mage_ImportExport_Model_Import_Entity_Product::COL_TYPE]) {
                    continue;
                }
                $linksData['product_ids'][$productId] = true;
                $linksData['links'][$productId][$linkedProductId] = $groupedLinkId;
                $linksData['relation'][] = array('parent_id' => $productId, 'child_id' => $linkedProductId);
                $qty = empty($rowData['_associated_default_qty']) ? 0 : $rowData['_associated_default_qty'];
                $pos = empty($rowData['_associated_position']) ? 0 : $rowData['_associated_position'];

                if ($qty || $pos) {
                    $linksData['attr_product_ids'][$productId] = true;
                    if ($pos) {
                        $linksData['position']["{$productId} {$linkedProductId}"] = array(
                            'product_link_attribute_id' => $attributes['position']['id'],
                            'value' => $pos
                        );
                    }
                    if ($qty) {
                        $linksData['qty']["{$productId} {$linkedProductId}"] = array(
                            'product_link_attribute_id' => $attributes['qty']['id'],
                            'value' => $qty
                        );
                    }
                }
            }
            // save links and relations
            if ($linksData['product_ids'] && $this->getBehavior() != Mage_ImportExport_Model_Import::BEHAVIOR_APPEND) {
                $connection->delete(
                    $mainTable,
                    $connection->quoteInto(
                        'product_id IN (?) AND link_type_id = ' . $groupedLinkId,
                        array_keys($linksData['product_ids'])
                    )
                );
            }
            if ($linksData['links']) {
                $mainData = array();

                foreach ($linksData['links'] as $productId => $linkedData) {
                    foreach ($linkedData as $linkedId => $linkType) {
                        $mainData[] = array(
                            'product_id'        => $productId,
                            'linked_product_id' => $linkedId,
                            'link_type_id'      => $linkType
                        );
                    }
                }
                $connection->insertOnDuplicate($mainTable, $mainData);
                $connection->insertOnDuplicate($relationTable, $linksData['relation']);
            }
            // save positions and default quantity
            if ($linksData['attr_product_ids']) {
                $savedData = $connection->fetchPairs($connection->select()
                    ->from($mainTable, array(
                        new Zend_Db_Expr('CONCAT_WS(" ", product_id, linked_product_id)'), 'link_id'
                    ))
                    ->where(
                        'product_id IN (?) AND link_type_id = ' . $groupedLinkId,
                        array_keys($linksData['attr_product_ids'])
                    )
                );
                foreach ($savedData as $pseudoKey => $linkId) {
                    if (isset($linksData['position'][$pseudoKey])) {
                        $linksData['position'][$pseudoKey]['link_id'] = $linkId;
                    }
                    if (isset($linksData['qty'][$pseudoKey])) {
                        $linksData['qty'][$pseudoKey]['link_id'] = $linkId;
                    }
                }
                if ($linksData['position']) {
                    $connection->insertOnDuplicate($attributes['position']['table'], $linksData['position']);
                }
                if ($linksData['qty']) {
                    $connection->insertOnDuplicate($attributes['qty']['table'], $linksData['qty']);
                }
            }
        }
        return $this;
    }
}
