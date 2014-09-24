<?php

require_once 'abstract.php';

/**
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
class AvS_FastSimpleImport_Shell_Import extends Mage_Shell_Abstract
{
    /**
     * Run script
     *
     */
    public function run()
    {
        if ($importTypeCode = $this->getArg('type')) {

            try {
                $importMethods = $this->_getImportMethods();

                if (!isset($importMethods[$importTypeCode])) {
                    Mage::throwException('Please give a valid import type code: product, category, customer or category_product.');
                    return;
                }

                //initialize the translations so that we are able to translate things.
                Mage::app()->loadAreaPart(
                    Mage_Core_Model_App_Area::AREA_ADMINHTML,
                    Mage_Core_Model_App_Area::PART_TRANSLATE
                );

                $importMethod = $importMethods[$importTypeCode];

                $data = $this->_getImportArray();

                /** @var $import AvS_FastSimpleImport_Model_Import */
                $import = Mage::getModel('fastsimpleimport/import');
                $import->$importMethod($data);

                echo $import->getEntityAdapter()->getProcessedRowsCount() . ' rows with ' . $import->getEntityAdapter()->getProcessedEntitiesCount() . ' entities have been imported successfully.' . "\n";

            } catch (Exception $e) {
                echo 'Error: ' . $e->getMessage() . "\n";
            }

        } else {
            echo $this->usageHelp();
        }
    }

    /**
     * @return array All available import type codes (keys) with according methods (values)
     */
    protected function _getImportMethods()
    {
        return array(
            'product' => 'processProductImport',
            'category' => 'processCategoryImport',
            'customer' => 'processCustomerImport',
            'category_product' => 'processCategoryProductImport',
        );
    }

    /**
     * Read import csv file and transform to array format
     *
     * @return array
     */
    protected function _getImportArray()
    {
        $filename = $this->_getFilename();

        $fieldnames = array();
        $data = array();

        $handle = fopen($filename, 'r');

        while (!feof($handle)) {
            if (!sizeof($fieldnames)) {
                $fieldnames = fgetcsv($handle, null, ',', '"');
            } else {
                $lineData = fgetcsv($handle, null, ',', '"');
                $lineDataWithKeys = array();
                foreach($lineData as $key => $value) {
                    if (!isset($fieldnames[$key])) {
                        Mage::throwException('Data has more columns than the header.');
                    }
                    $lineDataWithKeys[$fieldnames[$key]] = $value;
                }
                $data[] = $lineDataWithKeys;
            }
        }

        fclose($handle);

        return $data;
    }

    /**
     * @return string
     */
    protected function _getFilename()
    {
        if (!($filename = $this->getArg('file'))) {

            $filename = Mage::getBaseDir('var') . DS . 'import' . DS . $this->getArg('type') . '.csv';
        }

        if (!is_file($filename)) {
            Mage::throwException('File "' . $filename . '" does not exist.');
        }

        if (!is_readable($filename)) {
            Mage::throwException('File "' . $filename . '" is not readable.');
        }

        if (!filesize($filename)) {
            Mage::throwException('File "' . $filename . '" seems to be empty.');
        }

        return $filename;
    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f import.php -- [options]
        php -f import.php -- --type product --file ../var/import/test.csv

  --type <code>                 Import type: product, category, customer or category_product
  --file <filename>             The relative or absolute filename
  help                          This help

USAGE;
    }
}

$shell = new AvS_FastSimpleImport_Shell_Import();
$shell->run();
