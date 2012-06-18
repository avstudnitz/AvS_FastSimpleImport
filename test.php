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

$data = array();
for ($i = 1; $i <= 10; $i++) {

    $data[] = array(
        'sku' => $i,
    );
}

$time = microtime(true);
/** @var $import AvS_FastSimpleImport_Model_Import */
/*
$import = Mage::getModel('fastsimpleimport/import');
$import->setPartialIndexing(true);
$import->setBehavior(Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);
$import->processProductImport($data);
echo 'Elapsed time: ' . round(microtime(true) - $time, 2) . 's' . "\n";
*/

$data = array();
for ($i = 1; $i <= 10; $i++) {

    $randomString = getUniqueCode(20);
    $data[] = array(
        'sku' => 'test' . $i,
        '_type' => 'simple',
        '_attribute_set' => 'Default',
        '_product_websites' => 'base',
        // '_category' => rand(1, 3),
        'name' => $randomString,
        'price' => 0.99,
        'special_price' => 0.90,
        'cost' => 0.50,
        'description' => 'Default',
        'short_description' => 'Default',
        'meta_title' => 'Default',
        'meta_description' => 'Default',
        'meta_keywords' => 'Default',
        'weight' => 11,
        'status' => 1,
        'visibility' => 4,
        'tax_class_id' => 2,
        'qty' => 0,
        'is_in_stock' => 0,
        'enable_googlecheckout' => '1',
        'gift_message_available' => '0',
        'url_key' => strtolower($randomString),
    );
}

$time = microtime(true);
/** @var $import AvS_FastSimpleImport_Model_Import */
$import = Mage::getModel('fastsimpleimport/import');
$import->setPartialIndexing(true);
$import->setBehavior(Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);
$import->processProductImport($data);

echo 'Elapsed time: ' . round(microtime(true) - $time, 2) . 's' . "\n";
?>