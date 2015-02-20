<?php

/**
 * Source Adapter for Arrays
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
class AvS_FastSimpleImport_Model_NestedArrayAdapter extends AvS_FastSimpleImport_Model_ArrayAdapter
{
    /**
     * Initialize data and position; transferm multi arrays if activated
     *
     * @param array $data
     */
    public function __construct($data)
    {
        $numberLines = sizeof($data);
        for ($lineNumber = 0; $lineNumber < $numberLines; $lineNumber++) {
            
            $line = $data[$lineNumber];
            
            $newLines = $this->_getNewLines($line);
            
            foreach($newLines as $newLine) {
                $newLine['fsi_line_number'] = $lineNumber;
                $this->_array[] = $newLine;
            }
            
            unset($data[$lineNumber]);
        }
                
        $this->_position = 0;
    }

    /**
     * Transform nested array to multi-line array (ImportExport format) 
     * 
     * @param array $line
     * @return array
     */
    protected function _getNewLines($line)
    {
        $newLines = array(
            0 => $line
        );
        
        foreach ($line as $fieldName => $fieldValue) {
            if (is_array($fieldValue)) {
                $newLineNumber = 0;
                foreach ($fieldValue as $singleFieldValue) {
                    if ($newLineNumber > 0) {
                        $newLines[$newLineNumber]['sku'] = null;
                        $newLines[$newLineNumber]['_type'] = null;
                        $newLines[$newLineNumber]['_attribute_set'] = null;

                        $originalLineHasStoreScope = isset($line['_store']);
                        if ($originalLineHasStoreScope) {
                            $newLines[$newLineNumber]['_store'] = $line['_store'];
                        }
                    }
                    $newLines[$newLineNumber++][$fieldName] = $singleFieldValue;
                }
            }
        }
        
        return $newLines;
    }
}
