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
    /** @var array */
    protected $_sku = null;

    /**
     * Error codes.
     */
    const ERROR_INVALID_SKU = 'invalidSku';
    const ERROR_INVALID_QTY = 'invalidQty';

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

    public function __construct() {
        $add = array('_wishlist_shared', '_wishlist_updated_at', '_wishlist_sharing_code',
                     '_wishlist_item_sku', '_wishlist_item_added_at', '_wishlist_item_description',
                     '_wishlist_item_qty');
        $this->_particularAttributes = array_merge($this->_particularAttributes, $add);

        $this->_messageTemplates[self::ERROR_INVALID_SKU] = 'SKU not found';
        $this->_messageTemplates[self::ERROR_INVALID_QTY] = 'Qty must me numeric';
        parent::__construct();
    }


    /**
     * Save customer data to DB.
     *
     * @throws Exception
     * @return bool Result of operation.
     */
    protected function _importData()
    {
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            $this->_deleteCustomers();
        } else {
            $this->_saveCustomers();
            $this->_addressEntity->importData();
            $this->_saveWishlists();
        }
        return true;
    }

    /**
     * Initialize existent product SKUs.
     *
     * @return Mage_ImportExport_Model_Import_Entity_Product
     */
    protected function _initSkus()
    {
        $columns = array('entity_id', 'type_id', 'attribute_set_id', 'sku');
        foreach (Mage::getModel('catalog/product')->getProductEntitiesInfo($columns) as $info) {
            $this->_sku[$info['sku']] =$info['entity_id'];
        }
        return $this;
    }

    public function getProductId($sku) {
        if ($this->_sku === null) {
            $this->_initSkus();
        }
        return isset($this->_sku[$sku]) ? (int) $this->_sku[$sku] : null;
    }

    protected function _saveWishlists() {

        $entityItemTable = Mage::getResourceModel('wishlist/item')->getMainTable();
        $oldCustomersToLower = array_change_key_case($this->_oldCustomers, CASE_LOWER);
        $newCustomersToLower = array_change_key_case($this->_newCustomers, CASE_LOWER);

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $customerId = null;
            $wishlistItems = array();

            // Format bunch to stock data rows
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->isRowAllowedToImport($rowData, $rowNum)) {
                    continue;
                }

                if (self::SCOPE_DEFAULT == $this->getRowScope($rowData)) {
                    $wishlist = array();

                    $emailToLower = strtolower($rowData[self::COL_EMAIL]);
                    if (isset($oldCustomersToLower[$emailToLower][$rowData[self::COL_WEBSITE]])) {
                        $wishlist['customer_id'] = $oldCustomersToLower[$emailToLower][$rowData[self::COL_WEBSITE]];
                    } elseif (isset($oldCustomersToLower[$emailToLower][$rowData[self::COL_WEBSITE]])) {
                        $wishlist['customer_id'] = $newCustomersToLower[$emailToLower][$rowData[self::COL_WEBSITE]];
                    } else {
                        Mage::throwException('Customer not found, shouldn\'t happen');
                    }

                    $keyLength = strlen('_wishlist_');
                    foreach ($rowData as $key => $value) {
                        if (strpos($key, '_wishlist_') === 0 && strpos($key, '_wishlist_item_') === false && !empty($value)) {
                            $wishlist[substr($key, $keyLength)] = $value;
                        }
                    }

                    $wishlistModel = Mage::getModel('wishlist/wishlist');
                    $wishlistModel->loadByCustomer($wishlist['customer_id']);

                    if (! isset($wishlist['sharing_code']) || empty($wishlist['sharing_code'])) {
                        $wishlist['sharing_code'] = Mage::helper('core')->uniqHash();
                    }

                    if (! isset($wishlist['updated_at']) || empty($wishlist['updated_at'])) {
                        $wishlist['updated_at'] = Mage::getSingleton('core/date')->gmtDate();
                    }

                    $wishlistModel->addData($wishlist);
                    $wishlistModel->save();
                    $wishlistId = (int) $wishlistModel->getId();
                }

                $wishlistItem = array();

                if ($this->getBehavior() != Mage_ImportExport_Model_Import::BEHAVIOR_APPEND) { // remove old data?
                    $this->_connection->delete(
                        $entityItemTable,
                        $this->_connection->quoteInto('wishlist_id = ?', $wishlistId)
                    );
                }

                $keyLength = strlen('_wishlist_item_');
                foreach ($rowData as $key => $value) {
                    if (strpos($key, '_wishlist_') === 0 && !empty($value)) {
                        $wishlistItem[substr($key, $keyLength)] = $value;
                    }
                }

                if (! isset($wishlist['added_at']) || empty($wishlist['added_at'])) {
                    $wishlist['added_at'] = Mage::getSingleton('core/date')->gmtDate();
                }

                $wishlistItem['store_id'] = empty($rowData[self::COL_STORE]) ? 0 : $this->_storeCodeToId[$rowData[self::COL_STORE]];

                $wishlistItem['wishlist_id'] = $wishlistId;
                $wishlistItem['product_id'] = $this->getProductId($wishlistItem['sku']);
                unset($wishlistItem['sku']);

                $wishlistItems[] = $wishlistItem;
            }

            if ($wishlistItems) {
                $this->_connection->insertOnDuplicate($entityItemTable, $wishlistItems);
            }
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
                $message = 'Decimal value expected.';
                break;
            case 'select':
                $valid = isset($attrParams['options'][strtolower($rowData[$attrCode])]);
                $message = 'Possible options are: ' . implode(', ', array_keys($attrParams['options']));
                break;
            case 'int':
                $val   = trim($rowData[$attrCode]);
                $valid = (int)$val == $val;
                $message = 'Integer value expected.';
                break;
            case 'datetime':
                $val   = trim($rowData[$attrCode]);
                $valid = strtotime($val) !== false
                    || preg_match('/^\d{2}.\d{2}.\d{2,4}(?:\s+\d{1,2}.\d{1,2}(?:.\d{1,2})?)?$/', $val);
                $message = 'Datetime value expected.';
                break;
            case 'text':
                $val   = Mage::helper('core/string')->cleanString($rowData[$attrCode]);
                $valid = Mage::helper('core/string')->strlen($val) < self::DB_MAX_TEXT_LENGTH;
                $message = 'String is too long, only ' . self::DB_MAX_TEXT_LENGTH . ' characters allowed.';
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
            $email   = $rowData[self::COL_EMAIL];
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

        if (isset($rowData['_wishlist_item_sku'])
            && !empty($rowData['_wishlist_item_sku'])
            && $this->getProductId($rowData['_wishlist_item_sku']) === null) {
            $this->addRowError(self::ERROR_INVALID_SKU, $rowNum, $rowData['_wishlist_item_sku']);
        }

        if (isset($rowData['_wishlist_item_qty'])
            && !empty($rowData['_wishlist_item_qty'])
            && !is_numeric($rowData['_wishlist_item_qty'])) {
            $this->addRowError(self::ERROR_INVALID_QTY, $rowNum, $rowData['_wishlist_item_qty']);
        }

        return !isset($this->_invalidRows[$rowNum]);
    }
}