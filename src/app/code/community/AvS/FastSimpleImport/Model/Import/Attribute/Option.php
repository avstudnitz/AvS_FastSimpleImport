<?php
/**
 * Created by PhpStorm.
 * User: tb
 * Date: 2/22/14
 * Time: 6:57 PM
*/
class AvS_FastSimpleImport_Model_Import_Attribute_Option
{
    const COL_VALUE  = 'value';
    const COL_ORDER  = 'order';

    const AO_CODE = 'attribute_code';
    const AO_ADMIN = 'admin';

    const ENTITY_TYPE_CODE_FAKE = 'attribute_option';
    protected $_dataValidated = false;
    /**
     * Error counter.
     *
     * @var int
     */
    protected $_errorsCount = 0;
    /**
     * Number of Attribute Options processed by validation
     *
     * @var int
     */
    protected $_processedOptionsCount = 0;
    /**
     * Number of rows processed by validation.
     *
     * @var int
     */
    protected $_processedRowsCount = 0;
    /**
     * Limit of errors after which pre-processing will exit.
     *
     * @var int
     */
    protected $_errorsLimit = 100;
     /**
     * Permanent entity columns.
     *
     * @var array
     */
    protected $_permanentAttributes = array(
        self::AO_CODE, self::AO_ADMIN
    );

    protected $_availableAttributes = array();
    protected $_availableAttributeOptions = array();
    protected $_availableAttributeOptionValues = array();
    protected $_optionTable = '';
    protected $_optionValueTable = '';
    /**
     * Array of invalid rows numbers.
     *
     * @var array
     */
    protected $_invalidRows = array();
        /**
     * Array of numbers of validated rows as keys and boolean TRUE as values.
     *
     * @var array
     */
    protected $_validatedRows = array();


    /**
     * Error Codes
     */
    const ERROR_VALUE_IS_REQUIRED = 'valueIsRequired';
    const ERROR_ADMIN_LABEL_IS_REQUIRED = 'adminLabelIsRequired';
    const ERROR_ATTRIBUTE_IS_NOT_VALID = 'attributeIsNotValid';

    protected $_messageTemplate = array(
        self::ERROR_VALUE_IS_REQUIRED => 'Required entry \'%s\' has empty value.',
        self::ERROR_ADMIN_LABEL_IS_REQUIRED => 'Admin store view label is required for options.',
        self::ERROR_ATTRIBUTE_IS_NOT_VALID => 'Attribute \'%s\' is not valid.'
    );

    protected $_processedAttributes = array();
     /**
     * Size of bunch - part of entities to save in one step.
     */
    const BUNCH_SIZE = 20;
    public function __construct()
    {
        $this->_dataSourceModel = Mage_ImportExport_Model_Import::getDataSourceModel();
        $this->_connection      = Mage::getSingleton('core/resource')->getConnection('write');
        $this->_optionTable = Mage::getSingleton('core/resource')->getTableName('eav/attribute_option');
        $this->_optionValueTable = Mage::getSingleton('core/resource')->getTableName('eav/attribute_option_value');
    }

    /**
     * Add error with corresponding current data source row number.
     *
     * @param string $errorCode Error code or simply column name
     * @param int $errorRowNum Row number.
     * @param string $colName OPTIONAL Column name.
     * @return Mage_ImportExport_Model_Import_Adapter_Abstract
     */
    public function addRowError($errorCode, $errorRowNum, $colName = null)
    {
        $this->_errors[$errorCode][] = array($errorRowNum + 1, $colName); // one added for human readability
        $this->_invalidRows[$errorRowNum] = true;
        $this->_errorsCount ++;

        return $this;
    }
    /**
     * Import behavior setter
     *
     * @param string $behavior
     */
    public function setBehavior($behavior)
    {
        $this->_parameters['behavior'] = $behavior;
    }

     /**
     * Source model setter.
     *
     * @param array $source
     * @return Mage_ImportExport_Model_Import_Entity_Abstract
     */
    public function setArraySource($source)
    {
        $this->_source = $source;
        $this->_dataValidated = false;

        return $this;
    }
    /**
     * Is all of data valid?
     *
     * @return bool
     */
    public function isDataValid()
    {
        $this->validateData();
        return 0 == $this->_errorsCount;
    }

    public function getErrorsCount()
    {
        return $this->_errorsCount;
    }

    public function getErrorsLimit()
    {
        return $this->_errorsLimit;
    }
    /**
     * Returns error information grouped by error types and translated (if possible).
     *
     * @return array
     */
    public function getErrorMessages()
    {
        $translator = Mage::helper('importexport');
        $messages   = array();

        foreach ($this->_errors as $errorCode => $errorRows) {
            if (isset($this->_messageTemplates[$errorCode])) {
                $errorCode = $translator->__($this->_messageTemplates[$errorCode]);
            }
            foreach ($errorRows as $errorRowData) {
                $key = $errorRowData[1] ? sprintf($errorCode, $errorRowData[1]) : $errorCode;
                $messages[$key][] = $errorRowData[0];
            }
        }
        return $messages;
    }
    /**
     * Source model setter.
     *
     * @param Mage_ImportExport_Model_Import_Adapter_Abstract $source
     * @return Mage_ImportExport_Model_Import_Entity_Abstract
     */
    public function setSource(Mage_ImportExport_Model_Import_Adapter_Abstract $source)
    {
        $this->_source = $source;
        $this->_dataValidated = false;

        return $this;
    }

        /**
     * Inner source object getter.
     *
     * @return Mage_ImportExport_Model_Import_Adapter_Abstract
     */
    protected function _getSource()
    {
        if (!$this->_source) {
            Mage::throwException(Mage::helper('importexport')->__('No source specified'));
        }
        return $this->_source;
    }
    public function getProcessedAttributesCount()
    {
        return count($this->_processedAttributes);
    }
    /**
     * Import source file structure to DB.
     *
     * @return bool
     */
    public function importSource()
    {
        Mage::log(Mage::helper('importexport')->__('Begin import of "attribute_options" with "%s" behavior', $this->getBehavior()));
        $result = $this->_importData();
        Mage::log(array(
            Mage::helper('importexport')->__('Checked rows: %d, checked entities: %d, invalid rows: %d, total errors: %d',
                $this->getProcessedRowsCount(), $this->getProcessedAttributesCount(),
                $this->getInvalidRowsCount(), $this->getErrorsCount()
            ),
            Mage::helper('importexport')->__('Import has been done successfuly.')
        ));
        return $result;
    }

        /**
     * Get single error message as string
     *
     * @return string
     */
    public function getErrorMessage()
    {
        $message = sprintf("Input Data contains %s corrupt records (from a total of %s)",
            $this->getInvalidRowsCount(), $this->getProcessedRowsCount()
        );
        foreach ($this->getErrorMessages() as $type => $lines) {
            $message .= "\n:::: " . $type . " ::::\nIn Line(s) " . implode(", ", $lines) . "\n";
        }
        return $message;
    }
    /**
     * Validate data.
     *
     * @throws Exception
     * @return Mage_ImportExport_Model_Import_Entity_Abstract
     */
    public function validateData()
    {
        if (!$this->_dataValidated) {
            // does all permanent columns exists?
            if (($colsAbsent = array_diff($this->_permanentAttributes, $this->_getSource()->getColNames()))) {
                Mage::throwException(
                    Mage::helper('importexport')->__('Can not find required columns: %s', implode(', ', $colsAbsent))
                );
            }

            // initialize validation related attributes
            $this->_errors = array();
            $this->_invalidRows = array();

            // check attribute columns names validity
            $invalidColumns = array();

            foreach ($this->_getSource()->getColNames() as $colName) {
                if (!preg_match('/^[a-z][a-z0-9_]*$/', $colName) && !$this->isAttributeParticular($colName)) {
                    $invalidColumns[] = $colName;
                }
            }
            if ($invalidColumns) {
                Mage::throwException(
                    Mage::helper('importexport')->__('Column names: "%s" are invalid', implode('", "', $invalidColumns))
                );
            }
            $this->_saveValidatedBunches();

            $this->_dataValidated = true;
        }
        return $this;
    }

    protected function _getAvailableAttributes()
    {
        if (!isset($this->_availableAttributes) || empty($this->_availableAttributes)) {
            foreach (Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getAttributeCollection() as $attribute) {
                $this->_availableAttributes[] = $attribute->getAttributeCode();
            }
        }
        return $this->_availableAttributes;
    }


    protected function _getAvailableAttributeOptions($attributeCode)

    {
        if (!isset($this->_availableAttributeOptions[$attributeCode]) || empty($this->_availableAttributeOptions[$attributeCode])) {
                $result = $this->_connection
                ->select()
                ->from(array('ao' => Mage::getSingleton('core/resource')->getTableName('eav/attribute_option')), array('option_id', 'sort_order'))
                ->join(array('ea' => Mage::getSingleton('core/resource')->getTableName('eav/attribute')), 'ao.attribute_id = ea.attribute_id', '')
                ->join(array('aov' => Mage::getSingleton('core/resource')->getTableName('eav/attribute_option_value')), 'ao.option_id = aov.option_id', array('value', 'value_id'))
                ->where('ea.attribute_code = ?', $attributeCode)
                ->query()
                ->fetchAll();
            foreach ($result as $attributeOption) {
                $this->_availableAttributeOptions[$attributeCode][$attributeOption['value']] = $attributeOption;
            }
        }
        return $this->_availableAttributeOptions[$attributeCode];
    }
        /**
     * Change row data before saving in DB table.
     *
     * @param array $rowData
     * @return array
     */
    protected function _prePrepareRowForDb(array $rowData)
    {
        /**
         * Convert all empty strings to null values, as
         * a) we don't use empty string in DB
         * b) empty strings instead of numeric values will product errors in Sql Server
         */
        foreach ($rowData as $key => $val) {
            if ($val === '') {
                $rowData[$key] = null;
            }
        }
        return $rowData;
    }
    /**
     * Set valid attribute set and category type to rows with all scopes
     * to ensure that existing Categories doesn't changed.
     *
     * @param array $rowData
     * @return array
     */
    protected function _prepareRowForDb(array $rowData)
    {
//        $rowData = parent::_prepareRowForDb($rowData);
        $rowData = $this->_prePrepareRowForDb($rowData);
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            return $rowData;
        }
            if (!isset($rowData['order'])) $rowData['order'] = 10000; // diglin - prevent warning message

        return $rowData;
    }
    /**
     * Validate data rows and save bunches to DB.
     *
     * @return Mage_ImportExport_Model_Import_Entity_Abstract
     */
    protected function _saveValidatedBunches()
    {
        $source          = $this->_getSource();
        $productDataSize = 0;
        $bunchRows       = array();
        $startNewBunch   = false;
        $nextRowBackup   = array();
        $maxDataSize = Mage::getResourceHelper('importexport')->getMaxDataSize();
        $bunchSize = Mage::helper('importexport')->getBunchSize();

        $source->rewind();
        $this->_dataSourceModel->cleanBunches();

        while ($source->valid() || $bunchRows) {
            if ($startNewBunch || !$source->valid()) {
                $this->_dataSourceModel->saveBunch(self::ENTITY_TYPE_CODE_FAKE, $this->getBehavior(), $bunchRows);

                $bunchRows       = $nextRowBackup;
                $productDataSize = strlen(serialize($bunchRows));
                $startNewBunch   = false;
                $nextRowBackup   = array();
            }
            if ($source->valid()) {
                if ($this->_errorsCount >= $this->_errorsLimit) { // errors limit check
                    return false;
                }
                $rowData = $source->current();

                $this->_processedRowsCount++;
                if ($this->validateRow($rowData, $source->key())) { // add row to bunch for save
                    $rowData = $this->_prepareRowForDb($rowData);
                    $rowSize = strlen(Mage::helper('core')->jsonEncode($rowData));

                    $isBunchSizeExceeded = ($bunchSize > 0 && count($bunchRows) >= $bunchSize);

                    if (($productDataSize + $rowSize) >= $maxDataSize || $isBunchSizeExceeded) {
                        $startNewBunch = true;
                        $nextRowBackup = array($source->key() => $rowData);
                    } else {
                        $bunchRows[$source->key()] = $rowData;
                        $productDataSize += $rowSize;
                    }
                }
                $source->next();
            }
        }
        return $this;
    }
    /**
     * Import data rows.
     *
     * @return boolean
     */
    protected function _importData()
    {
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            $this->_deleteAttributeOptions();
        } else {
            $this->_saveAttributeOptions();
        }
        Mage::dispatchEvent('attribute_option_import_finish_before', array('adapter'=>$this));
        return true;
    }


    protected function _saveAttributeOptions()
    {
        $strftimeFormat = Varien_Date::convertZendToStrftime(Varien_Date::DATETIME_INTERNAL_FORMAT, true, true);
        $nextEntityId = Mage::getResourceHelper('importexport')->getNextAutoincrement(Mage::getSingleton('core/resource')->getTableName('eav/attribute_option'));
        static $entityId;
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $attributeRowsIn = $valueRowsIn = array();
            $attributeRowsUp = $valueRowsUp = array();

            foreach ($bunch as $rowNum => $rowData) {

                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }
                $optionRow = array(
                    'attribute_id' => Mage::getModel('eav/entity_attribute')->getIdByCode('catalog_product', $rowData['attribute_code']),
                    'sort_order' => isset($rowData['order']) ? $rowData['order'] : '0',
                    'value' => $rowData['admin']
                );
//                var_dump($this->_getAvailableAttributeOptions($rowData['attribute_code']));
                if (!isset($this->_getAvailableAttributeOptions($rowData['attribute_code'])[$rowData['admin']])) {
                    $attributeRowsIn[] = $optionRow;
                } elseif ($this->_getAvailableAttributeOptions($rowData['attribute_code'])[$rowData['admin']]['sort_order'] != $rowData['order'] || isset($rowData['admin_new'])) {
                    if (!isset($rowData['admin_new'])) {
                        unset($optionRow['value']);
                    } else {
                        $optionRow['value'] = $rowData['admin_new'];
                        $optionRow['value_id'] = $this->_getAvailableAttributeOptions($rowData['attribute_code'])[$rowData['admin']]['value_id'];
                    }
                    $optionRow['option_id'] = $this->_getOptionId($rowData);
                    $attributeRowsUp[] = $optionRow;
                }
                /*
                 * add logic for storeview specific labels here
                 */
            }
//         var_dump($attributeRowsIn);
//            var_dump($attributeRowsUp);
            $this->_saveOrUpdateAttributeOptions($attributeRowsIn, $attributeRowsUp);
            //$this->_saveOrUpdateAttributeOptionValues($valueRowsIn, $attributeRowsUp);
        }
    }

    /**
     *
     * Update and insert data in entity table.
     *
     * @param array $entityRowsIn Row for insert
     * @param array $entityRowsUp Row for update
     * @return Mage_ImportExport_Model_Import_Entity_Customer
     */
    protected function _saveOrUpdateAttributeOptions(array $rowsIn, array $rowsUp)
    {
        if ($rowsIn) {
            foreach ($rowsIn as $row) {
                $this->_connection->insertOnDuplicate($this->_optionTable, array('attribute_id' => $row['attribute_id'] , 'sort_order' => $row['sort_order']));
                $this->_connection->insertOnDuplicate($this->_optionValueTable, array('option_id' => $this->_connection->lastInsertId(), 'value' =>$row['value'], 'store_id' => 0));
            }
        }
        if ($rowsUp) {
            var_dump($rowsUp);
            foreach ($rowsUp as $row) {
                $this->_connection->insertOnDuplicate(
                    $this->_optionTable,
                    array('sort_order' => $row['sort_order'], 'option_id' => $row['option_id']),
                    array('sort_order')
                );
                if (isset($row['value'])) {
                    $this->_connection->insertOnDuplicate($this->_optionValueTable, array('value_id' => $row['value_id'] ,'option_id' => $row['option_id'], 'value' =>$row['value'], 'store_id' => 0));

                }
            }
        }
        return $this;
    }

    protected function _saveOrUpdateAttributeOptionValues(array $rowsIn, array $rowsUp)
    {
        if ($rowsIn) {
            $this->_connection->insertMultiple($this->_optionValueTable, $rowsIn);
        }
        if ($rowsUp) {
            $this->_connection->insertOnDuplicate(
                $this->_optionValueTable,
                $rowsUp,
                array('sort_order')
            );
        }
        return $this;

    }

    protected function _getOptionId($rowData)
    {
        if (isset($rowData['option_id'])) {
            $optionId = $rowData['option_id'];
        } elseif (isset($this->_getAvailableAttributeOptions($rowData['attribute_code'])[$rowData['admin']])) {
            $optionId = $this->_getAvailableAttributeOptions($rowData['attribute_code'])[$rowData['admin']]['option_id'];
        } else {
            $optionId = Mage::getResourceHelper('importexport')->getNextAutoincrement(Mage::getSingleton('core/resource')->getTableName('eav/attribute_option'));
        }
        return $optionId;
    }
    /**
     * Import behavior getter.
     *
     * @return string
     */
    public function getBehavior()
    {
        if (!isset($this->_parameters['behavior'])
            || ($this->_parameters['behavior'] != Mage_ImportExport_Model_Import::BEHAVIOR_APPEND
                && $this->_parameters['behavior'] != Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE
                && $this->_parameters['behavior'] != Mage_ImportExport_Model_Import::BEHAVIOR_DELETE)) {
            return Mage_ImportExport_Model_Import::getDefaultBehavior();
        }
        return $this->_parameters['behavior'];
    }

    public function getInvalidRowsCount() {
        return count($this->_invalidRows);
    }
    public function getProcessedRowsCount() {
        return $this->_processedRowsCount;
    }

    /**
     * Validate data row.
     *
     * @param array $rowData
     * @param int $rowNum
     * @return boolean
     */
    public function validateRow(array $rowData, $rowNum)
    {
        // check if row is already validated
        if (isset($this->_validatedRows[$rowNum])) {
            return !isset($this->_invalidRows[$rowNum]);
        }
        $this->_validatedRows[$rowNum] = true;

         if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            return false;
        }

        $this->_processedOptionsCount++;

        $attribute = $rowData['attribute_code'];
        $option = $rowData['admin'];

        if (!in_array($attribute, $this->_getAvailableAttributes())) {
            $this->addRowError(self::ERROR_ATTRIBUTE_IS_NOT_VALID, $rowNum);
            return false;
        }

        /*
         * way more checking should be in here, but time rushes me to allow admin changes only (no stores present in actual setup)
         */
        return !isset($this->_invalidRows[$rowNum]);
    }


    const COL_DELETE = 'delete';

    /**
     * Set the error limit when the importer will stop
     * @param $limit
     */
    public function setErrorLimit($limit) {
        if ($limit) {
            $this->_errorsLimit = $limit;
        } else {
            $this->_errorsLimit = 100;
        }
    }


    public function quickNDirtyUpdateAttributeOptions($row)
    {
        foreach ($row as $data) {
            $this->isAttributeCodeValid($data['attribute_code']);
            $id = Mage::getModel('eav/entity_attribute')->getIdByCode('catalog_product', $data['attribute_code']);
            $attr = Mage::getModel('eav/entity_attribute')->load($id);
            //set adminid to have a general and always present identifier for the attribute options
            $allOptions = $attr->setStoreId(0)->getSource()->getAllOptions(false);
            foreach (Mage::app()->getStores() as $store) {
                $stores[$store->getName()] = $store->getId();
            }

            foreach ($allOptions as $option) {
                $values = array();
                if ($option['label'] == $data['admin']) {
                    if (isset($data['admin_new'])) {
                        $values[] = $data['admin_new'];
                    } else {
                        $values[] = $data['admin'];
                    }
                    if (isset($data['view']) && is_array($data['view'])) {
                        foreach ($data['view'] as $viewKey => $view) {
                            $values[$stores[$viewKey]] = $view;
                        }

                    }
                    $updated = array(self::COL_ORDER => array($option['value'] => $data['order']),
                        self::COL_VALUE => array($option['value'] => $values),
                    );
                }
            }
           // var_dump($data);
//            var_dump($updated);

            if (isset($data['label'])) {

                $labels = array();
                foreach ($data['label'] as $labelKey => $viewLabel) {
                    $labels[$stores[$labelKey]] = $viewLabel;
                }
                $attr->setData('store_labels', $labels);
            }


            $attr->setOption($updated);
            $result = $attr->save();

        }
        return true;
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