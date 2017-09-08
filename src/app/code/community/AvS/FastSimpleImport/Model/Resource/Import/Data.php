<?php

/**
 * ImportExport import data resource model
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @author     Aoe Magento Team <team-magento@aoe.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @link       https://github.com/AOEpeople/AvS_FastSimpleImport
 */
class AvS_FastSimpleImport_Model_Resource_Import_Data extends Mage_ImportExport_Model_Resource_Import_Data {

    /**
     * Save import rows bunch.
     *
     * @param string $entity
     * @param string $behavior
     * @param array $data
     * @return int
     * @throws Exception
     */
    public function saveBunch($entity, $behavior, array $data)
    {
        $json = Mage::helper('core')->jsonEncode($data);
        if (false === $json) {
            $error = json_last_error_msg();
            throw new Exception(sprintf('Error encoding data for save: %s', $error));
        }
        return $this->_getWriteAdapter()->insert(
            $this->getMainTable(),
            array('behavior' => $behavior, 'entity' => $entity, 'data' => $json)
        );
    }
}
