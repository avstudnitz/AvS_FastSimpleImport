<?php

/**
 * Default Helper
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Volker Thiel <v.thiel@loewenstark.de>
 */

class AvS_FastSimpleImport_Helper_Data extends Mage_Core_Helper_Abstract
{
    private $magentoVersion = null;

    public function getMagentoVersion() {
        if ($this->magentoVersion == null) {
            $magentoVersionInfo = Mage::getVersionInfo();


            $this->magentoVersion = $magentoVersionInfo['major'] * 1000 +
                $magentoVersionInfo['minor'] * 100 +
                $magentoVersionInfo['revision'] * 10 +
                $magentoVersionInfo['patch'] * 1;

        }

        return $this->magentoVersion;
    }
}
