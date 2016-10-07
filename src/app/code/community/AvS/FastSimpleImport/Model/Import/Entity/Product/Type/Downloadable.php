<?php

/**
 * Downloadable (based on Simple) Products Import Model
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */

/**
 * @property AvS_FastSimpleImport_Model_Import_Entity_Product $_entityModel
 */
class AvS_FastSimpleImport_Model_Import_Entity_Product_Type_Downloadable extends AvS_FastSimpleImport_Model_Import_Entity_Product_Type_Simple
{
    public function _initAttributes()
    {
        parent::_initAttributes();

        /*
         * links_purchased_separately does not live in an attribute set, so it is not picked up
         * by abstract _initAttributes method. We add it here manually.
         */
        $attribute = Mage::getResourceModel('catalog/eav_attribute')->load('links_purchased_separately', 'attribute_code');
        foreach ($this->_attributes as $attrSetName => $attributes) {
            $this->_addAttributeParams(
                $attrSetName,
                array(
                    'id'               => $attribute->getId(),
                    'code'             => $attribute->getAttributeCode(),
                    'for_configurable' => $attribute->getIsConfigurable(),
                    'is_global'        => $attribute->getIsGlobal(),
                    'is_required'      => $attribute->getIsRequired(),
                    'is_unique'        => $attribute->getIsUnique(),
                    'frontend_label'   => $attribute->getFrontendLabel(),
                    'is_static'        => $attribute->isStatic(),
                    'apply_to'         => $attribute->getApplyTo(),
                    'type'             => Mage_ImportExport_Model_Import::getAttributeType($attribute),
                    'default_value'    => strlen($attribute->getDefaultValue()) ? $attribute->getDefaultValue() : null,
                    'options'          => $this->_entityModel->getAttributeOptions($attribute, $this->_indexValueAttributes)
                )
            );
        }

        return $this;
    }
}