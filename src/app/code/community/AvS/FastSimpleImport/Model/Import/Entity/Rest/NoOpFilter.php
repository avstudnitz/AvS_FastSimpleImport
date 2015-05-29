<?php

/**
 * Api2 ACL filter skipping input attributes data filtering
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Gabriel Féron <feron.gabriel@gmail.com>
 */
class AvS_FastSimpleImport_Model_Import_Entity_Rest_NoOpFilter extends Mage_Api2_Model_Acl_Filter
{
    /**
     * Return all the data without any kind of filtering
     *
     * @param array $allowedAttributes List of attributes available to use
     * @param array $data Associative array attribute to value
     * @return array
     */
    protected function _filter(array $allowedAttributes, array $data)
    {
        return $data;
    }
}