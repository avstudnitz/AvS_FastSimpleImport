<?php
/**
 * Abstract class for Product Entity Adapter whcih is used for switching between CE and EE
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
if (@class_exists('Enterprise_ImportExport_Model_Import_Entity_Product')) {
    abstract class AvS_FastSimpleImport_Model_Import_Entity_Product_Abstract extends Enterprise_ImportExport_Model_Import_Entity_Product {}
} else {
    abstract class AvS_FastSimpleImport_Model_Import_Entity_Product_Abstract extends Mage_ImportExport_Model_Import_Entity_Product {}
}

/**
 * Entity Adapter for importing Magento Products
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
class AvS_FastSimpleImport_Model_Import_Entity_Product extends AvS_FastSimpleImport_Model_Import_Entity_Product_Abstract
{
    /**
     * Code of a primary attribute which identifies the entity group if import contains of multiple rows
     *
     * @var string
     */
    protected $_masterAttributeCode = 'sku';

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

    /** @var bool */
    protected $_disablePreprocessImageData = false;

    /** @var bool */
    protected $_unsetEmptyFields = false;

    /** @var bool|string */
    protected $_symbolEmptyFields = false;

    /** @var bool|string */
    protected $_symbolIgnoreFields = false;

    /** @var bool */
    protected $_ignoreDuplicates = false;

    /**
     * Attributes with index (not label) value.
     *
     * @var array
     */
    protected $_indexValueAttributes = array(
        'status',
        'tax_class_id',
        'visibility',
        'enable_googlecheckout',
        'gift_message_available',
        'custom_design',
        'country_of_manufacture'
    );

    public function setIgnoreDuplicates($ignore)
    {
	$this->_ignoreDuplicates = (boolean) $ignore;
    }


    public function getIgnoreDuplicates()
    {
	return $this->_ignoreDuplicates;
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

    public function setAllowRenameFiles($allow)
    {
        $this->_allowRenameFiles = (boolean) $allow;
        return $this;
    }

    public function getAllowRenameFiles()
    {
        return $this->_allowRenameFiles;
    }


    /**
     * @return boolean
     */
    public function getDisablePreprocessImageData()
    {
        return $this->_disablePreprocessImageData;
    }


    /**
     * @param boolean $disablePreprocessImageData
     * @return $this
     */
    public function setDisablePreprocessImageData($disablePreprocessImageData)
    {
        $this->_disablePreprocessImageData = (boolean) $disablePreprocessImageData;
        return $this;
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
     * @param string $value
     * @return $this
     */
    public function setSymbolIgnoreFields($value) {
        $this->_symbolIgnoreFields = $value;
        return $this;
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
            $this->_preprocessImageData();

            if (!$this->getAllowRenameFiles()) {
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
            $this->_filterRowData($rowData);
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
            $this->_filterRowData($rowData);
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
            if (in_array($attribute->getFrontendInput(), array('select', 'multiselect'))) {
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
     * Autofill the fields "_media_attribute_id", "_media_is_disabled", "_media_position" and "_media_lable",
     * Check field "_media_image" for http links to images; download them
     */
    protected function _preprocessImageData()
    {
        if ($this->getDisablePreprocessImageData()) {
            return;
        }

        $mediaAttributeId = Mage::getSingleton('catalog/product')->getResource()->getAttribute('media_gallery')->getAttributeId();

        $this->_getSource()->rewind();
        while ($this->_getSource()->valid()) {

            $rowData = $this->_getSource()->current();
            if (isset($rowData['_media_image'])) {
                if (!isset($rowData['_media_attribute_id']) || !$rowData['_media_attribute_id']) {
                    $this->_getSource()->setValue('_media_attribute_id', $mediaAttributeId);
                }
                if (!isset($rowData['_media_is_disabled']) || !$rowData['_media_is_disabled']) {
                    $this->_getSource()->setValue('_media_is_disabled', 0);
                }
                if (!isset($rowData['_media_position']) || !$rowData['_media_position']) {
                    $this->_getSource()->setValue('_media_position', 0);
                }
                if (!isset($rowData['_media_lable'])) {
                    $this->_getSource()->setValue('_media_lable', '');
                }
                if (strpos($rowData['_media_image'], 'http') === 0 && strpos($rowData['_media_image'], '://') !== false) {

                    if (isset($rowData['_media_target_filename']) && $rowData['_media_target_filename']) {
                        $targetFilename = $rowData['_media_target_filename'];
                    } else {
                        $targetFilename = basename(parse_url($rowData['_media_image'], PHP_URL_PATH));
                    }

                    if (!is_file($this->_getUploader()->getTmpDir() . DS . $targetFilename)) {
                        $this->_copyExternalImageFile($rowData['_media_image'], $targetFilename);
                    }
                    $this->_getSource()->setValue('_media_image', $targetFilename);

                } else {

                    if (isset($rowData['_media_target_filename']) && $rowData['_media_target_filename']) {
                        $targetFilename = $rowData['_media_target_filename'];

                        if (!is_file($this->_getUploader()->getTmpDir() . DS . $targetFilename)) {
                            if (is_file($this->_getUploader()->getTmpDir() . DS . $rowData['_media_image'])) {
                                copy($this->_getUploader()->getTmpDir() . DS . $rowData['_media_image'], $this->_getUploader()->getTmpDir() . DS . $targetFilename);
                            }
                            $this->_getSource()->setValue('_media_image', $targetFilename);
                        }
                    }
                }

                $this->_getSource()->unsetValue('_media_target_filename');
            }

            $this->_getSource()->next();
        }
    }

    /**
     * Download given file to ImportExport Tmp Dir (usually media/import)
     *
     * @param string $url
     * @param string $targetFilename
     */
    protected function _copyExternalImageFile($url, $targetFilename)
    {
        try {
            $dir = $this->_getUploader()->getTmpDir();
            if (!is_dir($dir)) {
                mkdir($dir);
            }
            $fileHandle = fopen($dir . DS . $targetFilename, 'w+');
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
        if (Mage::helper('core')->isModuleEnabled('Enterprise_Index')) {
            Mage::dispatchEvent('fastsimpleimport_reindex_product_enterprise_before');
            Mage::getSingleton('enterprise_index/observer')->refreshIndex(Mage::getModel('cron/schedule'));
        } else {
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

            // Index our product entities.
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
            if (Mage::helper('catalog/category_flat')->isEnabled()) {
                Mage::dispatchEvent('fastsimpleimport_reindex_products_before_flat', array('entity_id' => &$entityIds));
                Mage::getSingleton('catalog/product_flat_indexer')->saveProduct($entityIds);
            }
            Mage::dispatchEvent('fastsimpleimport_reindex_products_after', array('entity_id' => &$entityIds));
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
	    if ($attribute === false) {
                continue;
            }
	    if (!($attribute->getSource() instanceof Mage_Eav_Model_Entity_Attribute_Source_Abstract)) {
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
	    if ($attribute === false) {
                continue;
            }
	    if (!($attribute->getBackend() instanceof Mage_Eav_Model_Entity_Attribute_Backend_Abstract)) {
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
        if (! is_array($attributeCodes)) {
            return;
        }
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
            //escape % for sprintf
            $message = str_replace('%','%%',$message);
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
     * Prepare attributes data
     *
     * @param array $rowData
     * @param int $rowScope
     * @param array $attributes
     * @param string|null $rowSku
     * @param int $rowStore
     * @return array
     */
    protected function _prepareAttributes($rowData, $rowScope, $attributes, $rowSku, $rowStore)
    {
        if (method_exists($this, '_prepareUrlKey')) {
            $rowData = $this->_prepareUrlKey($rowData, $rowScope, $rowSku);
        }

        $product = Mage::getModel('importexport/import_proxy_product', $rowData);

        foreach ($rowData as $attrCode => $attrValue) {
            $attribute = $this->_getAttribute($attrCode);
            if ('multiselect' != $attribute->getFrontendInput()
                && self::SCOPE_NULL == $rowScope
            ) {
                continue; // skip attribute processing for SCOPE_NULL rows
            }
            $attrId = $attribute->getId();
            $backModel = $attribute->getBackendModel();
            $attrTable = $attribute->getBackend()->getTable();
            $storeIds = array(0);

            if (!is_null($attrValue)) {
                if ('datetime' == $attribute->getBackendType() && strtotime($attrValue)) {
                    $attrValue = gmstrftime($this->_getStrftimeFormat(), strtotime($attrValue));
                } elseif ($backModel) {
                    $attribute->getBackend()->beforeSave($product);
                    $attrValue = $product->getData($attribute->getAttributeCode());
                }
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
                if ('multiselect' == $attribute->getFrontendInput()) {
                    if (!isset($attributes[$attrTable][$rowSku][$attrId][$storeId])) {
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
        return $attributes;
    }


    /**
     * Save product attributes.
     *
     * @param array $attributesData
     * @return Mage_ImportExport_Model_Import_Entity_Product
     */
    protected function _saveProductAttributes(array $attributesData)
    {
        foreach ($attributesData as $tableName => $skuData) {
            $tableData = array();

            foreach ($skuData as $sku => $attributes) {
                $productId = $this->_newSku[$sku]['entity_id'];

                foreach ($attributes as $attributeId => $storeValues) {
                    foreach ($storeValues as $storeId => $storeValue) {
                        // For storeId 0 we *must* save the NULL value into DB otherwise product collections can not load the store specific values
                        if ($storeId == 0 || ! is_null($storeValue)) {
                            $tableData[] = array(
                                'entity_id'      => $productId,
                                'entity_type_id' => $this->_entityTypeId,
                                'attribute_id'   => $attributeId,
                                'store_id'       => $storeId,
                                'value'          => $storeValue
                            );
                        } else {
                            /** @var Magento_Db_Adapter_Pdo_Mysql $connection */
                            $connection = $this->_connection;
                            $connection->delete($tableName, array(
                                'entity_id=?'      => (int) $productId,
                                'entity_type_id=?' => (int) $this->_entityTypeId,
                                'attribute_id=?'   => (int) $attributeId,
                                'store_id=?'       => (int) $storeId,
                            ));
                        }
                    }
                }
            }

            if (count($tableData)) {
                $this->_connection->insertOnDuplicate($tableName, $tableData, array('value'));
            }
        }
        return $this;
    }

    /**
     * Gather and save information about product entities.
     *
     * @return Mage_ImportExport_Model_Import_Entity_Product
     */
    protected function _saveProducts()
    {
        $priceIsGlobal  = Mage::helper('catalog')->isPriceGlobal();
        $productLimit   = null;
        $productsQty    = null;
        $rowSku         = null;

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
                $this->_filterRowData($rowData);
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
                $categoryPath = empty($rowData[self::COL_CATEGORY]) ? '' : $rowData[self::COL_CATEGORY];
                if (!empty($rowData[self::COL_ROOT_CATEGORY])) {
                    $categoryId = $this->_categoriesWithRoots[$rowData[self::COL_ROOT_CATEGORY]][$categoryPath];
                    $categories[$rowSku][$categoryId] = true;
                } elseif (!empty($categoryPath)) {
                    $categories[$rowSku][$this->_categories[$categoryPath]] = true;
                } elseif (array_key_exists(self::COL_CATEGORY, $rowData)) {
                    $categories[$rowSku] = array();
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
                if (is_array($this->_imagesArrayKeys)  && count($this->_imagesArrayKeys) > 0) {
                    foreach ($this->_imagesArrayKeys as $imageCol) {
                        if (!empty($rowData[$imageCol])) { // 5. Media gallery phase
                            if (!array_key_exists($rowData[$imageCol], $uploadedGalleryFiles)) {
                                $uploadedGalleryFiles[$rowData[$imageCol]] = $this->_uploadMediaFiles($rowData[$imageCol]);
                            }
                            $rowData[$imageCol] = $uploadedGalleryFiles[$rowData[$imageCol]];
                        }
                    }
                }
                if (!empty($rowData['_media_image'])) {
                    $mediaGallery[$rowSku][] = array(
                        'attribute_id'      => $rowData['_media_attribute_id'],
                        'label'             => isset($rowData['_media_lable']) ? $rowData['_media_lable'] : '',
                        'position'          => isset($rowData['_media_position']) ? $rowData['_media_position'] : 0,
                        'disabled'          => isset($rowData['_media_is_disabled']) ? $rowData['_media_is_disabled'] : 0,
                        'value'             => $rowData['_media_image']
                    );
                }
                // 6. Attributes phase
                $rowStore     = self::SCOPE_STORE == $rowScope ? $this->_storeCodeToId[$rowData[self::COL_STORE]] : 0;
                $productType  = isset($rowData[self::COL_TYPE]) ? $rowData[self::COL_TYPE] : null;
                if (!is_null($productType)) {
                    $previousType = $productType;
                }
                if (isset($rowData[self::COL_ATTR_SET]) && !is_null($rowData[self::COL_ATTR_SET])) {
                    $previousAttributeSet = $rowData[Mage_ImportExport_Model_Import_Entity_Product::COL_ATTR_SET];
                }
                if (self::SCOPE_NULL == $rowScope) {
                    // for multiselect attributes only
                    if (!is_null($previousAttributeSet)) {
                        $rowData[Mage_ImportExport_Model_Import_Entity_Product::COL_ATTR_SET] = $previousAttributeSet;
                    }
                    if (is_null($productType) && !is_null($previousType)) {
                        $productType = $previousType;
                    }
                    if (is_null($productType)) {
                        continue;
                    }
                }
                $rowData = $this->_productTypeModels[$productType]->prepareAttributesForSave(
                    $rowData,
                    !isset($this->_oldSku[$rowSku])
                );
                try {
                    $attributes = $this->_prepareAttributes($rowData, $rowScope, $attributes, $rowSku, $rowStore);
                } catch (Exception $e) {
                    Mage::logException($e);
                    continue;
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
        if (method_exists($this,'_fixUrlKeys')) { // > EE 1.13.1.0
            $this->_fixUrlKeys();
        }
        return $this;
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
                $this->_filterRowData($rowData);
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
            $this->_fileUploader->removeValidateCallback('catalog_product_image');

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
        if ($this->_unsetEmptyFields || $this->_symbolEmptyFields || $this->_symbolIgnoreFields) {
            foreach($rowData as $key => $fieldValue) {
                if ($this->_unsetEmptyFields && !strlen($fieldValue)) {
                    unset($rowData[$key]);
                } else if ($this->_symbolEmptyFields && trim($fieldValue) == $this->_symbolEmptyFields) {
                    $rowData[$key] = NULL;
                } else if ($this->_symbolIgnoreFields && trim($fieldValue) == $this->_symbolIgnoreFields) {
                    unset($rowData[$key]);
                }
            }
        }

        if (!isset($rowData[self::COL_SKU]) || $rowData[self::COL_SKU] === '') {
            $rowData[self::COL_SKU] = null;
        }

        if (!isset($rowData[self::COL_ATTR_SET]) || $rowData[self::COL_ATTR_SET] === '') {
            $rowData[self::COL_ATTR_SET] = null;
        }
    }

    /**
     * Uploading files into the "catalog/product" media folder.
     * Return a new file name if the same file is already exists.
     *
     * @see https://github.com/avstudnitz/AvS_FastSimpleImport/issues/109
     * In some cases the moving of files doesn't work because it is already
     * moved in a previous entity. We try and find the product in the destination folder.
     *
     * @param  string $fileName ex: /abc.jpg
     * @return string           ex: /a/b/abc.jpg
     */
    protected function _uploadMediaFiles($fileName)
    {
        try {
            $res = $this->_getUploader()->move($fileName);
            return $res['file'];
        } catch (Exception $e) {
            //added additional logging
            Mage::logException($e);

            //find new target
            $dispretionPath = Mage_ImportExport_Model_Import_Uploader::getDispretionPath(substr($fileName, 1));
            $destDir = $this->_getUploader()->getDestDir();

            if (file_exists($destDir.$dispretionPath.$fileName)) {
                return $dispretionPath.$fileName;
            }
            return '';
        }
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
        static $sku = null; // SKU is remembered through all product rows

        if (isset($rowData['fsi_line_number'])) {
            $rowNum = $rowData['fsi_line_number'];
        }

        $this->_filterRowData($rowData);

        if (isset($this->_validatedRows[$rowNum])) { // check that row is already validated
            return !isset($this->_invalidRows[$rowNum]);
        }
        $this->_validatedRows[$rowNum] = true;

        if (isset($this->_newSku[$rowData[self::COL_SKU]])) {
	    if($this->getIgnoreDuplicates()){
		return true;
	    }
            $this->addRowError(self::ERROR_DUPLICATE_SKU, $rowNum);
            return false;
        }
        $rowScope = $this->getRowScope($rowData);

        // BEHAVIOR_DELETE use specific validation logic
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            if (self::SCOPE_DEFAULT == $rowScope && !isset($this->_oldSku[$rowData[self::COL_SKU]])) {
                $this->addRowError(self::ERROR_SKU_NOT_FOUND_FOR_DELETE, $rowNum);
                return false;
            }
            return true;
        }

        $this->_validate($rowData, $rowNum, $sku);

        if (self::SCOPE_DEFAULT == $rowScope) { // SKU is specified, row is SCOPE_DEFAULT, new product block begins
            $this->_processedEntitiesCount ++;

            $sku = $rowData[self::COL_SKU];

            if (isset($this->_oldSku[$sku])) { // can we get all necessary data from existant DB product?
                // check for supported type of existing product
                if (isset($this->_productTypeModels[$this->_oldSku[$sku]['type_id']])) {
                    $this->_newSku[$sku] = array(
                        'entity_id'     => $this->_oldSku[$sku]['entity_id'],
                        'type_id'       => $this->_oldSku[$sku]['type_id'],
                        'attr_set_id'   => $this->_oldSku[$sku]['attr_set_id'],
                        'attr_set_code' => $this->_attrSetIdToName[$this->_oldSku[$sku]['attr_set_id']]
                    );
                } else {
                    $this->addRowError(self::ERROR_TYPE_UNSUPPORTED, $rowNum);
                    $sku = false; // child rows of legacy products with unsupported types are orphans
                }
            } else { // validate new product type and attribute set
                if (!isset($rowData[self::COL_TYPE])
                    || !isset($this->_productTypeModels[$rowData[self::COL_TYPE]])
                ) {
                    $this->addRowError(self::ERROR_INVALID_TYPE, $rowNum);
                } elseif (!isset($rowData[self::COL_ATTR_SET])
                    || !isset($this->_attrSetNameToId[$rowData[self::COL_ATTR_SET]])
                ) {
                    $this->addRowError(self::ERROR_INVALID_ATTR_SET, $rowNum);
                } elseif (!isset($this->_newSku[$sku])) {
                    $this->_newSku[$sku] = array(
                        'entity_id'     => null,
                        'type_id'       => $rowData[self::COL_TYPE],
                        'attr_set_id'   => $this->_attrSetNameToId[$rowData[self::COL_ATTR_SET]],
                        'attr_set_code' => $rowData[self::COL_ATTR_SET]
                    );
                }
                if (isset($this->_invalidRows[$rowNum])) {
                    // mark SCOPE_DEFAULT row as invalid for future child rows if product not in DB already
                    $sku = false;
                }
            }
        } else {
            if (null === $sku) {
                $this->addRowError(self::ERROR_SKU_IS_EMPTY, $rowNum);
            } elseif (false === $sku) {
                $this->addRowError(self::ERROR_ROW_IS_ORPHAN, $rowNum);
            } elseif (self::SCOPE_STORE == $rowScope && !isset($this->_storeCodeToId[$rowData[self::COL_STORE]])) {
                $this->addRowError(self::ERROR_INVALID_STORE, $rowNum);
            }
        }
        if (!isset($this->_invalidRows[$rowNum])) {
            // set attribute set code into row data for followed attribute validation in type model
            $rowData[self::COL_ATTR_SET] = $this->_newSku[$sku]['attr_set_code'];

            $rowAttributesValid = $this->_productTypeModels[$this->_newSku[$sku]['type_id']]->isRowValid(
                $rowData, $rowNum, !isset($this->_oldSku[$sku])
            );
            if (!$rowAttributesValid && self::SCOPE_DEFAULT == $rowScope && !isset($this->_oldSku[$sku])) {
                $sku = false; // mark SCOPE_DEFAULT row as invalid for future child rows if product not in DB already
            }
        }

        //additional check if there isn't an error with a row. Else child rows will be imported.
        if (isset($this->_invalidRows[$rowNum])) {
            $sku = false;
        }
        return !isset($this->_invalidRows[$rowNum]);
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

                if ($this->validateRow($rowData, $source->key()) && isset($entityGroup)) {
                    /* Add row to entity group */
                    $entityGroup[$source->key()] = $this->_prepareRowForDb($rowData);
                } elseif (isset($entityGroup)) {
                    /* In case validation of one line of the group fails kill the entire group */
                    unset($entityGroup);
                }
                $source->next();
            }
        }
        return $this;
    }


    /**
     * Common validation
     *
     * @param array $rowData
     * @param int $rowNum
     * @param string|false|null $sku
     */
    protected function _validate($rowData, $rowNum, $sku)
    {
        $this->_isProductWebsiteValid($rowData, $rowNum);
        $this->_isProductCategoryValid($rowData, $rowNum);
        $this->_isTierPriceValid($rowData, $rowNum);
        $this->_isGroupPriceValid($rowData, $rowNum);
        $this->_isSuperProductsSkuValid($rowData, $rowNum);

        if (method_exists($this, '_isUrlKeyValid')) {
            $this->_isUrlKeyValid($rowData, $rowNum, $sku);
        }
    }

    /**
     * Retrieve pattern for time formatting
     *
     * @return string
     */
    protected function _getStrftimeFormat()
    {
        return Varien_Date::convertZendToStrftime(Varien_Date::DATETIME_INTERNAL_FORMAT, true, true);
    }

    /**
     * Retrieve attribute by specified code
     *
     * @param string $code
     * @return Mage_Eav_Model_Entity_Attribute_Abstract
     */
    protected function _getAttribute($code)
    {
        $attribute = Mage::getSingleton('importexport/import_proxy_product_resource')->getAttribute($code);
        $backendModelName = (string)Mage::getConfig()->getNode(
            'global/importexport/import/catalog_product/attributes/' . $attribute->getAttributeCode() . '/backend_model'
        );
        if (!empty($backendModelName)) {
            $attribute->setBackendModel($backendModelName);
        }
        return $attribute;
    }


    /**
     * @param $sku
     * @return array|false
     */
    public function getEntityBySku($sku)
    {
        if (isset($this->_oldSku[$sku])) {
            return $this->_oldSku[$sku];
        }
        if (isset($this->_newSku[$sku])) {
            return $this->_newSku[$sku];
        }
        return false;
    }
}
