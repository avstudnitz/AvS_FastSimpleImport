<?php

/**
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */

/**
 * Import Main Model
 *
 * @method AvS_FastSimpleImport_Model_Import setBehavior(string $value)
 * @method string getBehavior()
 * @method AvS_FastSimpleImport_Model_Import setPartialIndexing(boolean $value)
 * @method boolean getPartialIndexing()
 * @method AvS_FastSimpleImport_Model_Import setContinueAfterErrors(boolean $value)
 * @method boolean getContinueAfterErrors()
 * @method AvS_FastSimpleImport_Model_Import setAllowRenameFiles(boolean $value)
 * @method boolean getAllowRenameFiles()
 * @method AvS_FastSimpleImport_Model_Import setIgnoreDuplicates(boolean $value)
 * @method boolean getIgnoreDuplicates()
 * @method array getDropdownAttributes()
 * @method array getMultiselectAttributes()
 */
class AvS_FastSimpleImport_Model_Import extends Mage_ImportExport_Model_Import
{
    protected function _construct()
    {
        $this->setBehavior(self::BEHAVIOR_REPLACE);
        $this->setPartialIndexing(false);
        $this->setContinueAfterErrors(false);
        $this->setDropdownAttributes(array());
        $this->setMultiselectAttributes(array());
        $this->setAllowRenameFiles(true);
    }

    /**
     * Import products
     *
     * @param array $data
     * @param string|null $behavior
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function processProductImport($data, $behavior = null)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }

        $this->setEntity(Mage_Catalog_Model_Product::ENTITY);

        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Entity_Product */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_product');
        $entityAdapter->setBehavior($this->getBehavior());
        $entityAdapter->setIsDryrun(false);
        $entityAdapter->setDropdownAttributes($this->getDropdownAttributes());
        $entityAdapter->setMultiselectAttributes($this->getMultiselectAttributes());
        $entityAdapter->setAllowRenameFiles($this->getAllowRenameFiles());
        $this->setEntityAdapter($entityAdapter);

        $validationResult = $this->validateSource($data);
        if ($this->getProcessedRowsCount() > 0) {
            if (!$validationResult) {
                if ($entityAdapter->getErrorsCount() >= $entityAdapter->getErrorsLimit()) {
                    Mage::throwException(
                        sprintf("Error Limit of %s Errors reached, stopping import.", $entityAdapter->getErrorsLimit())
                            . "\n" . $this->getErrorMessage()
                    );
                }

                if (!$this->getContinueAfterErrors()) {

                    Mage::throwException($this->getErrorMessage());
                }
            }

            if ($this->getProcessedRowsCount() > $this->getInvalidRowsCount()) {
                if ($this->getPartialIndexing()) {

                    $this->_prepareDeletedProductsReindex();
                    $this->importSource();
                    $this->reindexImportedProducts();
                } else {
                    $this->importSource();
                    $this->invalidateIndex();
                }
            }
        }

        return $this;
    }


    /**
     * Import products
     *
     * @param array       $data
     * @param string|null $behavior
     *
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function dryrunProductImport($data, $behavior = null)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }

        $this->setEntity(Mage_Catalog_Model_Product::ENTITY);

        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Entity_Product */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_product');
        $entityAdapter->setBehavior($this->getBehavior());
        $entityAdapter->setIsDryRun(true);
        $entityAdapter->setDropdownAttributes($this->getDropdownAttributes());
        $entityAdapter->setMultiselectAttributes($this->getMultiselectAttributes());
        $this->setEntityAdapter($entityAdapter);

        $validationResult = $this->validateSource($data);
        return $validationResult;
    }

    /**
     * Import customers
     *
     * @param array $data
     * @param string $behavior
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function processCustomerImport($data, $behavior = null)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }

        $this->setEntity('customer');

        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Entity_Customer */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_customer');
        $entityAdapter->setBehavior($this->getBehavior());
        $this->setEntityAdapter($entityAdapter);
        $validationResult = $this->validateSource($data);
        if ($this->getProcessedRowsCount() > 0) {
            if (!$validationResult) {
                if ($entityAdapter->getErrorsCount() >= $entityAdapter->getErrorsLimit()) {
                    Mage::throwException(
                        sprintf("Error Limit of %s Errors reached, stopping import.", $entityAdapter->getErrorsLimit())
                            . "\n" . $this->getErrorMessage()
                    );
                }

                if (!$this->getContinueAfterErrors()) {
                    Mage::throwException($this->getErrorMessage());
                }
            }

            if ($this->getProcessedRowsCount() > $this->getInvalidRowsCount()) {
                $this->importSource();
            }
        }

        return $this;
    }

    /**
     * Import products
     *
     * @param array       $data
     * @param string|null $behavior
     *
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function dryrunCustomerImport($data, $behavior = null)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }

        $this->setEntity('customer');

        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Entity_Customer */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_customer');
        $entityAdapter->setBehavior($this->getBehavior());
        $this->setEntityAdapter($entityAdapter);

        $validationResult = $this->validateSource($data);
        return $validationResult;
    }

    /**
     * Import categories
     *
     * @param array $data
     * @param string $behavior
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function processCategoryImport($data, $behavior = null)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }

        $this->setEntity(Mage_Catalog_Model_Category::ENTITY);

        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Entity_Category */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_category');
        $entityAdapter->setBehavior($this->getBehavior());
        $entityAdapter->setIgnoreDuplicates($this->getIgnoreDuplicates());
        $this->setEntityAdapter($entityAdapter);
        $validationResult = $this->validateSource($data);
        if ($this->getProcessedRowsCount() > 0) {
            if (!$validationResult) {
                if ($entityAdapter->getErrorsCount() >= $entityAdapter->getErrorsLimit()) {
                    Mage::throwException(
                        sprintf("Error Limit of %s Errors reached, stopping import.", $entityAdapter->getErrorsLimit())
                            . "\n" . $this->getErrorMessage()
                    );
                }

                if (!$this->getContinueAfterErrors()) {
                    Mage::throwException($this->getErrorMessage());
                }
            }

            if ($this->getProcessedRowsCount() > $this->getInvalidRowsCount()) {
                $this->importSource();

                $this->getEntityAdapter()->updateChildrenCount();

                if ($this->getPartialIndexing()) {
                    $this->getEntityAdapter()->reindexImportedCategories();
                } else {
                    $this->invalidateIndex();
                }
            }
        }

        return $this;
    }

    /**
     * Import products
     *
     * @param array       $data
     * @param string|null $behavior
     *
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function dryrunCategoryImport($data, $behavior = null)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }

        $this->setEntity(Mage_Catalog_Model_Category::ENTITY);

        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Entity_Category */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_category');
        $entityAdapter->setBehavior($this->getBehavior());
        $this->setEntityAdapter($entityAdapter);

        $validationResult = $this->validateSource($data);
        return $validationResult;
    }
    /**
     * Returns source adapter object.
     *
     * @param array $sourceData Array Source Data
     * @return AvS_FastSimpleImport_Model_ArrayAdapter
     */
    protected function _getSourceAdapter($sourceData)
    {
        if (is_array($sourceData)) {
            return Mage::getModel('fastsimpleimport/arrayAdapter', $sourceData);
        }

        return parent::_getSourceAdapter($sourceData);
    }

    /**
     * @param Mage_ImportExport_Model_Import_Entity_Abstract $entityAdapter
     * @return void
     */
    public function setEntityAdapter($entityAdapter)
    {
        $this->_entityAdapter = $entityAdapter;
    }

    /**
     * @return Mage_ImportExport_Model_Import_Entity_Abstract
     */
    public function getEntityAdapter()
    {
        return $this->_entityAdapter;
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
        foreach ($this->getErrors() as $type => $lines) {
            $message .= "\n:::: " . $type . " ::::\nIn Line(s) " . implode(", ", $lines) . "\n";
        }
        return $message;
    }

    /**
     * Get error messages which information in which rows the errors occured
     *
     * @return array
     */
    public function getErrorMessages()
    {
        return $this->getEntityAdapter()->getErrorMessages();
    }

    /**
     * Validates source file and returns validation result.
     *
     * @param array $sourceData Source Data
     * @return bool
     */
    public function validateSource($sourceData)
    {
        $result = $this->_getEntityAdapter()
            ->setArraySource($this->_getSourceAdapter($sourceData))
            ->isDataValid();

        return $result;
    }

    /**
     * Prepare Indexing of products which are to be deleted;
     * Preparing needed as products don't exist afterwards anymore
     *
     * @return AvS_FastSimpleImport_Model_Import
     */
    protected function _prepareDeletedProductsReindex()
    {
        $this->getEntityAdapter()->prepareDeletedProductsReindex();
        return $this;
    }

    /**
     * Partially reindex deleted, newly created and updated products
     * Method must be called seperately
     *
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function reindexImportedProducts()
    {
        $this->getEntityAdapter()->reindexImportedProducts();
        return $this;
    }

    /**
     * Set Attributes for which new Options should be created (dropdown only)
     *
     * @param string|array $attributeCodes
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function setDropdownAttributes($attributeCodes)
    {
        if (!is_array($attributeCodes)) {
            $attributeCodes = array($attributeCodes);
        }
        $this->setData('dropdown_attributes', $attributeCodes);
        return $this;
    }

    /**
     * Set Attributes for which new Options should be created (multiselect only)
     *
     * @param string|array $attributeCodes
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function setMultiselectAttributes($attributeCodes)
    {
        if (!is_array($attributeCodes)) {
            $attributeCodes = array($attributeCodes);
        }
        $this->setData('multiselect_attributes', $attributeCodes);
        return $this;
    }
}
