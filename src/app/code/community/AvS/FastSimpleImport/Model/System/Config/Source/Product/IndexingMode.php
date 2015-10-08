<?php
/**
 * Indexer Mode Source Model
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
class AvS_FastSimpleImport_Model_System_Config_Source_Product_IndexingMode
{
    const INDEXING_MODE_NONE = 0;
    const INDEXING_MODE_PARTIAL = 1;
    const INDEXING_MODE_ASYNC = 2;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => self::INDEXING_MODE_NONE, 'label'=>Mage::helper('fastsimpleimport')->__('None')),
            array('value' => self::INDEXING_MODE_PARTIAL, 'label'=>Mage::helper('fastsimpleimport')->__('Partial Indexing')),
            array('value' => self::INDEXING_MODE_ASYNC, 'label'=>Mage::helper('fastsimpleimport')->__('Asyncronous Indexing')),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            self::INDEXING_MODE_NONE => Mage::helper('fastsimpleimport')->__('None'),
            self::INDEXING_MODE_PARTIAL => Mage::helper('fastsimpleimport')->__('Partial Indexing'),
            self::INDEXING_MODE_PARTIAL => Mage::helper('fastsimpleimport')->__('Asyncronous Indexing'),
        );
    }

}
