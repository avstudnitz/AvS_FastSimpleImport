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
     * @var array The Data; Array of Array
     */
    protected $_array = array();

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
     * Initialize data and position; transferm multi arrays if activated
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
        return $this->_array[$this->_position];
    }

    /**
     * Get current position
     *
     * @return scalar
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
        ++$this->_position;
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

    public function unsetValue($key)
    {
        if (!$this->valid()) {
            return;
        }

        unset($this->_array[$this->_position][$key]);
    }
}
