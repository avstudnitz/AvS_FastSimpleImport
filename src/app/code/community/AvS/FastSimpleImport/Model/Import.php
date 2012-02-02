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
     * @return void
     */
    public function processProductImport($data)
    {
        $this->setEntity('catalog_product');
        $this->setEntityAdapter(Mage::getModel('fastsimpleimport/importEntity_product'));
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
     * @return void
     */
    public function processCustomerImport($data)
    {
        $this->setEntity('customer');
        $this->setEntityAdapter(Mage::getModel('fastsimpleimport/importEntity_customer'));
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

}