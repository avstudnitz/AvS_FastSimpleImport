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
    CONST COL_CATEGORY_POSITION = 'position';
    /** @var array */
    protected $_dropdownAttributes = array();

    /** @var array */
    protected $_attributeOptions = array();

    /** @var bool */
    protected $_allowRenameFiles = false;

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
                        $path[] = $collection->getItemById($structure[$i])->getName();
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

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $stockData = array();

            // Format bunch to stock data rows
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->isRowAllowedToImport($rowData, $rowNum)) {
                    continue;
                }
                // only SCOPE_DEFAULT can contain stock data
                if (self::SCOPE_DEFAULT != $this->getRowScope($rowData)) {
                    continue;
                }

                $row['product_id'] = $this->_newSku[$rowData[self::COL_SKU]]['entity_id'];
                $row['stock_id'] = 1;

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

                if ($helper->isQty($this->_newSku[$rowData[self::COL_SKU]]['type_id'])) {
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
     * Gather and save information about product entities.
     * Overwritten to allow correct product positions within a category
     *
     * @return Mage_ImportExport_Model_Import_Entity_Product
     */
    protected function _saveProducts()
    {
        /** @var $resource Mage_ImportExport_Model_Import_Proxy_Product_Resource */
        $resource       = Mage::getModel('importexport/import_proxy_product_resource');
        $priceIsGlobal  = Mage::helper('catalog')->isPriceGlobal();
        $strftimeFormat = Varien_Date::convertZendToStrftime(Varien_Date::DATETIME_INTERNAL_FORMAT, true, true);
        $productLimit   = null;
        $productsQty    = null;

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityRowsIn = array();
            $entityRowsUp = array();
            $attributes   = array();
            $websites     = array();
            $categories   = array();
            $tierPrices   = array();
            $groupPrices  = array();
            $mediaGallery = array();
            $uploadedGalleryFiles = array();
            $previousType = null;
            $previousAttributeSet = null;

            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }
                $rowScope = $this->getRowScope($rowData);

                if (self::SCOPE_DEFAULT == $rowScope) {
                    $rowSku = $rowData[self::COL_SKU];

                    // 1. Entity phase
                    if (isset($this->_oldSku[$rowSku])) { // existing row
                        $entityRowsUp[] = array(
                            'updated_at' => now(),
                            'entity_id'  => $this->_oldSku[$rowSku]['entity_id']
                        );
                    } else { // new row
                        if (!$productLimit || $productsQty < $productLimit) {
                            $entityRowsIn[$rowSku] = array(
                                'entity_type_id'   => $this->_entityTypeId,
                                'attribute_set_id' => $this->_newSku[$rowSku]['attr_set_id'],
                                'type_id'          => $this->_newSku[$rowSku]['type_id'],
                                'sku'              => $rowSku,
                                'created_at'       => now(),
                                'updated_at'       => now()
                            );
                            $productsQty++;
                        } else {
                            $rowSku = null; // sign for child rows to be skipped
                            $this->_rowsToSkip[$rowNum] = true;
                            continue;
                        }
                    }
                } elseif (null === $rowSku) {
                    $this->_rowsToSkip[$rowNum] = true;
                    continue; // skip rows when SKU is NULL
                } elseif (self::SCOPE_STORE == $rowScope) { // set necessary data from SCOPE_DEFAULT row
                    $rowData[self::COL_TYPE]     = $this->_newSku[$rowSku]['type_id'];
                    $rowData['attribute_set_id'] = $this->_newSku[$rowSku]['attr_set_id'];
                    $rowData[self::COL_ATTR_SET] = $this->_newSku[$rowSku]['attr_set_code'];
                }
                if (!empty($rowData['_product_websites'])) { // 2. Product-to-Website phase
                    $websites[$rowSku][$this->_websiteCodeToId[$rowData['_product_websites']]] = true;
                }

                // 3. Categories phase
                // nhp: added +1 to the actual position to avoid problems with true/false casting if they
                // occur at any point (not found any)
                $categoryPath = empty($rowData[self::COL_CATEGORY]) ? '' : $rowData[self::COL_CATEGORY];
                if (!empty($rowData[self::COL_ROOT_CATEGORY])) {
                    $categoryId = $this->_categoriesWithRoots[$rowData[self::COL_ROOT_CATEGORY]][$categoryPath];
                    $categories[$rowSku][$categoryId] = true;
                } elseif (!empty($categoryPath)) {
                    $categories[$rowSku][$this->_categories[$categoryPath]] = $rowData[self::COL_CATEGORY_POSITION] + 1;
                }

                if (!empty($rowData['_tier_price_website'])) { // 4.1. Tier prices phase
                    $tierPrices[$rowSku][] = array(
                        'all_groups'        => $rowData['_tier_price_customer_group'] == self::VALUE_ALL,
                        'customer_group_id' => ($rowData['_tier_price_customer_group'] == self::VALUE_ALL)
                            ? 0 : $rowData['_tier_price_customer_group'],
                        'qty'               => $rowData['_tier_price_qty'],
                        'value'             => $rowData['_tier_price_price'],
                        'website_id'        => (self::VALUE_ALL == $rowData['_tier_price_website'] || $priceIsGlobal)
                            ? 0 : $this->_websiteCodeToId[$rowData['_tier_price_website']]
                    );
                }
                if (!empty($rowData['_group_price_website'])) { // 4.2. Group prices phase
                    $groupPrices[$rowSku][] = array(
                        'all_groups'        => $rowData['_group_price_customer_group'] == self::VALUE_ALL,
                        'customer_group_id' => ($rowData['_group_price_customer_group'] == self::VALUE_ALL)
                            ? 0 : $rowData['_group_price_customer_group'],
                        'value'             => $rowData['_group_price_price'],
                        'website_id'        => (self::VALUE_ALL == $rowData['_group_price_website'] || $priceIsGlobal)
                            ? 0 : $this->_websiteCodeToId[$rowData['_group_price_website']]
                    );
                }
                foreach ($this->_imagesArrayKeys as $imageCol) {
                    if (!empty($rowData[$imageCol])) { // 5. Media gallery phase
                        if (!array_key_exists($rowData[$imageCol], $uploadedGalleryFiles)) {
                            $uploadedGalleryFiles[$rowData[$imageCol]] = $this->_uploadMediaFiles($rowData[$imageCol]);
                        }
                        $rowData[$imageCol] = $uploadedGalleryFiles[$rowData[$imageCol]];
                    }
                }
                if (!empty($rowData['_media_image'])) {
                    $mediaGallery[$rowSku][] = array(
                        'attribute_id'      => $rowData['_media_attribute_id'],
                        'label'             => $rowData['_media_lable'],
                        'position'          => $rowData['_media_position'],
                        'disabled'          => $rowData['_media_is_disabled'],
                        'value'             => $rowData['_media_image']
                    );
                }
                // 6. Attributes phase
                $rowStore     = self::SCOPE_STORE == $rowScope ? $this->_storeCodeToId[$rowData[self::COL_STORE]] : 0;
                $productType  = $rowData[self::COL_TYPE];
                if(!is_null($rowData[self::COL_TYPE])) {
                    $previousType = $rowData[self::COL_TYPE];
                }
                if(!is_null($rowData[self::COL_ATTR_SET])) {
                    $previousAttributeSet = $rowData[Mage_ImportExport_Model_Import_Entity_Product::COL_ATTR_SET];
                }
                if (self::SCOPE_NULL == $rowScope) {
                    // for multiselect attributes only
                    if(!is_null($previousAttributeSet)) {
                        $rowData[Mage_ImportExport_Model_Import_Entity_Product::COL_ATTR_SET] = $previousAttributeSet;
                    }
                    if(is_null($productType) && !is_null($previousType)) {
                        $productType = $previousType;
                    }
                    if(is_null($productType)) {
                        continue;
                    }
                }
                $rowData      = $this->_productTypeModels[$productType]->prepareAttributesForSave($rowData);
                $product      = Mage::getModel('importexport/import_proxy_product', $rowData);

                foreach ($rowData as $attrCode => $attrValue) {
                    $attribute = $resource->getAttribute($attrCode);
                    if('multiselect' != $attribute->getFrontendInput()
                        && self::SCOPE_NULL == $rowScope) {
                        continue; // skip attribute processing for SCOPE_NULL rows
                    }
                    $attrId    = $attribute->getId();
                    $backModel = $attribute->getBackendModel();
                    $attrTable = $attribute->getBackend()->getTable();
                    $storeIds  = array(0);

                    if ('datetime' == $attribute->getBackendType() && strtotime($attrValue)) {
                        $attrValue = gmstrftime($strftimeFormat, strtotime($attrValue));
                    } elseif ($backModel) {
                        $attribute->getBackend()->beforeSave($product);
                        $attrValue = $product->getData($attribute->getAttributeCode());
                    }
                    if (self::SCOPE_STORE == $rowScope) {
                        if (self::SCOPE_WEBSITE == $attribute->getIsGlobal()) {
                            // check website defaults already set
                            if (!isset($attributes[$attrTable][$rowSku][$attrId][$rowStore])) {
                                $storeIds = $this->_storeIdToWebsiteStoreIds[$rowStore];
                            }
                        } elseif (self::SCOPE_STORE == $attribute->getIsGlobal()) {
                            $storeIds = array($rowStore);
                        }
                    }
                    foreach ($storeIds as $storeId) {
                        if('multiselect' == $attribute->getFrontendInput()) {
                            if(!isset($attributes[$attrTable][$rowSku][$attrId][$storeId])) {
                                $attributes[$attrTable][$rowSku][$attrId][$storeId] = '';
                            } else {
                                $attributes[$attrTable][$rowSku][$attrId][$storeId] .= ',';
                            }
                            $attributes[$attrTable][$rowSku][$attrId][$storeId] .= $attrValue;
                        } else {
                            $attributes[$attrTable][$rowSku][$attrId][$storeId] = $attrValue;
                        }
                    }
                    $attribute->setBackendModel($backModel); // restore 'backend_model' to avoid 'default' setting
                }
            }
            $this->_saveProductEntity($entityRowsIn, $entityRowsUp)
                ->_saveProductWebsites($websites)
                ->_saveProductCategories($categories)
                ->_saveProductTierPrices($tierPrices)
                ->_saveProductGroupPrices($groupPrices)
                ->_saveMediaGallery($mediaGallery)
                ->_saveProductAttributes($attributes);
        }
        return $this;
    }

      /**
     * Save product categories. Adapted to allow positioning within a category instead of always supplying 1 as position
     *
     * @param array $categoriesData
     * @return Mage_ImportExport_Model_Import_Entity_Product
     */
    protected function _saveProductCategories(array $categoriesData)
    {
        static $tableName = null;

        if (!$tableName) {
            $tableName = Mage::getModel('importexport/import_proxy_product_resource')->getProductCategoryTable();
        }
        if ($categoriesData) {
            $categoriesIn = array();
            $delProductId = array();

            foreach ($categoriesData as $delSku => $categories) {
                $productId      = $this->_newSku[$delSku]['entity_id'];
                $delProductId[] = $productId;

                foreach ($categories as $categoryId => $position) {
                    $categoriesIn[] = array('product_id' => $productId, 'category_id' => $categoryId, 'position' => ($position-1 > 0) ? $position - 1 : 1);
                }
            }
            if (Mage_ImportExport_Model_Import::BEHAVIOR_APPEND != $this->getBehavior()) {
                $this->_connection->delete(
                    $tableName,
                    $this->_connection->quoteInto('product_id IN (?)', $delProductId)
                );
            }
            if ($categoriesIn) {
                $this->_connection->insertOnDuplicate($tableName, $categoriesIn, array('position'));
            }
        }
        return $this;
    }
}