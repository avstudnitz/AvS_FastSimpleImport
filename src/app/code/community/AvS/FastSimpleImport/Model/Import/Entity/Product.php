<?php

/**
 * Entity Adapter for importing Magento Products
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
class AvS_FastSimpleImport_Model_Import_Entity_Product extends Mage_ImportExport_Model_Import_Entity_Product
{
    /** @var array */
    protected $_dropdownAttributes = array();

    /** @var array */
    protected $_attributeOptions = array();

    /**
     * Source model setter.
     *
     * @param array $source
     * @return AvS_FastSimpleImport_Model_Import_Entity_Product
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
     * Validate data.
     *
     * @throws Exception
     * @return Mage_ImportExport_Model_Import_Entity_Abstract
     */
    public function validateData()
    {
        if (!$this->_dataValidated) {
            $this->_createAttributeOptions();
            $this->_importExternalImageFiles();
            $this->_getUploader()->setAllowRenameFiles(false);
        }

        return parent::validateData();
    }

    /**
     *
     */
    protected function _createAttributeOptions()
    {
        if (!sizeof($this->getDropdownAttributes())) {
            return;
        }

        $this->_getSource()->rewind();
        while ($this->_getSource()->valid()) {

            $rowData = $this->_getSource()->current();
            foreach ($this->getDropdownAttributes() as $attribute) {

                /** @var $attribute Mage_Eav_Model_Entity_Attribute */
                $attributeCode = $attribute->getAttributeCode();
                if (!isset($rowData[$attributeCode]) || !strlen(trim($rowData[$attributeCode]))) {
                    continue;
                }

                $options = $this->_getAttributeOptions($attribute);

                if (!in_array(trim($rowData[$attributeCode]), $options)) {
                    $this->_createAttributeOption($attribute, trim($rowData[$attributeCode]));
                }
            }

            $this->_getSource()->next();
        }
    }

    /**
     * Get all options of a dropdown attribute
     *
     * @param Mage_Eav_Model_Entity_Attribute $attribute
     * @return array
     */
    protected function _getAttributeOptions($attribute)
    {
        if (!isset($this->_attributeOptions[$attribute->getAttributeCode()])) {

            /** @var $attributeOptions Mage_Eav_Model_Entity_Attribute_Source_Table */
            $attributeOptions = Mage::getModel('eav/entity_attribute_source_table');
            $attributeOptions->setAttribute($attribute);
            $this->_attributeOptions[$attribute->getAttributeCode()] = array();
            foreach ($attributeOptions->getAllOptions(false) as $option) {
                $this->_attributeOptions[$attribute->getAttributeCode()][$option['value']] = $option['label'];
            }
        }

        return $this->_attributeOptions[$attribute->getAttributeCode()];
    }

    /**
     * @param Mage_Eav_Model_Entity_Attribute $attribute
     * @param string $optionLabel
     */
    protected function _createAttributeOption($attribute, $optionLabel)
    {
        $option = array(
            'value' => array(
                array('0' => $optionLabel)
            ),
            'order' => array(0),
            'delete' => array('')
        );

        $attribute->setOption($option);

        $attribute->save();

        $this->_attributeOptions[$attribute->getAttributeCode()][] = $optionLabel;
        $this->_initTypeModels();
    }

    /**
     * Check field "_media_image" for http links to images; download them
     */
    protected function _importExternalImageFiles()
    {
        $this->_getSource()->rewind();
        while ($this->_getSource()->valid()) {

            $rowData = $this->_getSource()->current();
            if (
                isset($rowData['_media_image'])
                && strpos($rowData['_media_image'], 'http') === 0
                && strpos($rowData['_media_image'], '://') !== false
            ) {
                if (!is_file($this->_getUploader()->getTmpDir() . DS . basename($rowData['_media_image']))) {
                    $this->_copyExternalImageFile($rowData['_media_image']);
                }
                $this->_getSource()->setValue('_media_image', basename($rowData['_media_image']));
            }
            $this->_getSource()->next();
        }
    }

    /**
     * Download given file to ImportExport Tmp Dir (usually media/import)
     *
     * @param string $url
     */
    protected function _copyExternalImageFile($url)
    {
        try {
            $dir = $this->_getUploader()->getTmpDir();
            if (!is_dir($dir)) {
                mkdir($dir);
            }
            $fileHandle = fopen($dir . DS . basename($url), 'w+');
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 50);
            curl_setopt($ch, CURLOPT_FILE, $fileHandle);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);
            curl_close($ch);
            fclose($fileHandle);
        } catch (Exception $e) {
            Mage::throwException('Download of file ' . $url . ' failed: ' . $e->getMessage());
        }
    }

    /**
     * Initialize categories text-path to ID hash.
     *
     * @return Mage_ImportExport_Model_Import_Entity_Product
     */
    protected function _initCategories()
    {
        $collection = Mage::getResourceModel('catalog/category_collection')->addNameToResult();
        /* @var $collection Mage_Catalog_Model_Resource_Eav_Mysql4_Category_Collection */
        foreach ($collection as $category) {
            $structure = explode('/', $category->getPath());
            $pathSize = count($structure);
            if ($pathSize > 2) {
                $path = array();
                $this->_categories[implode('/', $path)] = $category->getId();
                for ($i = 1; $i < $pathSize; $i++) {
                    $path[] = $collection->getItemById($structure[$i])->getName();
                }

                // additional options for category referencing: name starting from base category, or category id
                $this->_categories[implode('/', $path)] = $category->getId();
                array_shift($path);
                $this->_categories[implode('/', $path)] = $category->getId();
                $this->_categories[$category->getId()] = $category->getId();
            }
        }
        return $this;
    }

    /**
     * Log Indexing Events before deleting products
     *
     * @return AvS_FastSimpleImport_Model_Import_Entity_Product
     */
    public function prepareDeletedProductsReindex()
    {
        if ($this->getBehavior() != Mage_ImportExport_Model_Import::BEHAVIOR_DELETE) {
            return $this;
        }

        $skus = $this->_getDeletedProductsSkus();

        $productCollection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('sku', array('in' => $skus));

        foreach ($productCollection as $product) {
            /** @var $product Mage_Catalog_Model_Product */

            $this->_logDeleteEvent($product);
        }

        return $this;
    }

    /**
     * Archive SKUs of products which are to be deleted
     *
     * @return array
     */
    protected function _getDeletedProductsSkus()
    {
        $skus = array();
        foreach ($this->_validatedRows as $rowIndex => $rowValidated) {
            if (!$rowValidated) {
                continue;
            }
            $this->getSource()->seek($rowIndex);
            $rowData = $this->getSource()->current();
            $skus[] = (string)$rowData['sku'];
        }
        return $skus;
    }

    /**
     * Partially reindex newly created and updated products
     *
     * @return AvS_FastSimpleImport_Model_Import_Entity_Product
     */
    public function reindexImportedProducts()
    {
        switch ($this->getBehavior()) {

            case Mage_ImportExport_Model_Import::BEHAVIOR_DELETE:

                $this->_indexDeleteEvents();
                break;
            case Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE:
            case Mage_ImportExport_Model_Import::BEHAVIOR_APPEND:

                $this->_reindexUpdatedProducts();
                break;
        }
    }

    /**
     * Partially reindex newly created and updated products
     *
     * @return AvS_FastSimpleImport_Model_Import_Entity_Product
     */
    protected function _reindexUpdatedProducts()
    {
        $skus = $this->_getUpdatedProductsSkus();
        $productCollection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('sku', array('in' => $skus));

        foreach ($productCollection as $product) {

            /** @var $product Mage_Catalog_Model_Product */
            $this->_logSaveEvent($product);
        }

        $this->_indexSaveEvents();

        return $this;
    }

    /**
     * Archive SKUs of products which have been created
     *
     * @return array
     */
    protected function _getUpdatedProductsSkus()
    {
        $skus = array();
        foreach ($this->_validatedRows as $rowIndex => $rowValidated) {
            if (!$rowValidated) {
                continue;
            }
            $this->getSource()->seek($rowIndex);
            $rowData = $this->getSource()->current();
            $skus[] = (string)$rowData['sku'];
        }
        return $skus;
    }

    /**
     * Log save index events for product and its stock item
     *
     * @param Mage_Catalog_Model_Product $product
     */
    protected function _logSaveEvent($product)
    {
        /** @var $stockItem Mage_CatalogInventory_Model_Stock_Item */
        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
        $stockItem->setForceReindexRequired(true);

        Mage::getSingleton('index/indexer')->logEvent(
            $stockItem,
            Mage_CatalogInventory_Model_Stock_Item::ENTITY,
            Mage_Index_Model_Event::TYPE_SAVE
        );

        $product
            ->setForceReindexRequired(true)
            ->setIsChangedCategories(true);

        Mage::getSingleton('index/indexer')->logEvent(
            $product,
            Mage_Catalog_Model_Product::ENTITY,
            Mage_Index_Model_Event::TYPE_SAVE
        );
    }

    /**
     * Fulfill indexing for product save events
     */
    protected function _indexSaveEvents()
    {
        Mage::getSingleton('index/indexer')->indexEvents(
            Mage_CatalogInventory_Model_Stock_Item::ENTITY,
            Mage_Index_Model_Event::TYPE_SAVE
        );

        Mage::getSingleton('index/indexer')->indexEvents(
            Mage_Catalog_Model_Product::ENTITY,
            Mage_Index_Model_Event::TYPE_SAVE
        );
    }

    /**
     * Log delete index events for product
     *
     * @param Mage_Catalog_Model_Product $product
     */
    protected function _logDeleteEvent($product)
    {
        /** @var $stockItem Mage_CatalogInventory_Model_Stock_Item */
        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
        $stockItem->setForceReindexRequired(true);

        Mage::getSingleton('index/indexer')->logEvent(
            $stockItem,
            Mage_CatalogInventory_Model_Stock_Item::ENTITY,
            Mage_Index_Model_Event::TYPE_DELETE
        );

        Mage::getSingleton('index/indexer')->logEvent(
            $product,
            Mage_Catalog_Model_Product::ENTITY,
            Mage_Index_Model_Event::TYPE_DELETE
        );
    }

    /**
     * Perform reindexing of deleted products after deletion;
     * Events have been logged before
     *
     * @return AvS_FastSimpleImport_Model_Import_Entity_Product
     */
    protected function _indexDeleteEvents()
    {
        Mage::getSingleton('index/indexer')->indexEvents(
            Mage_CatalogInventory_Model_Stock_Item::ENTITY, Mage_Index_Model_Event::TYPE_DELETE
        );
        Mage::getSingleton('index/indexer')->indexEvents(
            Mage_Catalog_Model_Product::ENTITY, Mage_Index_Model_Event::TYPE_DELETE
        );
    }

    /**
     * Set and Validate Attributes for which new Options should be created (dropdown only)
     *
     * @param array $attributeCodes
     */
    public function setDropdownAttributes($attributeCodes)
    {
        $attributes = array();
        foreach ($attributeCodes as $attributeCode) {
            /** @var $attribute Mage_Eav_Model_Entity_Attribute */
            $attribute = Mage::getSingleton('catalog/product')->getResource()->getAttribute($attributeCode);
            if (!is_object($attribute)) {
                Mage::throwException('Attribute ' . $attributeCode . ' not found.');
            }
            if ($attribute->getSourceModel() != 'eav/entity_attribute_source_table') {
                Mage::throwException('Attribute ' . $attributeCode . ' is no dropdown attribute.');
            }
            $attributes[$attributeCode] = $attribute;
        }

        $this->_dropdownAttributes = $attributes;
    }

    /**
     * Get Attributes for which options will be created
     *
     * @return array
     */
    public function getDropdownAttributes()
    {
        return $this->_dropdownAttributes;
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
            case 'multiselect':
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
}