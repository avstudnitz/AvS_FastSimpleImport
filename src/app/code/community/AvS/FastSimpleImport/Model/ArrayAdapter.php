<?php

/**
 * Source Adapter for Arrays
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
class AvS_FastSimpleImport_Model_ArrayAdapter implements SeekableIterator
{
    /**
     * @var int
     */
    protected $_position = 0;

    /**
     * @var int
     */
    protected $_subPosition = 0;

    /**
     * @var int
     */
    protected $_maxSubPosition = 0;

    /**
     * @var array The Data; Array of Array
     */
    protected $_array = array();

    /**
     * @var array ; Array of Strings
     */
    protected $_subArray = array();

    /**
     * Go to given position and check if it is valid
     *
     * @throws OutOfBoundsException
     * @param int $position
     * @return void
     */
    public function seek($position)
    {
        $this->_position = $position;

        if (!$this->valid()) {
            throw new OutOfBoundsException("invalid seek position ($position)");
        }
    }

    /* Methods required for Iterator interface */

    /**
     * Initialize data and position
     *
     * @param array $data
     */
    public function __construct($data)
    {
        $this->_array = $data;
        $this->_position = 0;
    }

    /**
     * Rewind to starting position
     *
     * @return void
     */
    public function rewind()
    {
        $this->_position = 0;
    }

    /**
     * Get data at current position
     *
     * @return mixed
     */
    public function current()
    {
        $lineData = $this->_array[$this->_position];
        
        if ($this->_useMultiArrays()) {
            
            if (!isset($this->_subArray[$this->_position])) {

                $this->_createSubArray($lineData);
            }
            
            print_r($this->_subArray[$this->_position][$this->_subPosition]);
            return $this->_subArray[$this->_position][$this->_subPosition];
        }
        
        return $lineData;
    }

    /**
     * Get current position
     *
     * @return int
     */
    public function key()
    {
        return $this->_position;
    }

    /**
     * Set pointer to next position
     *
     * @return void
     */
    public function next()
    {
        if ($this->_useMultiArrays()) {
            ++$this->_subPosition;
            if ($this->_subPosition > $this->_maxSubPosition) {
                ++$this->_position;
            }
        } else {
            ++$this->_position;
        }
    }

    /**
     * Is current position valid?
     *
     * @return bool
     */
    public function valid()
    {
        return isset($this->_array[$this->_position]);
    }

    /**
     * Column names getter.
     *
     * @return array
     */
    public function getColNames()
    {
        $colNames = array();
        foreach ($this->_array as $row) {
            foreach (array_keys($row) as $key) {
                if (!is_numeric($key) && !isset($colNames[$key])) {
                    $colNames[$key] = $key;
                }
            }
        }
        return $colNames;
    }

    public function setValue($key, $value)
    {
        if (!$this->valid()) {
            return;
        }

        $this->_array[$this->_position][$key] = $value;
    }
    
    protected function _useMultiArrays()
    {
        return true;
    }

    /**
     * @param array $lineData
     */
    protected function _createSubArray($lineData)
    {
        $this->_subArray = array(
            $this->_position => array(
                0 => $lineData,
            ),
        );

        foreach ($lineData as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $i => $subValue) {
                    $this->_subArray[$this->_position][$i][$key] = $subValue;
                    if ($i > 0) {
                        $this->_subArray[$this->_position][$i]['sku'] = null;
                    }
                }
            }
        }

        $this->_subPosition = 0;
        $this->_maxSubPosition = sizeof($this->_subArray[$this->_position]) - 1;
    }
}
