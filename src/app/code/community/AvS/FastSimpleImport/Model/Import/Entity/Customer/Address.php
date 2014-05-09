<?php
/**
 * Entity Adapter for importing Magento Categories
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Paul Hachmang <paul@h-o.nl>
 */
/**
 * @property AvS_FastSimpleImport_Model_Import_Entity_Customer $_customer
 */
class AvS_FastSimpleImport_Model_Import_Entity_Customer_Address
    extends Mage_ImportExport_Model_Import_Entity_Customer_Address {
    
    /**
     * Import data rows.
     *
     * @return boolean
     */
    protected function _importData()
    {
        /** @var $customer Mage_Customer_Model_Customer */
        $customer       = Mage::getModel('customer/customer');
        /** @var $resource Mage_Customer_Model_Address */
        $resource       = Mage::getModel('customer/address');
        $strftimeFormat = Varien_Date::convertZendToStrftime(Varien_Date::DATETIME_INTERNAL_FORMAT, true, true);
        $table          = $resource->getResource()->getEntityTable();
        $nextEntityId   = Mage::getResourceHelper('importexport')->getNextAutoincrement($table);
        $customerId     = null;
        $regionColName  = self::getColNameForAttrCode('region');
        $countryColName = self::getColNameForAttrCode('country_id');
        /** @var $regionIdAttr Mage_Customer_Model_Attribute */
        $regionIdAttr   = Mage::getSingleton('eav/config')->getAttribute($this->getEntityTypeCode(), 'region_id');
        $regionIdTable  = $regionIdAttr->getBackend()->getTable();
        $regionIdAttrId = $regionIdAttr->getId();
        $isAppendMode   = Mage_ImportExport_Model_Import::BEHAVIOR_APPEND == $this->_customer->getBehavior();

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityRows = array();
            $attributes = array();
            $defaults   = array(); // customer default addresses (billing/shipping) data

            foreach ($bunch as $rowNum => $rowData) {
                $this->_customer->filterRowData($rowData);
                if (!empty($rowData[Mage_ImportExport_Model_Import_Entity_Customer::COL_EMAIL])
                        && !empty($rowData[Mage_ImportExport_Model_Import_Entity_Customer::COL_WEBSITE])
                ) {
                    $customerId = $this->_customer->getCustomerId(
                        $rowData[Mage_ImportExport_Model_Import_Entity_Customer::COL_EMAIL],
                        $rowData[Mage_ImportExport_Model_Import_Entity_Customer::COL_WEBSITE]
                    );
                }
                if (!$customerId || !$this->_isRowWithAddress($rowData) || !$this->validateRow($rowData, $rowNum)) {
                    continue;
                }

                /** @var $addressCollection Mage_Customer_Model_Resource_Address_Collection */
                $addressCollection = Mage::getResourceModel('customer/address_collection');
                $addressCollection->addAttributeToFilter('parent_id', $customerId);

                $addressAttributes = array();
                foreach ($this->_attributes as $attrAlias => $attrParams) {
                    if (isset($rowData[$attrAlias]) && strlen($rowData[$attrAlias])) {
                        if ('select' == $attrParams['type']) {
                            $value = $attrParams['options'][strtolower($rowData[$attrAlias])];
                        } elseif ('datetime' == $attrParams['type']) {
                            $value = gmstrftime($strftimeFormat, strtotime($rowData[$attrAlias]));
                        } else {
                            $value = $rowData[$attrAlias];
                        }
                        $addressAttributes[$attrParams['id']] = $value;
                        $addressCollection->addAttributeToFilter($attrParams['code'], $value);
                    }
                }

                // skip duplicate address
                if ($isAppendMode && $addressCollection->getSize()) {
                    continue;
                }

                $entityId = $nextEntityId++;

                // entity table data
                $entityRows[] = array(
                    'entity_id'      => $entityId,
                    'entity_type_id' => $this->_entityTypeId,
                    'parent_id'      => $customerId,
                    'created_at'     => now(),
                    'updated_at'     => now()
                );
                // attribute values
                foreach ($this->_attributes as $attrAlias => $attrParams) {
                    if (isset($addressAttributes[$attrParams['id']])) {
                        $attributes[$attrParams['table']][$entityId][$attrParams['id']]
                            = $addressAttributes[$attrParams['id']];
                    }
                }
                // customer default addresses
                foreach (self::getDefaultAddressAttrMapping() as $colName => $customerAttrCode) {
                    if (!empty($rowData[$colName])) {
                        $attribute = $customer->getAttribute($customerAttrCode);
                        $defaults[$attribute->getBackend()->getTable()][$customerId][$attribute->getId()] = $entityId;
                    }
                }
                // let's try to find region ID
                if (!empty($rowData[$regionColName])) {
                    $countryNormalized = strtolower($rowData[$countryColName]);
                    $regionNormalized  = strtolower($rowData[$regionColName]);

                    if (isset($this->_countryRegions[$countryNormalized][$regionNormalized])) {
                        $regionId = $this->_countryRegions[$countryNormalized][$regionNormalized];
                        $attributes[$regionIdTable][$entityId][$regionIdAttrId] = $regionId;
                        // set 'region' attribute value as default name
                        $tbl = $this->_attributes[$regionColName]['table'];
                        $regionColNameId = $this->_attributes[$regionColName]['id'];
                        $attributes[$tbl][$entityId][$regionColNameId] = $this->_regions[$regionId];
                    }
                }
            }
            $this->_saveAddressEntity($entityRows)
                ->_saveAddressAttributes($attributes)
                ->_saveCustomerDefaults($defaults);
        }
        return true;
    }
}