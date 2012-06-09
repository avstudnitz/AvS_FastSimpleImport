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
     * @param string|null $behavior
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function processProductImport($data, $behavior = null)
    {
        if (is_null($behavior)) $behavior = self::BEHAVIOR_REPLACE;

        $this->setEntity(Mage_Catalog_Model_Product::ENTITY);

        /** @var $entityAdapter AvS_FastSimpleImport_Model_ImportEntity_Product */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_product');
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

        return $this;
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
        if (is_null($behavior)) $behavior = self::BEHAVIOR_REPLACE;

        $this->setEntity('customer');

        /** @var $entityAdapter AvS_FastSimpleImport_Model_ImportEntity_Customer */
        $entityAdapter = Mage::getModel('fastsimpleimport/import_entity_customer');
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

        return $this;
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

    public function prepareDeletedProductsReindex()
    {
        $skus = array();
        //foreach($this->getEntityAdapter()->getVali)
    }

    /**
     * Partially reindex newly created and updated products
     *
     * @todo handle deleted products
     * @return AvS_FastSimpleImport_Model_Import
     */
    public function reindexUpdatedProducts()
    {
        $this->getEntityAdapter()->reindexUpdatedProducts();
        return $this;
    }
}