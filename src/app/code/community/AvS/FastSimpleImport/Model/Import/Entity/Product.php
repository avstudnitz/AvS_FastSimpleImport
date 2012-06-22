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
            $this->_importExternalImageFiles();
        }

        return parent::validateData();
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
                $this->_copyExternalImageFile($rowData['_media_image']);
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
        if ($this->getBehavior() != Mage_ImportExport_Model_Import::BEHAVIOR_DELETE) return $this;

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
            if (!$rowValidated) continue;
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
            if (!$rowValidated) continue;
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
     * Set Attributes for which new Options should be created (dropdown and multiselect only)
     *
     * @param array $attributeCodes
     */
    public function setDropdownAttributes($attributeCodes)
    {
        foreach($attributeCodes as $attributeCode) {
            /** @var $attribute Mage_Eav_Model_Entity_Attribute */
            $attribute = Mage::getSingleton('catalog/product')->getResource()->getAttribute($attributeCode);
            if (!is_object($attribute)) {
                Mage::throwException('Attribute ' . $attributeCode . ' not found.');
            }
        }
    }
}