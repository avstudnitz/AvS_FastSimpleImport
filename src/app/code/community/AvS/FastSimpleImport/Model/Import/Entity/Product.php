<?php

/**
 * Entity Adapter for importing Magento Products
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
class AvS_FastSimpleImport_Model_Import_Entity_Product extends Mage_ImportExport_Model_Import_Entity_Product
{
    /** @var array */
    protected $_dropdownAttributes = array();

    /** @var array */
    protected $_imageAttributes = array();

    /** @var array */
    protected $_multiselectAttributes = array();

    /** @var array */
    protected $_attributeOptions = array();

    /** @var bool */
    protected $_allowRenameFiles = false;

    /** @var bool */
    protected $_isDryRun = false;

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

    public function setAllowRenameFiles($allow)
    {
        $this->_allowRenameFiles = (boolean) $allow;
    }

    public function getAllowRenameFiles()
    {
        return $this->_allowRenameFiles;
    }


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

            if (! $this->getAllowRenameFiles())
            {
                $this->_getUploader()->setAllowRenameFiles(false);
            }
        }

        return parent::validateData();
    }

    /**
     *
     */
    protected function _createAttributeOptions()
    {
        $this->_createDropdownAttributeOptions();
        $this->_createMultiselectAttributeOptions();
    }

    /**
     *
     */
    protected function _createDropdownAttributeOptions()
    {
        if (!sizeof($this->getDropdownAttributes()) || $this->getIsDryRun()) {
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

                if (!in_array(trim($rowData[$attributeCode]), $options, true)) {
                    $this->_createAttributeOption($attribute, trim($rowData[$attributeCode]));
                }
            }

            $this->_getSource()->next();
        }
    }

    /**
     *
     */
    protected function _createMultiselectAttributeOptions()
    {
        if (!sizeof($this->getMultiselectAttributes()) || $this->getIsDryRun()) {
            return;
        }

        $this->_getSource()->rewind();
        while ($this->_getSource()->valid()) {

            $rowData = $this->_getSource()->current();
            foreach ($this->getMultiselectAttributes() as $attribute) {

                /** @var $attribute Mage_Eav_Model_Entity_Attribute */
                $attributeCode = $attribute->getAttributeCode();
                if (!isset($rowData[$attributeCode]) || !strlen(trim($rowData[$attributeCode]))) {
                    continue;
                }

                $options = $this->_getAttributeOptions($attribute);

                if (!in_array(trim($rowData[$attributeCode]), $options, true)) {
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
            if ($attribute->getFrontendInput() == 'select') {
                /** @var $attributeOptions Mage_Eav_Model_Entity_Attribute_Source_Table */
                $attributeOptions = Mage::getModel('eav/entity_attribute_source_table');
                $attributeOptions->setAttribute($attribute);
            } else {
                $attributeOptions = $attribute->getSource();
            }

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
                if (!is_file($this->_getUploader()->getTmpDir() . DS . parse_url(basename($rowData['_media_image']),PHP_URL_PATH))) {
                    $this->_copyExternalImageFile($rowData['_media_image']);
                }
                $this->_getSource()->setValue('_media_image', parse_url(basename($rowData['_media_image']),PHP_URL_PATH));
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
            $fileName = parse_url(basename($url),PHP_URL_PATH);
            $fileHandle = fopen($dir . DS . $fileName, 'w+');
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
        $transportObject = new Varien_Object();
        Mage::dispatchEvent( 'avs_fastsimpleimport_entity_product_init_categories', array('transport' => $transportObject) );

        if ( $transportObject->getCategories() ) {
            $this->_categories = $transportObject->getCategories();
        } else {
            $collection = Mage::getResourceModel('catalog/category_collection')->addNameToResult();
            /* @var $collection Mage_Catalog_Model_Resource_Eav_Mysql4_Category_Collection */
            foreach ($collection as $category) {
                $structure = explode('/', $category->getPath());
                $pathSize = count($structure);
                if ($pathSize > 2) {
                    $path = array();
                    $this->_categories[implode('/', $path)] = $category->getId();
                    for ($i = 1; $i < $pathSize; $i++) {
                        $item = $collection->getItemById($structure[$i]);
                        if ($item instanceof Varien_Object) {
                            $path[] = $item->getName();
                        }
                    }

                    // additional options for category referencing: name starting from base category, or category id
                    $this->_categories[implode('/', $path)] = $category->getId();
                    array_shift($path);
                    $this->_categories[implode('/', $path)] = $category->getId();
                    $this->_categories[$category->getId()] = $category->getId();
                }
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

        $skus = $this->_getProcessedProductSkus();

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
     * SKUs of products which have been created, updated
     *
     * @return array
     */
    protected function _getProcessedProductSkus()
    {
        $skus = array();
        $source = $this->getSource();

        $source->rewind();
        while ($source->valid()) {
            $current = $source->current();
            $key = $source->key();

            if (! empty($current[self::COL_SKU]) && $this->_validatedRows[$key]) {
                $skus[] = $current[self::COL_SKU];
            }

            $source->next();
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
        $entityIds = $this->_getProcessedProductIds();

        /*
         * Generate a fake mass update event that we pass to our indexers.
         */
        $event = Mage::getModel('index/event');
        $event->setNewData(array(
            'reindex_price_product_ids' => &$entityIds, // for product_indexer_price
            'reindex_stock_product_ids' => &$entityIds, // for indexer_stock
            'product_ids'               => &$entityIds, // for category_indexer_product
            'reindex_eav_product_ids'   => &$entityIds  // for product_indexer_eav
        ));

        /*
         * Index our product entities.
         */
        try {
            Mage::dispatchEvent('fastsimpleimport_reindex_products_before_indexer_stock', array('entity_id' => &$entityIds));
            Mage::getResourceSingleton('cataloginventory/indexer_stock')->catalogProductMassAction($event);

            Mage::dispatchEvent('fastsimpleimport_reindex_products_before_product_indexer_price', array('entity_id' => &$entityIds));
            Mage::getResourceSingleton('catalog/product_indexer_price')->catalogProductMassAction($event);

            Mage::dispatchEvent('fastsimpleimport_reindex_products_before_category_indexer_product', array('entity_id' => &$entityIds));
            Mage::getResourceSingleton('catalog/category_indexer_product')->catalogProductMassAction($event);

            Mage::dispatchEvent('fastsimpleimport_reindex_products_before_product_indexer_eav', array('entity_id' => &$entityIds));
            Mage::getResourceSingleton('catalog/product_indexer_eav')->catalogProductMassAction($event);

            Mage::dispatchEvent('fastsimpleimport_reindex_products_before_fulltext', array('entity_id' => &$entityIds));
            Mage::getResourceSingleton('catalogsearch/fulltext')->rebuildIndex(null, $entityIds);

            if (Mage::getResourceModel('ecomdev_urlrewrite/indexer')) {
                Mage::dispatchEvent('fastsimpleimport_reindex_products_before_ecomdev_urlrewrite', array('entity_id' => &$entityIds));
                Mage::getResourceSingleton('ecomdev_urlrewrite/indexer')->updateProductRewrites($entityIds);
            } else {
                Mage::dispatchEvent('fastsimpleimport_reindex_products_before_urlrewrite', array('entity_id' => &$entityIds));
                /* @var $urlModel Mage_Catalog_Model_Url */
                $urlModel = Mage::getSingleton('catalog/url');

                $urlModel->clearStoreInvalidRewrites(); // Maybe some products were moved or removed from website
                foreach ($entityIds as $productId) {
                    $urlModel->refreshProductRewrite($productId);
                }
            }

            Mage::dispatchEvent('fastsimpleimport_reindex_products_before_flat', array('entity_id' => &$entityIds));
            Mage::getSingleton('catalog/product_flat_indexer')->saveProduct($entityIds);

            Mage::dispatchEvent('fastsimpleimport_reindex_products_after', array('entity_id' => &$entityIds));
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        return $this;
    }


    /**
     * Ids of products which have been created, updated or deleted
     *
     * @return array
     */
    protected function _getProcessedProductIds()
    {
        $productIds = array();
        $source = $this->getSource();

        $source->rewind();
        while ($source->valid()) {
            $current = $source->current();
            if (! empty($current['sku']) && isset($this->_oldSku[$current[self::COL_SKU]])) {
                $productIds[] = $this->_oldSku[$current[self::COL_SKU]]['entity_id'];
            } elseif (! empty($current['sku']) && isset($this->_newSku[$current[self::COL_SKU]])) {
                $productIds[] = $this->_newSku[$current[self::COL_SKU]]['entity_id'];
            }

            $source->next();
        }

        return $productIds;
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
        Mage::dispatchEvent('fastsimpleimport_reindex_products_delete_before');
        Mage::getSingleton('index/indexer')->indexEvents(
            Mage_CatalogInventory_Model_Stock_Item::ENTITY, Mage_Index_Model_Event::TYPE_DELETE
        );
        Mage::getSingleton('index/indexer')->indexEvents(
            Mage_Catalog_Model_Product::ENTITY, Mage_Index_Model_Event::TYPE_DELETE
        );
        Mage::dispatchEvent('fastsimpleimport_reindex_products_delete_after');
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
            if (!$attributeCode) {
                continue;
            }
            
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
     * Set and Validate Attributes for which new Options should be created (multiselect only)
     *
     * @param array $attributeCodes
     */
    public function setMultiselectAttributes($attributeCodes)
    {
        $attributes = array();
        foreach ($attributeCodes as $attributeCode) {
            if (!$attributeCode) {
                continue;
            }

            /** @var $attribute Mage_Eav_Model_Entity_Attribute */
            $attribute = Mage::getSingleton('catalog/product')->getResource()->getAttribute($attributeCode);
            if (!is_object($attribute)) {
                Mage::throwException('Attribute ' . $attributeCode . ' not found.');
            }
            if ($attribute->getBackendModel() != 'eav/entity_attribute_backend_array') {
                Mage::throwException('Attribute ' . $attributeCode . ' is no multiselect attribute.');
            }
            $attributes[$attributeCode] = $attribute;
        }

        $this->_multiselectAttributes = $attributes;
    }

    /**
     * Set _imageAttributes to allow importing other media_gallery fields as images beside _media_gallery, image,
     * small_image and thumbnail.
     * Automatically sets $this->_imagesArrayKeys that is used by parent class to read from
     * @param array $attributeCodes
     */
    public function setImageAttributes($attributeCodes)
    {
        $this->_imagesArrayKeys = $this->_imageAttributes = array_merge($this->_imagesArrayKeys, $attributeCodes);
    }

    /**
     * Get Attributes that should be handled as images
     * @return array
     */
    public function getImageAttributes()
    {
        return $this->_imageAttributes;
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
     * Get Attributes for which options will be created
     *
     * @return array
     */
    public function getMultiselectAttributes()
    {
        return $this->_multiselectAttributes;
    }


    /**
     * Set a flag if the current import is a dryrun
     *
     * @param bool $isDryrun
     * @return $this
     */
    public function setIsDryrun($isDryrun) {
        $this->_isDryRun = (bool) $isDryrun;
        return $this;
    }


    /**
     * Set a flag if the current import is a dryrun
     *
     * @return bool
     */
    public function getIsDryRun() {
        return $this->_isDryRun;
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
                $message = 'String is too long, only ' . self::DB_MAX_VARCHAR_LENGTH . ' characters allowed. Your input: ' . $rowData[$attrCode] . ', length: ' . strlen($val);
                break;
            case 'decimal':
                $val   = trim($rowData[$attrCode]);
                $valid = (float)$val == $val;
                $message = 'Decimal value expected. Your Input: '.$rowData[$attrCode];
                break;
            case 'select':
            case 'multiselect':
                $isAutocreate = isset($this->_dropdownAttributes[$attrCode]) || isset($this->_multiselectAttributes[$attrCode]);
                if ($this->getIsDryRun() && ($isAutocreate)) {
                	$valid = true; // Force validation in case of dry run with options of dropdown or multiselect which doesn't yet exist
                    break;
                }
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
                $message = 'Datetime value expected. Your Input: '.$rowData[$attrCode];
                break;
            case 'text':
                $val   = Mage::helper('core/string')->cleanString($rowData[$attrCode]);
                $valid = Mage::helper('core/string')->strlen($val) < self::DB_MAX_TEXT_LENGTH;
                $message = 'String is too long, only ' . self::DB_MAX_TEXT_LENGTH . ' characters allowed. Your input: ' . $rowData[$attrCode] . ', length: ' . strlen($val);
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
     * Stock item saving.
     * Overwritten in order to fix bug with stock data import
     * See http://www.magentocommerce.com/bug-tracking/issue/?issue=13539
     * See https://github.com/avstudnitz/AvS_FastSimpleImport/issues/3
     *
     * @return Mage_ImportExport_Model_Import_Entity_Product
     */
    protected function _saveStockItem()
    {
        $defaultStockData = array(
            'manage_stock'                  => 1,
            'use_config_manage_stock'       => 1,
            'qty'                           => 0,
            'min_qty'                       => 0,
            'use_config_min_qty'            => 1,
            'min_sale_qty'                  => 1,
            'use_config_min_sale_qty'       => 1,
            'max_sale_qty'                  => 10000,
            'use_config_max_sale_qty'       => 1,
            'is_qty_decimal'                => 0,
            'backorders'                    => 0,
            'use_config_backorders'         => 1,
            'notify_stock_qty'              => 1,
            'use_config_notify_stock_qty'   => 1,
            'enable_qty_increments'         => 0,
            'use_config_enable_qty_inc'     => 1,
            'qty_increments'                => 0,
            'use_config_qty_increments'     => 1,
            'is_in_stock'                   => 0,
            'low_stock_date'                => null,
            'stock_status_changed_auto'     => 0,
        );

        if (version_compare(Mage::getVersion(), '1.7.0.0', 'ge')) {
            $defaultStockData['is_decimal_divided'] = 0;
        }

        $entityTable = Mage::getResourceModel('cataloginventory/stock_item')->getMainTable();
        $helper      = Mage::helper('catalogInventory');
        $sku = null;

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $stockData = array();

            // Format bunch to stock data rows
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->isRowAllowedToImport($rowData, $rowNum)) {
                    continue;
                }

                if (self::SCOPE_DEFAULT == $this->getRowScope($rowData)) {
                    $sku = $rowData[self::COL_SKU];
                }

                //we have a non-SCOPE_DEFAULT row, we check if it has a stock_id, if not, skip it.
                if (self::SCOPE_DEFAULT != $this->getRowScope($rowData) && !isset($rowData['stock_id'])) {
                    continue;
                }

                $row['product_id'] = $this->_newSku[$sku]['entity_id'];
                $row['stock_id'] = isset($rowData['stock_id']) ? $rowData['stock_id'] : 1;

                /** @var $stockItem Mage_CatalogInventory_Model_Stock_Item */
                $stockItem = Mage::getModel('cataloginventory/stock_item');
                $stockItem->loadByProduct($row['product_id']);
                $existStockData = $stockItem->getData();

                $row = array_merge(
                    $row,
                    $defaultStockData,
                    array_intersect_key($existStockData, $defaultStockData),
                    array_intersect_key($rowData, $defaultStockData)
                );

                $stockItem->setData($row);

                if ($helper->isQty($this->_newSku[$sku]['type_id'])) {
                    if ($stockItem->verifyNotification()) {
                        $stockItem->setLowStockDate(Mage::app()->getLocale()
                                ->date(null, null, null, false)
                                ->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
                        );
                    }
                    $stockItem->setStockStatusChangedAutomatically((int) !$stockItem->verifyStock());
                } else {
                    $stockItem->setQty(0);
                }
                $stockData[] = $stockItem->unsetOldData()->getData();
            }

            // Insert rows
            if ($stockData) {
                $this->_connection->insertOnDuplicate($entityTable, $stockData);
            }
        }
        return $this;
    }

    /**
     * Returns an object for upload a media files
     */
    protected function _getUploader()
    {
        if (is_null($this->_fileUploader)) {
            $this->_fileUploader    = new Mage_ImportExport_Model_Import_Uploader();

            $this->_fileUploader->init();

            $tmpDir     = Mage::getConfig()->getOptions()->getMediaDir() . '/import';
            $destDir    = Mage::getConfig()->getOptions()->getMediaDir() . '/catalog/product';
            if (!is_writable($destDir)) {
                @mkdir($destDir, 0777, true);
            }
            // diglin - add auto creation in case folder doesn't exist
            if (!file_exists($tmpDir)) {
                @mkdir($tmpDir, 0777, true);
            }
            if (!$this->_fileUploader->setTmpDir($tmpDir)) {
                Mage::throwException("File directory '{$tmpDir}' is not readable.");
            }
            if (!$this->_fileUploader->setDestDir($destDir)) {
                Mage::throwException("File directory '{$destDir}' is not writable.");
            }
        }
        return $this->_fileUploader;
    }

    /**
     * Removes empty keys in case value is null or empty string
     *
     * @param array $rowData
     */
    protected function _filterRowData(&$rowData)
    {
        $rowData = array_filter($rowData, 'strlen');
        if (!isset($rowData[self::COL_SKU])) {
            $rowData[self::COL_SKU] = null;
        }
        if (!isset($rowData[self::COL_ATTR_SET])) {
            $rowData[self::COL_ATTR_SET] = null;
        }
    }

    /**
     * Save product media gallery.
     * Overwritten in order to provide default value for media_attribute_id
     *
     * @param array $mediaGalleryData
     * @return Mage_ImportExport_Model_Import_Entity_Product
     */
    protected function _saveMediaGallery(array $mediaGalleryData)
    {
        if (empty($mediaGalleryData)) {
            return $this;
        }

        static $mediaGalleryTableName = null;
        static $mediaValueTableName = null;
        static $productId = null;

        if (!$mediaGalleryTableName) {
            $mediaGalleryTableName = Mage::getModel('importexport/import_proxy_product_resource')
                ->getTable('catalog/product_attribute_media_gallery');
        }

        if (!$mediaValueTableName) {
            $mediaValueTableName = Mage::getModel('importexport/import_proxy_product_resource')
                ->getTable('catalog/product_attribute_media_gallery_value');
        }

        foreach ($mediaGalleryData as $productSku => $mediaGalleryRows) {
            $productId = $this->_newSku[$productSku]['entity_id'];
            $insertedGalleryImgs = array();

            if (Mage_ImportExport_Model_Import::BEHAVIOR_APPEND != $this->getBehavior()) {
                $this->_connection->delete(
                    $mediaGalleryTableName,
                    $this->_connection->quoteInto('entity_id IN (?)', $productId)
                );
            }

            foreach ($mediaGalleryRows as $insertValue) {

                if (!in_array($insertValue['value'], $insertedGalleryImgs)) {
                    if (!isset($insertValue['attribute_id']) || !$insertValue['attribute_id']) {
                        $insertValue['attribute_id'] = Mage::getSingleton('catalog/product')
                            ->getResource()
                            ->getAttribute('media_gallery')
                            ->getAttributeId();
                    }

                    $valueArr = array(
                        'attribute_id' => $insertValue['attribute_id'],
                        'entity_id'    => $productId,
                        'value'        => $insertValue['value']
                    );

                    $this->_connection
                        ->insertOnDuplicate($mediaGalleryTableName, $valueArr, array('entity_id'));

                    $insertedGalleryImgs[] = $insertValue['value'];
                }

                $newMediaValues = $this->_connection->fetchPairs($this->_connection->select()
                        ->from($mediaGalleryTableName, array('value', 'value_id'))
                        ->where('entity_id IN (?)', $productId)
                );

                if (array_key_exists($insertValue['value'], $newMediaValues)) {
                    $insertValue['value_id'] = $newMediaValues[$insertValue['value']];
                }

                $valueArr = array(
                    'value_id' => $insertValue['value_id'],
                    'store_id' => Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID,
                    'label'    => $insertValue['label'],
                    'position' => $insertValue['position'],
		            'disabled' => isset($insertValue['disabled']) ? $insertValue['disabled'] : 0,
                );

                try {
                    $this->_connection
                        ->insertOnDuplicate($mediaValueTableName, $valueArr, array('value_id'));
                } catch (Exception $e) {
                    $this->_connection->delete(
                        $mediaGalleryTableName, $this->_connection->quoteInto('value_id IN (?)', $newMediaValues)
                    );
                }
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
        if (isset($rowData['fsi_line_number'])) {
            $rowNum = $rowData['fsi_line_number'];
        }
        
        return parent::validateRow($rowData, $rowNum);
    }    
}
