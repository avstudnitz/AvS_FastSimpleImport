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
 * @method AvS_FastSimpleImport_Model_Import setErrorLimit(boolean $value)
 * @method boolean getErrorLimit()
 */
class AvS_FastSimpleImport_Model_Import extends Mage_ImportExport_Model_Import
{
    protected function _construct()
    {
        $this->setBehavior(self::BEHAVIOR_REPLACE);
        $this->setPartialIndexing(FALSE);
        $this->setContinueAfterErrors(FALSE);
        $this->setDropdownAttributes(array());
        $this->setMultiselectAttributes(array());
        $this->setAllowRenameFiles(TRUE);
        $this->setImageAttributes(array());
    }

    /**
     * Import products
     *
     * @param array       $data
     * @param string|null $behavior
     *
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function processProductImport($data, $behavior = NULL)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }

        $this->setEntity(Mage_Catalog_Model_Product::ENTITY);
        $partialIndexing = $this->getPartialIndexing();

        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Entity_Product */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_product');
        $entityAdapter->setBehavior($this->getBehavior());
        $entityAdapter->setIsDryrun(FALSE);
        $entityAdapter->setErrorLimit($this->getErrorLimit());
        $entityAdapter->setDropdownAttributes($this->getDropdownAttributes());
        $entityAdapter->setMultiselectAttributes($this->getMultiselectAttributes());
        $entityAdapter->setImageAttributes($this->getImageAttributes());
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
                if (!empty($partialIndexing)) {

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
    public function dryrunProductImport($data, $behavior = NULL)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }

        $this->setEntity(Mage_Catalog_Model_Product::ENTITY);

        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Entity_Product */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_product');
        $entityAdapter->setBehavior($this->getBehavior());
        $entityAdapter->setIsDryRun(TRUE);
        $entityAdapter->setErrorLimit($this->getErrorLimit());
        $entityAdapter->setDropdownAttributes($this->getDropdownAttributes());
        $entityAdapter->setMultiselectAttributes($this->getMultiselectAttributes());
        $this->setEntityAdapter($entityAdapter);

        $validationResult = $this->validateSource($data);
        return $validationResult;
    }

    /**
     * Import customers
     *
     * @param array  $data
     * @param string $behavior
     *
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function processCustomerImport($data, $behavior = NULL)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }

        $this->setEntity('customer');

        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Entity_Customer */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_customer');
        $entityAdapter->setBehavior($this->getBehavior());
        $entityAdapter->setIgnoreDuplicates($this->getIgnoreDuplicates());
        $entityAdapter->setErrorLimit($this->getErrorLimit());
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
     * @return bool
     */
    public function dryrunCustomerImport($data, $behavior = NULL)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }

        $this->setEntity('customer');

        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Entity_Customer */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_customer');
        $entityAdapter->setBehavior($this->getBehavior());
        $entityAdapter->setErrorLimit($this->getErrorLimit());
        $this->setEntityAdapter($entityAdapter);

        $validationResult = $this->validateSource($data);
        return $validationResult;
    }


    public function processAttributeOptionImport($data, $behavior = NULL)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }

        $partialIndexing = $this->getPartialIndexing();

        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Attribute_Option */
        $importAdapter = Mage::getModel('fastsimpleimport/import_attribute_option');
        $importAdapter->setBehavior($this->getBehavior());
        $importAdapter->setErrorLimit($this->getErrorLimit());
        $this->setImporter($importAdapter);
        $validationResult = $this->validateSource($data);
        if ($this->getImporter()->getProcessedRowsCount() > 0) {
            if (!$validationResult) {
                if ($importAdapter->getErrorsCount() >= $importAdapter->getErrorsLimit()) {
                    Mage::throwException(
                        sprintf("Error Limit of %s Errors reached, stopping import.", $importAdapter->getErrorsLimit())
                        . "\n" . $this->getImporter()->getErrorMessage()
                    );
                }

                if (!$this->getContinueAfterErrors()) {
                    Mage::throwException($this->getImporter()->getErrorMessage());
                }
            }

            if ($this->getImporter()->getProcessedRowsCount() > $this->getImporter()->getInvalidRowsCount()) {
                $this->getImporter()->importSource();

/*
                if (!empty($partialIndexing)) {
                    $this->getEntityAdapter()->reindexImportedCategories();
                } else {
                    $this->invalidateIndex();
                }
*/
            }
        }

        return $this;
    }

    /**
     * Import categories
     *
     * @param array  $data
     * @param string $behavior
     *
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function processCategoryImport($data, $behavior = NULL)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }

        $this->setEntity(Mage_Catalog_Model_Category::ENTITY);
        $partialIndexing = $this->getPartialIndexing();
        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Entity_Category */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_category');
        $entityAdapter->setBehavior($this->getBehavior());
        $entityAdapter->setErrorLimit($this->getErrorLimit());
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

                if (!empty($partialIndexing)) {
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
    public function dryrunCategoryImport($data, $behavior = NULL)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }

        $this->setEntity(Mage_Catalog_Model_Category::ENTITY);

        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Entity_Category */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_category');
        $entityAdapter->setBehavior($this->getBehavior());
        $entityAdapter->setErrorLimit($this->getErrorLimit());
        $this->setEntityAdapter($entityAdapter);

        $validationResult = $this->validateSource($data);
        return $validationResult;
    }

    /**
     * Import categories
     *
     * @param array  $data
     * @param string $behavior
     *
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function processCategoryProductImport($data, $behavior = NULL)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }
        $this->setEntity('category_product');
        $partialIndexing = $this->getPartialIndexing();

        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Entity_Category_Product */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_category_product');
        $entityAdapter->setBehavior($this->getBehavior());
        $entityAdapter->setErrorLimit($this->getErrorLimit());
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
                $this->importSource(); // this resets the internal previously set _data array :-( that's why $partialIndexing is needed
                if (!empty($partialIndexing)) {
                    $this->getEntityAdapter()->reindexImportedCategoryProduct();
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
    public function dryrunCategoryProductImport($data, $behavior = NULL)
    {
        if (!is_null($behavior)) {
            $this->setBehavior($behavior);
        }

        $this->setEntity('category_product');

        /** @var $entityAdapter AvS_FastSimpleImport_Model_Import_Entity_Category_Product */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_category_product');
        $entityAdapter->setBehavior($this->getBehavior());
        $entityAdapter->setErrorLimit($this->getErrorLimit());
        $this->setEntityAdapter($entityAdapter);
        $validationResult = $this->validateSource($data);
        return $validationResult;
    }

    /**
     * Returns source adapter object.
     *
     * @param array $sourceData Array Source Data
     *
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
     *
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

    public function getImporter()
    {
        return $this->_importer;
    }

    public function setImporter($importer)
    {
        $this->_importer = $importer;
    }
    /**
     * Validates source file and returns validation result.
     *
     * @param array $sourceData Source Data
     *
     * @return bool
     */
    public function validateSource($sourceData)
    {
        if ($this->getEntityAdapter()) {
            $result = $this->_getEntityAdapter()
                ->setArraySource($this->_getSourceAdapter($sourceData))
                ->isDataValid();
        } else {
            $result = $this->getImporter()
                ->setArraySource($this->_getSourceAdapter($sourceData))
                ->isDataValid();
        }
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
     *
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
     *
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

    /**
     * Set Attributes which will be handled as images
     *
     * @param string|array $attributeCodes
     *
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function setImageAttributes($attributeCodes)
    {
        if (!is_array($attributeCodes)) {
            $attributeCodes = array($attributeCodes);
            $attributes     = Mage::getResourceModel('catalog/product_attribute_collection')->addFieldToFilter('frontend_input', 'media_image');
            foreach ($attributes as $attribute) {
                $attributeCodes[] = $attribute->getAttributeCode();
            }
        }
        $this->setData('image_attributes', $attributeCodes);
        return $this;
    }

    /**
     * get dropdown attributes
     *
     * @return array
     */
    public function getDropdownAttributes()
    {
        return (array)$this->getData('dropdown_attributes');
    }

    /**
     * get multiselect attributes
     *
     * @return array
     */
    public function getMultiselectAttributes()
    {
        return (array)$this->getData('multiselect_attributes');
    }
}
