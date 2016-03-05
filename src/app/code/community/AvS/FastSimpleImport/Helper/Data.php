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

    /**
     * Returns str with all alphabetic characters converted to lowercase.
     * Uses the multibyte function variants if available.
     *
     * @param $str the string being lowercased
     *
     * @return string str with all alphabetic characters converted to lowercase
     */
    public function strtolower($str)
    {
        if (function_exists('mb_strtolower') && function_exists('mb_detect_encoding')) {
            return mb_strtolower($str, mb_detect_encoding($str));
        }

        return strtolower($str);
    }

}
