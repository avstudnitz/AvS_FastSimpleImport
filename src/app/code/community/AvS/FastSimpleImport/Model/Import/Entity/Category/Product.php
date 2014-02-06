<?php

/**
 * Entity Adapter for importing Magento Categories
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>, Cyrill Schumacher
 */
class AvS_FastSimpleImport_Model_Import_Entity_Category_Product extends Mage_ImportExport_Model_Import_Entity_Abstract
{
    /**
     * Size of bunch - part of entities to save in one step.
     */
    const BUNCH_SIZE = 20;

    /**
     * Data row scopes.
     */
    const SCOPE_DEFAULT = 1;
    const SCOPE_WEBSITE = 2;
    const SCOPE_STORE   = 0;
    const SCOPE_NULL    = -1;

    /**
     * Permanent column names.
     *
     * Names that begins with underscore is not an attribute. This name convention is for
     * to avoid interference with same attribute name.
     */
    const COL_STORE    = '_store';
    const COL_ROOT     = '_root';
    const COL_CATEGORY = '_category';
    const COL_SKU      = '_sku';

    /**
     * Error codes.
     */
    const ERROR_INVALID_SCOPE                 = 'invalidScope';
    const ERROR_INVALID_WEBSITE               = 'invalidWebsite';
    const ERROR_INVALID_STORE                 = 'invalidStore';
    const ERROR_INVALID_ROOT                  = 'invalidRoot';
    const ERROR_CATEGORY_IS_EMPTY             = 'categoryIsEmpty';
    const ERROR_PARENT_NOT_FOUND              = 'parentNotFound';
    const ERROR_NO_DEFAULT_ROW                = 'noDefaultRow';
    const ERROR_DUPLICATE_CATEGORY            = 'duplicateCategory';
    const ERROR_DUPLICATE_SCOPE               = 'duplicateScope';
    const ERROR_ROW_IS_ORPHAN                 = 'rowIsOrphan';
    const ERROR_VALUE_IS_REQUIRED             = 'valueIsRequired';
    const ERROR_CATEGORY_NOT_FOUND_FOR_DELETE = 'categoryNotFoundToDelete';

    /**
     * Categories text-path to ID hash with roots checking.
     *
     * @var array
     */
    protected $_categoriesWithRoots = array();

    /**
     * Category entity DB table name.
     *
     * @var string
     */
    protected $_entityTable;

    /**
     * @var string
     */
    protected $_productEntityTable = NULL;

    /**
     * used in the current bunch
     *
     * @var array
     */
    protected $_skuEntityIds = array();

    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $_messageTemplates = array(
        self::ERROR_INVALID_SCOPE                 => 'Invalid value in Scope column',
        self::ERROR_INVALID_WEBSITE               => 'Invalid value in Website column (website does not exists?)',
        self::ERROR_INVALID_STORE                 => 'Invalid value in Store column (store does not exists?)',
        self::ERROR_INVALID_ROOT                  => 'Root category doesn\'t exist',
        self::ERROR_CATEGORY_IS_EMPTY             => 'Category is empty',
        self::ERROR_PARENT_NOT_FOUND              => 'Parent Category is not found, add parent first',
        self::ERROR_NO_DEFAULT_ROW                => 'Default values row does not exists',
        self::ERROR_DUPLICATE_CATEGORY            => 'Duplicate category',
        self::ERROR_DUPLICATE_SCOPE               => 'Duplicate scope',
        self::ERROR_ROW_IS_ORPHAN                 => 'Orphan rows that will be skipped due default row errors',
        self::ERROR_VALUE_IS_REQUIRED             => 'Required attribute \'%s\' has an empty value',
        self::ERROR_CATEGORY_NOT_FOUND_FOR_DELETE => 'Category not found for delete'
    );

    /**
     * Column names that holds values with particular meaning.
     *
     * @var array
     */
    protected $_particularAttributes = array(
        self::COL_STORE, self::COL_ROOT, self::COL_CATEGORY, self::COL_SKU
    );

    /**
     * Permanent entity columns.
     *
     * @var array
     */
    protected $_permanentAttributes = array(
        self::COL_ROOT, self::COL_CATEGORY, self::COL_SKU
    );

    /** @var bool */
    protected $_ignoreDuplicates = FALSE;

    public function setIgnoreDuplicates($ignore)
    {
        $this->_ignoreDuplicates = (boolean)$ignore;
    }

    public function getIgnoreDuplicates()
    {
        return $this->_ignoreDuplicates;
    }

    /**
     * Set the error limit when the importer will stop
     *
     * @param $limit
     */
    public function setErrorLimit($limit)
    {
        if ($limit) {
            $this->_errorsLimit = $limit;
        } else {
            $this->_errorsLimit = 100;
        }
    }

    /**
     * Constructor.
     *
     */
    public function __construct()
    {
        $this->_dataSourceModel = Mage_ImportExport_Model_Import::getDataSourceModel();
        $this->_connection      = Mage::getSingleton('core/resource')->getConnection('write');

        $this->_initCategories();

        $this->_entityTable        = Mage::getModel('catalog/category')->getResource()->getTable('catalog/category_product');
        $this->_productEntityTable = Mage::getModel('catalog/product')->getResource()->getEntityTable();
    }

    /**
     * Delete Categories.
     *
     * @return AvS_FastSimpleImport_Model_Import_Entity_Category
     */
    protected function _deleteCategoryProduct()
    {
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $idToDelete = array();

            foreach ($bunch as $rowNum => $rowData) {
                if ($this->validateRow($rowData, $rowNum)) {
                    $idToDelete[] = $this->_categoriesWithRoots[$rowData[self::COL_ROOT]][$rowData[self::COL_CATEGORY]]['entity_id'];
                }
            }
            if ($idToDelete) {
                $this->getConnection()->query(
                    $this->getConnection()->quoteInto(
                        "DELETE FROM `{$this->_entityTable}` WHERE `category_id` IN (?)", $idToDelete
                    )
                );
            }
        }
        return $this;
    }

    /**
     * @param string $delimiter
     * @param        $string
     *
     * @return array
     */
    protected function _explodeEscaped($delimiter = '/', $string)
    {
        $exploded = explode($delimiter, $string);
        $fixed    = array();
        for ($k = 0, $l = count($exploded); $k < $l; ++$k) {
            if ($exploded[$k][strlen($exploded[$k]) - 1] == '\\') {
                if ($k + 1 >= $l) {
                    $fixed[] = trim($exploded[$k]);
                    break;
                }
                $exploded[$k][strlen($exploded[$k]) - 1] = $delimiter;
                $exploded[$k] .= $exploded[$k + 1];
                array_splice($exploded, $k + 1, 1);
                --$l;
                --$k;
            } else $fixed[] = trim($exploded[$k]);
        }
        return $fixed;
    }

    /**
     * @param $glue
     * @param $array
     *
     * @return string
     */
    protected function _implodeEscaped($glue, $array)
    {
        $newArray = array();
        foreach ($array as $value) {
            $newArray[] = str_replace($glue, '\\' . $glue, $value);
        }
        return implode('/', $newArray);
    }

    /**
     * Main action to import the data
     *
     * @throws Exception
     * @return bool Result of operation.
     */
    protected function _importData()
    {
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            $this->_deleteCategoryProduct();
        } else {
            $this->_saveCategoryProduct();
        }
        Mage::dispatchEvent('catalog_category_product_import_finish_before', array('adapter' => $this));
        return TRUE;
    }

    /**
     * Initialize categories text-path to ID hash.
     *
     * @return AvS_FastSimpleImport_Model_Import_Entity_Category
     */
    protected function _initCategories()
    {
        $collection = Mage::getResourceModel('catalog/category_collection')->addNameToResult();
        /* @var $collection Mage_Catalog_Model_Resource_Category_Collection */

        foreach ($collection as $category) {
            /** @var $category Mage_Catalog_Model_Category */
            $structure = explode('/', $category->getPath());
            $pathSize  = count($structure);
            if ($pathSize > 1) {
                $path = array();
                for ($i = 1; $i < $pathSize; $i++) {
                    $path[] = $collection->getItemById($structure[$i])->getName();
                }
                $rootCategoryName = array_shift($path);
                if (!isset($this->_categoriesWithRoots[$rootCategoryName])) {
                    $this->_categoriesWithRoots[$rootCategoryName] = array();
                }
                $index = $this->_implodeEscaped('/', $path);

                $this->_categoriesWithRoots[$rootCategoryName][$index] = array(
                    'entity_id' => $category->getId(),
                    'path'      => $category->getPath(),
                    'level'     => $category->getLevel(),
                    'position'  => $category->getPosition()
                );
            }
        }
        return $this;
    }

    /**
     * deletes all products which are in a category
     * gets also all sku -> entity_id relations
     *
     * @param array $bunch
     *
     * @return bool
     */
    protected function _initWorkBunch(array $bunch)
    {
        $this->_skuEntityIds = array();
        $skus                = array();
        $categoryIds         = array();
        foreach ($bunch as $rowNum => $rowData) {
            if ($this->validateRow($rowData, $rowNum)) {
                $skus[] = $rowData[self::COL_SKU];
                $catId  = isset($this->_categoriesWithRoots[$rowData[self::COL_ROOT]][$rowData[self::COL_CATEGORY]])
                    ? (int)$this->_categoriesWithRoots[$rowData[self::COL_ROOT]][$rowData[self::COL_CATEGORY]]['entity_id']
                    : 0;
                if ($catId > 0) {
                    $categoryIds[] = $catId;
                }
            }
        }

        if (count($categoryIds) > 0) {
            $this->getConnection()->query(
                $this->getConnection()->quoteInto(
                    "DELETE FROM `{$this->_entityTable}` WHERE `category_id` IN (?)", $categoryIds
                )
            );
        }

        if (count($skus) > 0) {
            /** @var Varien_Db_Statement_Pdo_Mysql $result */
            $result = $this->getConnection()->query(
                $this->getConnection()->quoteInto(
                    "SELECT entity_id,sku FROM `{$this->_productEntityTable}` WHERE `sku` IN (?)", $skus
                )
            );
            while ($row = $result->fetch()) {
                $this->_skuEntityIds[$row['sku']] = (int)$row['entity_id'];
            }
        }
    }

    /**
     * Gather and save information about category entities.
     *
     * @return AvS_FastSimpleImport_Model_Import_Entity_Category
     */
    protected function _saveCategoryProduct()
    {
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityRowsIn = array();
            $this->_initWorkBunch($bunch);

            foreach ($bunch as $rowNum => $rowData) {
                $isValidRow = $this->validateRow($rowData, $rowNum);
                if (FALSE === $isValidRow) {
                    continue;
                }

                $rowData = $this->_prepareRowForDb($rowData);

                // entity table data
                $catId     = isset($this->_categoriesWithRoots[$rowData[self::COL_ROOT]][$rowData[self::COL_CATEGORY]])
                    ? (int)$this->_categoriesWithRoots[$rowData[self::COL_ROOT]][$rowData[self::COL_CATEGORY]]['entity_id']
                    : 0;
                $prodId    = isset($this->_skuEntityIds[$rowData[self::COL_SKU]])
                    ? (int)$this->_skuEntityIds[$rowData[self::COL_SKU]]
                    : 0;
                $entityRow = array(
                    'category_id' => $catId,
                    'product_id'  => $prodId,
                    'position'    => (int)(isset($rowData['position']) ? $rowData['position'] : 0)
                );

                if ($catId > 0 && $prodId > 0) {
                    $entityRowsIn[] = $entityRow;
                } else {
                    // product or category not found
                    // echo $rowData[self::COL_SKU] . "\n";
                }
            }

            $this->_saveCategoryProductRelation($entityRowsIn);
        }
        return $this;
    }

    /**
     * Update and insert data in entity table.
     *
     * @param array $entityRowsIn Row for insert
     *
     * @return Mage_ImportExport_Model_Import_Entity_Customer
     */
    protected function _saveCategoryProductRelation(array $entityRowsIn)
    {
        if (count($entityRowsIn) > 0) {
            $this->getConnection()->insertOnDuplicate($this->_entityTable, $entityRowsIn, array('position'));
        }
        return $this;
    }

    /**
     * DB connection getter.
     *
     * @return Varien_Db_Adapter_Pdo_Mysql
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * EAV entity type code getter.
     *
     * @abstract
     * @return string
     */
    public function getEntityTypeCode()
    {
        return 'catalog_category_product';
    }

    /**
     * Get next bunch of validatetd rows.
     *
     * @return array|null
     */
    public function getNextBunch()
    {
        return $this->_dataSourceModel->getNextBunch();
    }

    /**
     * Returns boolean TRUE if row scope is default (fundamental) scope.
     *
     * @param array $rowData
     *
     * @return bool
     */
    protected function _isRowScopeDefault(array $rowData)
    {
        return strlen(trim($rowData[self::COL_CATEGORY])) ? TRUE : FALSE;
    }

    /**
     * Obtain scope of the row from row data.
     *
     * @param array $rowData
     *
     * @return int
     */
    public function getRowScope(array $rowData)
    {
        if (strlen(trim($rowData[self::COL_CATEGORY]))) {
            return self::SCOPE_DEFAULT;
        } elseif (empty($rowData[self::COL_STORE])) {
            return self::SCOPE_NULL;
        } else {
            return self::SCOPE_STORE;
        }
    }

    /**
     * Get the categorie's parent ID
     *
     * @param array $rowData
     *
     * @return bool|mixed
     */
    protected function _getParentCategory($rowData)
    {
        $categoryParts = $this->_explodeEscaped('/', $rowData[self::COL_CATEGORY]);
        array_pop($categoryParts);
        $parent = $this->_implodeEscaped('/', $categoryParts);

        if ($parent) {
            if (isset($this->_categoriesWithRoots[$rowData[self::COL_ROOT]][$parent])) {
                return $this->_categoriesWithRoots[$rowData[self::COL_ROOT]][$parent];
            } else {
                return FALSE;
            }
        } else {
            return reset($this->_categoriesWithRoots[$rowData[self::COL_ROOT]]);
        }
    }

    /**
     * Validate data row.
     *
     * @param array $rowData
     * @param int   $rowNum
     *
     * @return boolean
     */
    public function validateRow(array $rowData, $rowNum)
    {
        static $root = NULL;
        static $category = NULL;

        // check if row is already validated
        if (isset($this->_validatedRows[$rowNum])) {
            return !isset($this->_invalidRows[$rowNum]);
        }
        $this->_validatedRows[$rowNum] = TRUE;

        $rowScope = $this->getRowScope($rowData);

        // common validation
        if (self::SCOPE_DEFAULT == $rowScope) { // category is specified, row is SCOPE_DEFAULT, new category block begins

            $this->_processedEntitiesCount++;

            $root     = $rowData[self::COL_ROOT];
            $category = $rowData[self::COL_CATEGORY];

            //check if parent category exists
            if ($this->_getParentCategory($rowData) === FALSE) {
                $this->addRowError(self::ERROR_PARENT_NOT_FOUND, $rowNum);
                return FALSE;
            }

            if (!isset($this->_categoriesWithRoots[$root][$category])) {
                // validate new category type and attribute set
                if (isset($this->_invalidRows[$rowNum])) {
                    // mark SCOPE_DEFAULT row as invalid for future child rows if category not in DB already
                    $category = FALSE;
                }
            }

            //check if the root exists
            if (!isset($this->_categoriesWithRoots[$root])) {
                $this->addRowError(self::ERROR_INVALID_ROOT, $rowNum);
                return FALSE;
            }
        } else {
            if (NULL === $category) {
                $this->addRowError(self::ERROR_CATEGORY_IS_EMPTY, $rowNum);
            } elseif (FALSE === $category) {
                $this->addRowError(self::ERROR_ROW_IS_ORPHAN, $rowNum);
            }
        }
        return !isset($this->_invalidRows[$rowNum]);
    }

    /**
     * Source model setter.
     *
     * @param array $source
     *
     * @return Mage_ImportExport_Model_Import_Entity_Abstract
     */
    public function setArraySource($source)
    {
        $this->_source        = $source;
        $this->_dataValidated = FALSE;

        return $this;
    }

    /**
     * Import behavior setter
     *
     * @param string $behavior
     */
    public function setBehavior($behavior)
    {
        $this->_parameters['behavior'] = $behavior;
    }

    /**
     * Partially reindex newly created and updated products
     *
     * @return AvS_FastSimpleImport_Model_Import_Entity_Product
     */
    public function reindexImportedCategoryProduct()
    {
        switch ($this->getBehavior()) {
            case Mage_ImportExport_Model_Import::BEHAVIOR_DELETE:
                $this->_indexDeleteEvents();
                break;
            case Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE:
            case Mage_ImportExport_Model_Import::BEHAVIOR_APPEND:

                $this->_reindexUpdatedCategories();
                break;
        }
    }

    protected function _indexDeleteEvents()
    {
        //not yet implemented
    }

    protected function _reindexUpdatedCategories()
    {
        $indexProcess = Mage::getSingleton('index/indexer')->getProcessByCode('catalog_category_product');
        if ($indexProcess) {
            $indexProcess->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);
        }
    }
}
