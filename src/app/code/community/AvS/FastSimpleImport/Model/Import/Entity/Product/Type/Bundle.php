<?php

/*
 * Copyright 2011 Daniel Sloof
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>

*/

/**
 * @property AvS_FastSimpleImport_Model_Import_Entity_Product $_entityModel
 */
class AvS_FastSimpleImport_Model_Import_Entity_Product_Type_Bundle
    extends Mage_ImportExport_Model_Import_Entity_Product_Type_Abstract
{

    const DEFAULT_OPTION_TYPE = 'select';
    const ERROR_INVALID_BUNDLE_PRODUCT_SKU = 'invalidBundleProductSku';

    protected $_particularAttributes = array(
        '_bundle_option_required',
        '_bundle_option_position',
        '_bundle_option_type',
        '_bundle_option_title',
        '_bundle_option_store',
        '_bundle_product_sku',
        '_bundle_product_position',
        '_bundle_product_is_default',
        '_bundle_product_price_type',
        '_bundle_product_price_value',
        '_bundle_product_qty',
        '_bundle_product_can_change_qty'
    );

    protected $_bundleOptionTypes = array(
        'select',
        'radio',
        'checkbox',
        'multi'
    );

    public function _initAttributes()
    {
        parent::_initAttributes();

        /*
         * Price type does not live in an attribute set, so it is not picked up
         * by abstract _initAttributes method. We add it here manually.
         */
        $attribute = Mage::getResourceModel('catalog/eav_attribute')->load('price_type', 'attribute_code');
        foreach ($this->_attributes as $attrSetName => $attributes) {
            $this->_addAttributeParams(
                $attrSetName,
                array(
                    'id'               => $attribute->getId(),
                    'code'             => $attribute->getAttributeCode(),
                    'for_configurable' => $attribute->getIsConfigurable(),
                    'is_global'        => $attribute->getIsGlobal(),
                    'is_required'      => $attribute->getIsRequired(),
                    'is_unique'        => $attribute->getIsUnique(),
                    'frontend_label'   => $attribute->getFrontendLabel(),
                    'is_static'        => $attribute->isStatic(),
                    'apply_to'         => $attribute->getApplyTo(),
                    'type'             => Mage_ImportExport_Model_Import::getAttributeType($attribute),
                    'default_value'    => strlen($attribute->getDefaultValue()) ? $attribute->getDefaultValue() : null,
                    'options'          => $this->_entityModel->getAttributeOptions($attribute, $this->_indexValueAttributes)
                )
            );
        }

        return $this;
    }

    public function saveData()
    {
        if(!$this->isSuitable())
        {
            return $this;
        }
        $connection       = $this->_entityModel->getConnection();
        $newSku           = $this->_entityModel->getNewSku();
        $oldSku           = $this->_entityModel->getOldSku();
        $optionTable      = Mage::getSingleton('core/resource')->getTableName('bundle/option');
        $optionValueTable = Mage::getSingleton('core/resource')->getTableName('bundle/option_value');
        $selectionTable   = Mage::getSingleton('core/resource')->getTableName('bundle/selection');
        $relationTable    = Mage::getSingleton('core/resource')->getTableName('catalog/product_relation');
        $productData      = null;
        $productId        = null;

        while ($bunch = $this->_entityModel->getNextBunch()) {
            $bundleOptions    = array();
            $bundleSelections = array();

            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->_entityModel->isRowAllowedToImport($rowData, $rowNum)) {
                    continue;
                }
                $this->_entityModel->filterRowData($rowData);
                $scope = $this->_entityModel->getRowScope($rowData);
                if (Mage_ImportExport_Model_Import_Entity_Product::SCOPE_DEFAULT == $scope) {
                    $productData = $newSku[$rowData[Mage_ImportExport_Model_Import_Entity_Product::COL_SKU]];

                    if ($this->_type != $productData['type_id']) {
                        $productData = null;
                        continue;
                    }
                    $productId = $productData['entity_id'];
                } elseif (null === $productData) {
                    continue;
                }

                if (empty($rowData['_bundle_option_title'])) {
                    continue;
                }
                if (isset($rowData['_bundle_option_type']) && !empty($rowData['_bundle_option_type'])) {
                    if (!in_array($rowData['_bundle_option_type'], $this->_bundleOptionTypes)) {
                        continue;
                    }

                    $bundleOptions[$productId][$rowData['_bundle_option_title']] = array(
                        'parent_id' => $productId,
                        'required'  => !empty($rowData['_bundle_option_required']) ? $rowData['_bundle_option_required'] : '0',
                        'position'  => !empty($rowData['_bundle_option_position']) ? $rowData['_bundle_option_position'] : '0',
                        'type'      => !empty($rowData['_bundle_option_type'])     ? $rowData['_bundle_option_type']     : self::DEFAULT_OPTION_TYPE
                    );
                }
                if (isset($rowData['_bundle_product_sku']) && !empty($rowData['_bundle_product_sku'])) {
                    $selectionEntityId = false;
                    if (isset($newSku[$rowData['_bundle_product_sku']])) {
                        $selectionEntityId = $newSku[$rowData['_bundle_product_sku']]['entity_id'];
                    } elseif (isset($oldSku[$rowData['_bundle_product_sku']])) {
                        $selectionEntityId = $oldSku[$rowData['_bundle_product_sku']]['entity_id'];
                    } else {
                        /*
                         * TODO: We should move this to _isParticularAttributeValid, but
                         * entity model is not filled with newSku / oldSku there.
                         */
                        $this->_entityModel->addRowError(self::ERROR_INVALID_BUNDLE_PRODUCT_SKU, $rowNum);
                    }

                    if ($selectionEntityId) {
                        $bundleSelections[$productId][$rowData['_bundle_option_title']][] = array(
                            'parent_product_id'         => $productId,
                            'product_id'                => $selectionEntityId,
                            'position'                  => !empty($rowData['_bundle_product_position'])       ? $rowData['_bundle_product_position']        : '0',
                            'is_default'                => !empty($rowData['_bundle_product_is_default'])     ? $rowData['_bundle_product_is_default']      : '0',
                            'selection_price_type'      => !empty($rowData['_bundle_product_price_type'])     ? $rowData['_bundle_product_price_type']      : '0',
                            'selection_price_value'     => !empty($rowData['_bundle_product_price_value'])    ? $rowData['_bundle_product_price_value']     : '0',
                            'selection_qty'             => !empty($rowData['_bundle_product_qty'])            ? $rowData['_bundle_product_qty']             : '1',
                            'selection_can_change_qty'  => !empty($rowData['_bundle_product_can_change_qty']) ? $rowData['_bundle_product_can_change_qty']  : '0'
                        );
                    }
                }
            }

            if (count($bundleOptions)) {
//                if ($this->_entityModel->getBehavior() != Mage_ImportExport_Model_Import::BEHAVIOR_APPEND) {
                    $quoted = $connection->quoteInto('IN (?)', array_keys($bundleOptions));
                    $connection->delete($optionTable, "parent_id {$quoted}");
                    $connection->delete($selectionTable, "parent_product_id {$quoted}");
                    $connection->delete($relationTable, "parent_id {$quoted}");
//                }

                /*
                 * Insert options.
                 */
                $optionData = array();
                foreach ($bundleOptions as $productId => $options) {
                    foreach ($options as $title => $option) {
                        $optionData[] = $option;
                    }
                }
                $connection->insertOnDuplicate($optionTable, $optionData);

                /*
                 * Insert option titles.
                 */
                $optionId = $connection->lastInsertId();
                $optionValues = array();
                foreach ($bundleOptions as $productId => $options) {
                    foreach ($options as $title => $option) {
                        $optionValues[] = array(
                            'option_id' => $optionId++,
                            'store_id'  => '0',
                            'title'     => $title
                        );
                    }
                }
                $connection->insertOnDuplicate($optionValueTable, $optionValues);
                $optionId -= count($optionData);

                if (count($bundleSelections)) {
                    $optionSelections = array();
                    $productRelations = array();

                    foreach ($bundleSelections as $productId => $selections) {
                        foreach ($selections as $title => $selection) {
                            foreach ($selection as &$sel) {
                                $productRelations[] = array(
                                    'parent_id' => $sel['parent_product_id'],
                                    'child_id'  => $sel['product_id']
                                );
                                $sel['option_id'] = $optionId;
                            }
                            $optionId++;
                            $optionSelections = array_merge($optionSelections, $selection);
                        }
                    }

                    /*
                     * Insert option selections.
                     */
                    $connection->insertOnDuplicate($selectionTable, $optionSelections);

                    /*
                     * Insert product relations.
                     */
                    $connection->insertOnDuplicate($relationTable, $productRelations);
                }
            }
        }

        return $this;
    }

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
     * check if Mage_Bundle module is enabled
     * 
     * @return boolean
     */
    public function isSuitable()
    {
        return Mage::getConfig()->getModuleConfig('Mage_Bundle')->is('active', 'true');
    }
}
