<?php

function getUniqueCode($length = "")
{
    $code = md5(uniqid(rand(), true));
    if ($length != "") return substr($code, 0, $length);
    else return $code;
}

require_once 'src/app/Mage.php';
umask(0);
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

ini_set('display_errors', 1);
ini_set('max_execution_time', 600);
$data = array(
    array(
        'email' => 'customer@company.com',
        '_website' => 'base',
        '_store' => 'default', // The storeviews code
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
        'email' => null,
        '_website' => null,
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

/** @var $import AvS_FastSimpleImport_Model_Import */
$import = Mage::getModel('fastsimpleimport/import');
try {
    $import->processCustomerImport($data);
} catch (Exception $e) {
    print_r($import->getErrorMessages());
}