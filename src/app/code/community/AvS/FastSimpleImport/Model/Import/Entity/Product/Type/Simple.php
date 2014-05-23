<?php

/**
 * Simple (and Virtual) Products Import Model
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
/**
 * @property AvS_FastSimpleImport_Model_Import_Entity_Product $_entityModel
 */
class AvS_FastSimpleImport_Model_Import_Entity_Product_Type_Simple
    extends Mage_ImportExport_Model_Import_Entity_Product_Type_Simple
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
}
