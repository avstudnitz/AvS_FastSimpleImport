<?php

/**
 * Import Main Model
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
class AvS_FastSimpleImport_Model_Import extends Mage_ImportExport_Model_Import
{
    /**
     * Import products
     *
     * @param array $data
     * @param string $behavior
     * @return void
     */
    public function processProductImport($data, $behavior = self::BEHAVIOR_REPLACE)
    {
        $this->setEntity('catalog_product');

        /** @var $entityAdapter AvS_FastSimpleImport_Model_ImportEntity_Product */
        $entityAdapter = Mage::getModel('fastsimpleimport/importEntity_product');
        $entityAdapter->setBehavior($behavior);
        $this->setEntityAdapter($entityAdapter);
        $validationResult = $this->validateSource($data);
        if ($this->getProcessedRowsCount() > 0) {
            if (!$validationResult) {
                $message = sprintf("Input Data contains %s corrupt records (from a total of %s)",
                                   $this->getInvalidRowsCount(), $this->getProcessedRowsCount()
                );
                foreach ($this->getErrors() as $type => $lines)
                {
                    $message .= "\n:::: " . $type . " ::::\nIn Line(s) " . implode(", ", $lines) . "\n";
                }
                Mage::throwException($message);
            }
            $this->importSource();
            $this->invalidateIndex();
        }
    }

    /**
     * Import customers
     *
     * @param array $data
     * @param string $behavior
     * @return void
     */
    public function processCustomerImport($data, $behavior = self::BEHAVIOR_REPLACE)
    {
        $this->setEntity('customer');

        /** @var $entityAdapter AvS_FastSimpleImport_Model_ImportEntity_Customer */
        $entityAdapter = Mage::getModel('fastsimpleimport/importEntity_customer');
        $entityAdapter->setBehavior($behavior);
        $this->setEntityAdapter($entityAdapter);
        $validationResult = $this->validateSource($data);
        if ($this->getProcessedRowsCount() > 0) {
            if (!$validationResult) {
                $message = sprintf("Input Data contains %s corrupt records (from a total of %s)",
                                   $this->getInvalidRowsCount(), $this->getProcessedRowsCount()
                );
                foreach ($this->getErrors() as $type => $lines)
                {
                    $message .= "\n:::: " . $type . " ::::\nIn Line(s) " . implode(", ", $lines) . "\n";
                }
                Mage::throwException($message);
            }
            $this->importSource();
        }
    }

    /**
     * Returns source adapter object.
     *
     * @param string $sourceData Array Source Data
     * @return AvS_FastSimpleImport_Model_ArrayAdapter
     */
    protected function _getSourceAdapter($sourceData)
    {
        return Mage::getModel('fastsimpleimport/arrayAdapter', $sourceData);
    }

    /**
     * @param Mage_ImportExport_Model_Import_Entity_Abstract $entityAdapter
     * @return void
     */
    protected function setEntityAdapter($entityAdapter)
    {
        $this->_entityAdapter = $entityAdapter;
    }

    /**
     * @return Mage_ImportExport_Model_Import_Entity_Abstract
     */
    protected function getEntityAdapter()
    {
        return $this->_entityAdapter;
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
     * Partially reindex newly created and updated products
     *
     * @todo update search index on new products
     * @todo ensure that  the Stock Option "Display Out of Stock Products" is set to "Yes".
     */
    public function reindexImportedProducts()
    {
        foreach($this->getEntityAdapter()->getNewSku() as $sku => $productData) {
            $productId = $productData['entity_id'];
            $product = Mage::getModel('catalog/product')->load($productId)
                ->setForceReindexRequired(true)
                ->setIsChangedCategories(true);

            Mage::getSingleton('index/indexer')->processEntityAction($product, Mage_Catalog_Model_Product::ENTITY, Mage_Index_Model_Event::TYPE_SAVE);
        }
    }
}