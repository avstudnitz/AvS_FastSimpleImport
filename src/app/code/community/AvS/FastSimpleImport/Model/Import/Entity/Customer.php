<?php

/**
 * Entity Adapter for importing Magento Customers
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
class AvS_FastSimpleImport_Model_Import_Entity_Customer extends Mage_ImportExport_Model_Import_Entity_Customer
{
    /**
     * Code of a primary attribute which identifies the entity group if import contains of multiple rows
     *
     * @var string
     */
    protected $_masterAttributeCode = 'email';

    /** @var null|bool */
    protected $_unsetEmptyFields = false;

    /** @var null|bool */
    protected $_symbolEmptyFields = false;


    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->_addressEntity = Mage::getModel('fastsimpleimport/import_entity_customer_address', $this);
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

    /** @var bool */
    protected $_ignoreDuplicates = false;

    public function setIgnoreDuplicates($ignore)
    {
        $this->_ignoreDuplicates = (boolean) $ignore;
    }


    public function getIgnoreDuplicates()
    {
        return $this->_ignoreDuplicates;
    }


    /**
     * @param boolean $value
     * @return $this
     */
    public function setUnsetEmptyFields($value) {
        $this->_unsetEmptyFields = (boolean) $value;
        return $this;
    }


    /**
     * @param string $value
     * @return $this
     */
    public function setSymbolEmptyFields($value) {
        $this->_symbolEmptyFields = $value;
        return $this;
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
     * Import behavior setter
     *
     * @param string $behavior
     */
    public function setBehavior($behavior)
    {
        $this->_parameters['behavior'] = $behavior;
    }

    /**
     * Gather and save information about customer entities.
     *
     * @return Mage_ImportExport_Model_Import_Entity_Customer
     */
    protected function _saveCustomers()
    {
        /** @var $resource Mage_Customer_Model_Customer */
        $resource       = Mage::getModel('customer/customer');
        $strftimeFormat = Varien_Date::convertZendToStrftime(Varien_Date::DATETIME_INTERNAL_FORMAT, true, true);
        $table = $resource->getResource()->getEntityTable();
        $nextEntityId   = Mage::getResourceHelper('importexport')->getNextAutoincrement($table);
        $passId         = $resource->getAttribute('password_hash')->getId();
        $passTable      = $resource->getAttribute('password_hash')->getBackend()->getTable();

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityRowsIn = array();
            $entityRowsUp = array();
            $attributes   = array();

            $oldCustomersToLower = array_change_key_case($this->_oldCustomers, CASE_LOWER);

            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }
                $this->_filterRowData($rowData);
                if (self::SCOPE_DEFAULT == $this->getRowScope($rowData)) {
                    // entity table data
                    $entityRow = array(
                        'group_id'   => empty($rowData['group_id']) ? self::DEFAULT_GROUP_ID : $rowData['group_id'],
                        'increment_id' => empty($rowData['increment_id']) ? null : $rowData['increment_id'],
                        'store_id'   => empty($rowData[self::COL_STORE])
                                        ? 0 : $this->_storeCodeToId[$rowData[self::COL_STORE]],
                        'created_at' => empty($rowData['created_at'])
                                        ? now() : gmstrftime($strftimeFormat, strtotime($rowData['created_at'])),
                        'updated_at' => now()
                    );

                    $emailToLower = strtolower($rowData[self::COL_EMAIL]);
                    if (isset($oldCustomersToLower[$emailToLower][$rowData[self::COL_WEBSITE]])) { // edit
                        $entityId = $oldCustomersToLower[$emailToLower][$rowData[self::COL_WEBSITE]];
                        $entityRow['entity_id'] = $entityId;
                        $entityRowsUp[] = $entityRow;
                    } else { // create
                        $entityId                      = $nextEntityId++;
                        $entityRow['entity_id']        = $entityId;
                        $entityRow['entity_type_id']   = $this->_entityTypeId;
                        $entityRow['attribute_set_id'] = 0;
                        $entityRow['website_id']       = $this->_websiteCodeToId[$rowData[self::COL_WEBSITE]];
                        $entityRow['email']            = $rowData[self::COL_EMAIL];
                        $entityRow['is_active']        = 1;
                        $entityRowsIn[]                = $entityRow;

                        $this->_newCustomers[$rowData[self::COL_EMAIL]][$rowData[self::COL_WEBSITE]] = $entityId;
                    }
                    // attribute values
                    foreach (array_intersect_key($rowData, $this->_attributes) as $attrCode => $value) {
                        if (!$this->_attributes[$attrCode]['is_static']) {
                            /** @var $attribute Mage_Customer_Model_Attribute */
                            $attribute  = $resource->getAttribute($attrCode);
                            $backModel  = $attribute->getBackendModel();
                            $attrParams = $this->_attributes[$attrCode];

                            if ('select' == $attrParams['type']) {
                                $value = $attrParams['options'][strtolower($value)];
                            } elseif ('datetime' == $attrParams['type']) {
                                $value = gmstrftime($strftimeFormat, strtotime($value));
                            } elseif ($backModel) {
                                $attribute->getBackend()->beforeSave($resource->setData($attrCode, $value));
                                $value = $resource->getData($attrCode);
                            }
                            $attributes[$attribute->getBackend()->getTable()][$entityId][$attrParams['id']] = $value;

                            // restore 'backend_model' to avoid default setting
                            $attribute->setBackendModel($backModel);
                        }
                    }
                    // password change/set
                    if (isset($rowData['password']) && strlen($rowData['password'])) {
                        $attributes[$passTable][$entityId][$passId] = $resource->hashPassword($rowData['password']);
                    }
                }
            }
            $this->_saveCustomerEntity($entityRowsIn, $entityRowsUp)->_saveCustomerAttributes($attributes);
        }
        return $this;
    }

    /**
     * Update and insert data in entity table.
     *
     * @param array $entityRowsIn Row for insert
     * @param array $entityRowsUp Row for update
     * @return Mage_ImportExport_Model_Import_Entity_Customer
     */
    protected function _saveCustomerEntity(array $entityRowsIn, array $entityRowsUp)
    {
        if ($entityRowsIn) {
            $this->_connection->insertMultiple($this->_entityTable, $entityRowsIn);
        }
        if ($entityRowsUp) {
            $this->_connection->insertOnDuplicate(
                $this->_entityTable,
                $entityRowsUp,
                array('group_id', 'store_id', 'updated_at', 'created_at', 'increment_id')
            );
        }
        return $this;
    }

    /**
     * Check one attribute. Can be overridden in child.
     *
     * @param string $attrCode Attribute code
     * @param array $attrParams Attribute params
     * @param array $rowData Row data
     * @param int $rowNum
     * @return boolean
     */
    public function isAttributeValid($attrCode, array $attrParams, array $rowData, $rowNum)
    {
        $message = '';
        switch ($attrParams['type']) {
            case 'varchar':
            case 'multiselect': // isn't properly supported in customer import, so no validation.
                $val   = Mage::helper('core/string')->cleanString($rowData[$attrCode]);
                $valid = Mage::helper('core/string')->strlen($val) < self::DB_MAX_VARCHAR_LENGTH;
                $message = 'String is too long, only ' . self::DB_MAX_VARCHAR_LENGTH . ' characters allowed.';
                break;
            case 'decimal':
                $val   = trim($rowData[$attrCode]);
                $valid = (float)$val == $val;
                $message = 'Decimal value expected. Your Input: '.$rowData[$attrCode];
                break;
            case 'select':
                $valid = isset($attrParams['options'][strtolower($rowData[$attrCode])]);
                $message = 'Possible options are: ' . implode(', ', array_keys($attrParams['options'])) . '. Your input: ' . $rowData[$attrCode];
                break;
            case 'int':
                $val   = trim($rowData[$attrCode]);
                $valid = (int)$val == $val;
                $message = 'Integer value expected. Your Input: '.$rowData[$attrCode];
                break;
            case 'datetime':
                $val   = trim($rowData[$attrCode]);
                $valid = strtotime($val) !== false
                    || preg_match('/^\d{2}.\d{2}.\d{2,4}(?:\s+\d{1,2}.\d{1,2}(?:.\d{1,2})?)?$/', $val);
                $message = 'Datetime value expected. Your Input: ' . $rowData[$attrCode];
                break;
            case 'text':
                $val   = Mage::helper('core/string')->cleanString($rowData[$attrCode]);
                $valid = Mage::helper('core/string')->strlen($val) < self::DB_MAX_TEXT_LENGTH;
                $message = 'String is too long, only ' . self::DB_MAX_TEXT_LENGTH . ' characters allowed. Your input: ' . $rowData[$attrCode] . ', length: ' . strlen($val);;
                break;
            default:
                $valid = true;
                break;
        }

        if (!$valid) {
            $this->addRowError(Mage::helper('importexport')->__("Invalid value for '%s'") . '. ' . $message, $rowNum, $attrCode);
        } elseif (!empty($attrParams['is_unique'])) {
            if (isset($this->_uniqueAttributes[$attrCode][$rowData[$attrCode]])) {
                $this->addRowError(Mage::helper('importexport')->__("Duplicate Unique Attribute for '%s'"), $rowNum, $attrCode);
                return false;
            }
            $this->_uniqueAttributes[$attrCode][$rowData[$attrCode]] = true;
        }
        return (bool) $valid;
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
        static $email   = null; // e-mail is remembered through all customer rows
        static $website = null; // website is remembered through all customer rows

        if (isset($rowData['fsi_line_number'])) {
            $rowNum = $rowData['fsi_line_number'];
        }

        $this->_filterRowData($rowData);


        if (isset($this->_validatedRows[$rowNum])) { // check that row is already validated
            return !isset($this->_invalidRows[$rowNum]);
        }
        $this->_validatedRows[$rowNum] = true;

        $rowScope = $this->getRowScope($rowData);

        if (self::SCOPE_DEFAULT == $rowScope) {
            $this->_processedEntitiesCount ++;
        }
        // BEHAVIOR_DELETE use specific validation logic
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            if (self::SCOPE_DEFAULT == $rowScope
                && !isset($this->_oldCustomers[$rowData[self::COL_EMAIL]][$rowData[self::COL_WEBSITE]])) {
                $this->addRowError(self::ERROR_EMAIL_SITE_NOT_FOUND, $rowNum);
            }
        } elseif (self::SCOPE_DEFAULT == $rowScope) { // row is SCOPE_DEFAULT = new customer block begins
            $email   = strtolower($rowData[self::COL_EMAIL]);
            $website = $rowData[self::COL_WEBSITE];

            if (!Zend_Validate::is($email, 'EmailAddress')) {
                $this->addRowError(self::ERROR_INVALID_EMAIL, $rowNum);
            } elseif (!isset($this->_websiteCodeToId[$website])) {
                $this->addRowError(self::ERROR_INVALID_WEBSITE, $rowNum);
            } else {
                if (isset($this->_newCustomers[$email][$website]) && !$this->getIgnoreDuplicates()) {
                    $this->addRowError(self::ERROR_DUPLICATE_EMAIL_SITE, $rowNum);
                }
                $this->_newCustomers[$email][$website] = false;

                if (!empty($rowData[self::COL_STORE]) && !isset($this->_storeCodeToId[$rowData[self::COL_STORE]])) {
                    $this->addRowError(self::ERROR_INVALID_STORE, $rowNum);
                }
                // check password
                if (isset($rowData['password']) && strlen($rowData['password'])
                    && Mage::helper('core/string')->strlen($rowData['password']) < self::MAX_PASSWD_LENGTH
                ) {
                    $this->addRowError(self::ERROR_PASSWORD_LENGTH, $rowNum);
                }
                // check simple attributes
                foreach ($this->_attributes as $attrCode => $attrParams) {
                    if (in_array($attrCode, $this->_ignoredAttributes)) {
                        continue;
                    }
                    if (isset($rowData[$attrCode]) && strlen($rowData[$attrCode])) {
                        $this->isAttributeValid($attrCode, $attrParams, $rowData, $rowNum);
                    } elseif ($attrParams['is_required'] && !isset($this->_oldCustomers[$email][$website])) {
                        $this->addRowError(self::ERROR_VALUE_IS_REQUIRED, $rowNum, $attrCode);
                    }
                }
            }
            if (isset($this->_invalidRows[$rowNum])) {
                $email = false; // mark row as invalid for next address rows
            }
        } else {
            if (null === $email) { // first row is not SCOPE_DEFAULT
                $this->addRowError(self::ERROR_EMAIL_IS_EMPTY, $rowNum);
            } elseif (false === $email) { // SCOPE_DEFAULT row is invalid
                $this->addRowError(self::ERROR_ROW_IS_ORPHAN, $rowNum);
            }
        }
        // validate row data by address entity
        $this->_addressEntity->validateRow($rowData, $rowNum);

        if (isset($this->_invalidRows[$rowNum])) {
            $email = false; // mark row as invalid for next address rows
        }

        return !isset($this->_invalidRows[$rowNum]);
    }


    /**
     * @param array $rowData
     */
    public function filterRowData(&$rowData) {
        $this->_filterRowData($rowData);
    }

    /**
     * Removes empty keys in case value is null or empty string
     * Behavior can be turned off with config setting "fastsimpleimport/general/clear_field_on_empty_string"
     * You can define a string which can be used for clearing a field, configured in "fastsimpleimport/product/symbol_for_clear_field"
     *
     * @param array $rowData
     */
    protected function _filterRowData(&$rowData)
    {
        if ($this->_unsetEmptyFields || $this->_symbolEmptyFields) {
            foreach($rowData as $key => $fieldValue) {
                if ($this->_unsetEmptyFields && !strlen($fieldValue)) {
                    unset($rowData[$key]);
                } else if ($this->_symbolEmptyFields && trim($fieldValue) == $this->_symbolEmptyFields) {
                    $rowData[$key] = NULL;
                }
            }
        }

        if (!isset($rowData[self::COL_EMAIL]) || $rowData[self::COL_EMAIL] === '') {
            $rowData[self::COL_EMAIL] = null;
        }
    }

    /**
     * Validate data rows and save bunches to DB.
     * Taken from https://github.com/tim-bezhashvyly/Sandfox_ImportExportFix
     *
     * @return Mage_ImportExport_Model_Import_Entity_Abstract
     */
    protected function _saveValidatedBunches()
    {
        $source = $this->_getSource();
        $bunchRows = array();
        $startNewBunch = false;
        $maxDataSize = Mage::getResourceHelper('importexport')->getMaxDataSize();
        $bunchSize = Mage::helper('importexport')->getBunchSize();

        $source->rewind();
        $this->_dataSourceModel->cleanBunches();

        while ($source->valid() || count($bunchRows) || isset($entityGroup)) {
            if ($startNewBunch || !$source->valid()) {
                /* If the end approached add last validated entity group to the bunch */
                if (!$source->valid() && isset($entityGroup)) {
                    $bunchRows = array_merge($bunchRows, $entityGroup);
                    unset($entityGroup);
                }
                $this->_dataSourceModel->saveBunch($this->getEntityTypeCode(), $this->getBehavior(), $bunchRows);
                $bunchRows = array();
                $startNewBunch = false;
            }
            if ($source->valid()) {
                if ($this->_errorsCount >= $this->_errorsLimit) { // errors limit check
                    return $this;
                }
                $rowData = $source->current();

                $this->_processedRowsCount++;

                if (isset($rowData[$this->_masterAttributeCode]) && trim($rowData[$this->_masterAttributeCode])) {
                    /* Add entity group that passed validation to bunch */
                    if (isset($entityGroup)) {
                        $bunchRows = array_merge($bunchRows, $entityGroup);
                        $productDataSize = strlen(serialize($bunchRows));

                        /* Check if the nw bunch should be started */
                        $isBunchSizeExceeded = ($bunchSize > 0 && count($bunchRows) >= $bunchSize);
                        $startNewBunch = $productDataSize >= $maxDataSize || $isBunchSizeExceeded;
                    }

                    /* And start a new one */
                    $entityGroup = array();
                }
                $this->validateRow($rowData, $source->key());
                if (isset($entityGroup)) {
                    /* Add row to entity group */
                    $entityGroup[$source->key()] = $this->_prepareRowForDb($rowData);
                }
                $source->next();
            }
        }
        return $this;
    }
}