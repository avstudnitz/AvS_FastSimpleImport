<?php
/**
 * Created by PhpStorm.
 * User: havoc
 * Date: 10/25/14
 * Time: 8:44 PM
 */
class AvS_FastSimpleImport_Model_Import_Catalogrule_Rule
{
    protected $_dataValidated = false;
    /**
     * Error counter.
     *
     * @var int
     */
    protected $_errorsCount = 0;
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

    protected $_neededColumns = array(
        'name',
        'is_active',
        'conditions_serialized',
        'actions_serialized',
        'simple_action',
        'discount_amount',

    );
    protected $_processedCatalogrules;
    /**
     * Size of bunch - part of entities to save in one step.
     */
    const BUNCH_SIZE = 20;
    const ERROR_CATALOGRULE_NOT_PRESENT = 'catalogruleNotPresent';
    const ERROR_CATALOGRULE_RULE_SERIALIZED = 'serializedRuleProblem';
    const ERROR_CATALOGRULE_SIMPE_ACTION = 'simpleActionNotSupported';
    const ENTITY_TYPE_CODE_FAKE = 'catalogrule';

    public function __construct()
    {
        $this->_dataSourceModel = Mage_ImportExport_Model_Import::getDataSourceModel();
        $this->_connection      = Mage::getSingleton('core/resource')->getConnection('write');
        $this->_catalogruleTable = Mage::getSingleton('core/resource')->getTableName('catalogrule/rule');
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
    public function getProcessedCatalogruleCount()
    {
        return count($this->_processedCatalogrules);
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
                $this->getProcessedRowsCount(), $this->getProcessedRulesCount(),
                $this->getInvalidRowsCount(), $this->getErrorsCount()
            ),
            Mage::helper('importexport')->__('Import has been done successfuly.')
        ));
        return $result;
    }

    public function getProcessedRulesCount()
    {
        return count($this->_processedCatalogrules);
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
    public function validateData()
    {
        if (!$this->_dataValidated) {

            if (($colsAbsent = array_diff($this->_neededColumns, $this->_getSource()->getColNames()))) {
                Mage::throwException(
                    Mage::helper('importexport')->__('Can not find required columns: %s', implode(', ', $colsAbsent))
                );
            }
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


        if (isset($rowData['rule_id']) && !in_array($rowData['rule_id'], $this->_getAvailableRules())) {

            $this->addRowError(self::ERROR_CATALOGRULE_NOT_PRESENT, $rowNum);
            return false;
        }
        if (!unserialize($rowData['conditions_serialized']) || !unserialize($rowData['actions_serialized'])) {
            $this->addRowError(self::ERROR_CATALOGRULE_RULE_SERIALIZED, $rowNum);
            return false;

        }


        return !isset($this->_invalidRows[$rowNum]);
    }
    protected function _getAvailableRules()
    {
        $rules = Mage::getModel('catalogrule/rule')
            ->getCollection()
            ;
        foreach ($rules as $rule) {
            $result[] = $rule['rule_id'];
        }
        return $result;
    }
    protected function _importData()
    {
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            $this->_deleteCatalogrules();
        } else {
            $this->_saveCatalogrules();
        }
        Mage::dispatchEvent('catalogrule_import_finish_before', array('adapter'=>$this));
        return true;

    }
    protected function _deleteCatalogrules()
    {

    }

    protected function _saveCatalogrules()
    {
        $nextRuleId = Mage::getResourceHelper('importexport')->getNextAutoincrement(Mage::getSingleton('core/resource')->getTableName('catalogrule/rule'));
        static $entityId;
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $catalogruleRowsIn = $valueRowsIn = array();
            $catalogruleRowsUp = $valueRowsUp = array();

            foreach ($bunch as $rowNum => $rowData) {

                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }
                if (isset($rowData['rule_id'])) {
                    $catalogruleRowsUp[] = $rowData;
                } else {
                    $rowData['rule_id'] = $nextRuleId;
                    $catalogruleRowsIn[] = $rowData;
                }

            }
        }
        $this->_saveOrUpdateCatalogrules($catalogruleRowsIn, $catalogruleRowsUp);
    }
    protected function _saveOrUpdateCatalogrules(array $rowsIn, array $rowsUp)
    {
        if ($rowsIn) {
            $this->_connection->insertMultiple($this->_catalogruleTable, $rowsIn);
        }
        if ($rowsUp) {
            $this->_connection->insertOnDuplicate(
                $this->_catalogruleTable,
                $rowsUp
            );
        }
        return $this;

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


}