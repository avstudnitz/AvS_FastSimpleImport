<?php

/**
 * API2 for FastSimpleImport Product Import
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Gabriel Féron <feron.gabriel@gmail.com>
 */
class AvS_FastSimpleImport_Model_Import_Entity_Product_Rest_Admin_V1 extends Mage_Api2_Model_Resource
{
    public function __construct()
    {
        $this->_filter = new AvS_FastSimpleImport_Model_Import_Entity_Rest_NoOpFilter($this);
    }

    /**
     * Actual Product Import logic
     * @param $behavior
     * @param $data array products to import
     * @return array the created product entity IDs
     * @see http://avstudnitz.github.io/AvS_FastSimpleImport/products.html
     */
    protected function _runImport($behavior, $data)
    {
        try {
            Mage::getModel('fastsimpleimport/import')
                ->setBehavior($behavior)
                ->setUseNestedArrays(true)
                ->processProductImport($data);

            // Return the products of the last product we submitted
            $skus = array_map(function ($v) {
                return $v['sku'];
            }, $data);

            /** @var array $productIds */
            $productIds = Mage::getResourceModel('catalog/product_collection')
                ->addAttributeToFilter('sku', $skus)
                ->getColumnValues('entity_id');
            return $productIds;
        } catch (Exception $e) {
            $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
            return false;
        }
    }

    /**
     * Create single product
     *
     * @param array $data
     * @return string
     */
    public function _create($data)
    {
        $ids = $this->_runImport(Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE, array($data));
        return $ids[0];
    }

    /**
     * Update a single product
     *
     * @param array $data
     */
    public function _update($data)
    {
        $ids = $this->_runImport(Mage_ImportExport_Model_Import::BEHAVIOR_APPEND, array($data));
        return $ids[0];
    }

    /**
     * Create multiple products at once
     *
     * @param array $data
     */
    public function _multiCreate($data)
    {
        $ids = $this->_runImport(Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE, $data);
        $this->_successMessage(
            Mage_Api2_Model_Resource::RESOURCE_UPDATED_SUCCESSFUL,
            Mage_Api2_Model_Server::HTTP_OK,
            array('product_ids' => $ids)
        );
    }

    /**
     * Update multiple products at once
     *
     * @param array $data
     */
    public function _multiUpdate($data)
    {
        $ids = $this->_runImport(Mage_ImportExport_Model_Import::BEHAVIOR_APPEND, $data);
        $this->_successMessage(
            Mage_Api2_Model_Resource::RESOURCE_UPDATED_SUCCESSFUL,
            Mage_Api2_Model_Server::HTTP_OK,
            array('product_ids' => $ids)
        );
    }
}
