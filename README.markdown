## FastSimpleImport - Array Adapter for Magento ImportExport

### Import products and customers into Magento, using the new fast ImportExport core module.

This module allows to import from arrays and thus using any import source, while the Magento module only imports from files.
ImportExport exists since Magento 1.5 CE / 1.10 EE, image import since 1.6 CE / 1.11 EE. Thus, this module needs at least one of those versions.

### Basic Usage

Call it like this:
```php
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
    // add more products here
);
Mage::getSingleton('fastsimpleimport/import')
    ->processProductImport($data); 

// Import customer:
$data = array(
    array(
		'email' => 'customer@company.com',
		'_website' => 'base',
        'group_id' => 1,
        'firstname' => 'John',
        'lastname' => 'Doe',
        '_address_firstname' => 'John',
        '_address_lastname' => 'Doe',
        '_address_street' => 'Main Street 1',
        '_address_postcode' => '12345',
        '_address_city' => 'Springfield',
        '_address_country_id' => 'US',
        '_address_telephone' => '+1 2345 6789',
        '_address_default_billing_' => 1,
        '_address_default_shipping_' => 0,
	),
    array(
        '_address_firstname' => 'John',
        '_address_lastname' => 'Doe',
        '_address_street' => 'Countryside 99',
        '_address_postcode' => '98765',
        '_address_city' => 'Cape Cod',
        '_address_country_id' => 'US',
        '_address_telephone' => '+1 9876 54321',
        '_address_default_billing_' => 0,
        '_address_default_shipping_' => 1,
	),
);
Mage::getSingleton('fastsimpleimport/import')
    ->processCustomerImport($data);
```

You can see the [test file](https://github.com/avstudnitz/AvS_FastSimpleImport/blob/master/test.php) for more examples.

See [specifications about the expected format](http://www.integer-net.de/download/ImportExport_EN.pdf).

### Features

* Import products and customers from php arrays (see above)
* Bugfix for ImportExport: default values were set on updates when the attribute was not given (only when a default value was present, i.e. with visibility)
* Choose Import Behavior: "Replace" (default), "Append" or "Delete" like this:

```php
Mage::getSingleton('fastsimpleimport/import')
    ->setBehavior(Mage_ImportExport_Model_Import::BEHAVIOR_DELETE)
    ->processProductImport($data);
```

* Activate Indexing of imported (or deleted) products only (Partial Indexing)

```php
Mage::getSingleton('fastsimpleimport/import')
    ->setPartialIndexing(true)
    ->processProductImport($data);
```

* Improved assigning of categories. In default, you can assign the category by giving the breadcrumb path below the root category, i.e. "Electronics/Cameras/Digital Cameras". Now, you can add the root category for uniqueness ("Root Catalog/Electronics/Cameras/Digital Cameras") or just the category id ("26").
* Download images with http. Just enter the URL in the field *_media_image*, while *image*, *small_image* und *thumbnail* get the filename only.
* **NEW:** Stop creating image duplicates (_1, _2, _3, etc.)

```php
Mage::getSingleton('fastsimpleimport/import')
    ->setAllowRenameFiles(false);
```

* Create options for predefined attributes automatically.

```php
Mage::getSingleton('fastsimpleimport/import')
    ->setDropdownAttributes('color')
    ->processProductImport($data);
```

or
```php
Mage::getSingleton('fastsimpleimport/import')
    ->setDropdownAttributes(array('manufacturer', 'color'))
    ->processProductImport($data);
```

* Continue import after error in parts of import data:

```php
Mage::getSingleton('fastsimpleimport/import')
    ->setContinueAfterErrors(true)
    ->processProductImport($data);
```

* **NEW:** Import categories:

```php
$data = array();
$data[] = array(
    '_root' => 'Default Category',
    '_category' => 'Test2',
    'name' => 'Test2',
    'description' => 'Test2',
    'is_active' => 'yes',
    'include_in_menu' => 'yes',
    'meta_description' => 'Meta Test',
    'available_sort_by' => 'position',
    'default_sort_by' => 'position',
);
$data[] = array(
    '_root' => 'Default Category',
    '_category' => 'Test2/Test3',
    'name' => 'TestTest',
    'description' => 'Test3',
    'is_active' => 'yes',
    'include_in_menu' => 'yes',
    'meta_description' => 'Meta Test',
    'available_sort_by' => 'position',
    'default_sort_by' => 'position',
);

/** @var $import AvS_FastSimpleImport_Model_Import */
$import = Mage::getModel('fastsimpleimport/import');
try {
    $import->processCategoryImport($data);
} catch (Exception $e) {
    print_r($import->getErrorMessages());
}
```