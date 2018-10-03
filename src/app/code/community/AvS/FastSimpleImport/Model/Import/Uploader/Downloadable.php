<?php

class AvS_FastSimpleImport_Model_Import_Uploader_Downloadable extends Mage_ImportExport_Model_Import_Uploader
{

    // set this to an empty array, so that all mime types and file extensions are allowed
    protected $_allowedMimeTypes = array();

    public function init()
    {
        parent::init();

        // we want to support any file type, so remove the image specific validators
        $this->removeValidateCallback('catalog_product_image');
        $this->removeValidateCallback(Mage_Core_Model_File_Validator_Image::NAME);
    }

}
