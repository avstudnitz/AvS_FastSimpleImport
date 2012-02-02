Import products and customers into Magento, using the new (since Magento 1.5 CE / 1.10 EE, image import since 1.6 CE / 1.11 EE ) fast ImportExport module. This module allows to import from arrays and thus using any import source, while the Magento module only imports from files.

Call it like this: 

// Import product:
$data = array(
    array(
        'sku' => '1234567',
        '_type' => 'simple',
        '_attribute_set' => 'Default',
        '_product_websites' => 'base',
        'name' => 'Default',
        'price' => 0.99,
        'description' => 'Default',
        'short_description' => 'Default',
        'weight' => 0,
        'status' => 1,
        'visibility' => 4,
        'tax_class_id' => 2,
        'qty' => 76,
    ),
);
Mage::getSingleton('fastsimpleimport/import')
    ->processProductImport($data); 

// Import customer:
Mage::getSingleton('fastsimpleimport/import')
    ->processCustomerImport($data);


You can choose the import behavior like this:

// delete products
Mage::getSingleton('fastsimpleimport/import')
    ->processProductImport($data, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE); 


