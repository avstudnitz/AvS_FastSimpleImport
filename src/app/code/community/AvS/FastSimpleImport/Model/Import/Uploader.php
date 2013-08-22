<?php
if (version_compare(Mage::getVersion(), '1.6.0.0', 'lt')) {
{
	// Uploader File from Magento 1.7.0.2 only as Fallback for Magento lt 1.6
    require_once(dirname(__FILE__).DS.'Uploader-1.7.0.2.php');
}
class AvS_FastSimpleImport_Model_Import_Uploader extends Mage_ImportExport_Model_Import_Uploader
{
}
