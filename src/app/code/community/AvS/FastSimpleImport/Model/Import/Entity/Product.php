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
     * @return AvS_FastSimpleImport_Model_ImportEntity_Product
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
            $pathSize  = count($structure);
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
     * Partially reindex newly created and updated products
     *
     * @todo handle deleted products
     * @return AvS_FastSimpleImport_Model_Import_Entity_Product
     */
    public function reindexUpdatedProducts()
    {
        $skus = array_keys($this->getNewSku());
        $productCollection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('sku', array('in' => $skus));

        foreach($productCollection as $product) {
            /** @var $product Mage_Catalog_Model_Product */

            /** @var $stockItem Mage_CatalogInventory_Model_Stock_Item */
            $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
            $stockItem->setForceReindexRequired(true);

            Mage::getSingleton('index/indexer')->processEntityAction($stockItem, Mage_CatalogInventory_Model_Stock_Item::ENTITY, Mage_Index_Model_Event::TYPE_SAVE);

            $product
                ->setForceReindexRequired(true)
                ->setIsChangedCategories(true);

            Mage::getSingleton('index/indexer')->processEntityAction($product, Mage_Catalog_Model_Product::ENTITY, Mage_Index_Model_Event::TYPE_SAVE);
        }

        return $this;
    }
}