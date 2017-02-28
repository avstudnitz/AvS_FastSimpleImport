<?php

/**
 * Escape Data Helper
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Volker Thiel <v.thiel@loewenstark.de>
 */

class AvS_FastSimpleImport_Helper_EscapeCsv extends Mage_Core_Helper_Abstract
{
    /**
     * @var Mage_Core_Helper_Data
     */
    protected $_coreHelper;
    public function __construct()
    {
        $this->_coreHelper = Mage::helper("core");
    }

    /**
     * Escaping CSV-data if core method exists
     *
     * Security enchancement for CSV data processing by Excel-like applications.
     * @see https://bugzilla.mozilla.org/show_bug.cgi?id=1054702
     *
     * @param $data
     * @return array
     */
    public function getEscapedCSVData(array $data)
    {
        $return = $data;
        if (method_exists($this->_coreHelper,'getEscapedCSVData')){
            $return = $this->_coreHelper->getEscapedCSVData($data);
        }
        return $return;
    }

    /**
     * UnEscapes CSV data if base method exists
     *
     * @param mixed $data
     * @return mixed array
     */
    public function unEscapeCSVData($data)
    {
        $return = $data;
        if (method_exists($this->_coreHelper,'unEscapeCSVData')){
            $return = $this->_coreHelper->getEscapedCSVData($data);
        }
        return $return;
    }
}
