<?php
/**
 * AvS_FastSimpleImport
 *
 * Copyright (c) 2015 H&O E-commerce specialists B.V. (http://www.h-o.nl/)
 * H&O Commercial License (http://www.h-o.nl/license)
 *
 * Author: H&O E-commerce specialists B.V. <info@h-o.nl> */
?>
<?php
/* @var $installer Mage_Catalog_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();


if (!$installer->getAttribute(Mage_Catalog_Model_Category::ENTITY, 'external_id')) {
    $installer->addAttribute(Mage_Catalog_Model_Category::ENTITY, 'external_id', array(
        'label'                      => 'External ID',
        'group'                      => 'General Information',
        'sort_order'                 => 100,
        'type'                       => 'text',
        'note'                       => '',
        'default'                    => null,
        'input'                      => 'text',
        'required'                   => false,
        'user_defined'               => false,
        'unique'                     => false,
        'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'visible'                    => true,
        'visible_on_front'           => false,
    ));
}


$installer->endSetup();