<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_ImportExport
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Import entity customer model
 *
 * @category    Mage
 * @package     Mage_ImportExport
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class AvS_FastSimpleImport_Model_Import_Entity_Category extends Mage_ImportExport_Model_Import_Entity_Abstract
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
    const COL_STORE        = '_store';
    const COL_ROOT         = '_root';
    const COL_CATEGORY     = '_category';

    /**
     * Error codes.
     */
    const ERROR_INVALID_SCOPE                  = 'invalidScope';
    const ERROR_INVALID_WEBSITE                = 'invalidWebsite';
    const ERROR_INVALID_STORE                  = 'invalidStore';
    const ERROR_INVALID_ROOT                   = 'invalidRoot';
    const ERROR_CATEGORY_IS_EMPTY              = 'categoryIsEmpty';
    const ERROR_PARENT_NOT_FOUND               = 'parentNotFound';
    const ERROR_NO_DEFAULT_ROW                 = 'noDefaultRow';
    const ERROR_DUPLICATE_CATEGORY             = 'duplicateCategory';
    const ERROR_DUPLICATE_SCOPE                = 'duplicateScope';
    const ERROR_ROW_IS_ORPHAN                  = 'rowIsOrphan';
    const ERROR_VALUE_IS_REQUIRED              = 'valueIsRequired';
    const ERROR_CATEGORY_NOT_FOUND_FOR_DELETE  = 'categoryNotFoundToDelete';


    /**
     * Category attributes parameters.
     *
     *  [attr_code_1] => array(
     *      'options' => array(),
     *      'type' => 'text', 'price', 'textarea', 'select', etc.
     *      'id' => ..
     *  ),
     *  ...
     *
     * @var array
     */
    protected $_attributes = array();

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
     * Attributes with index (not label) value.
     *
     * @var array
     */
    protected $_indexValueAttributes = array(
        'default_sort_by',
        'available_sort_by'
    );


    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $_messageTemplates = array(
        self::ERROR_INVALID_SCOPE                => 'Invalid value in Scope column',
        self::ERROR_INVALID_WEBSITE              => 'Invalid value in Website column (website does not exists?)',
        self::ERROR_INVALID_STORE                => 'Invalid value in Store column (store does not exists?)',
        self::ERROR_INVALID_ROOT                 => 'Root category doesn\'t exist',
        self::ERROR_CATEGORY_IS_EMPTY            => 'Category is empty',
        self::ERROR_PARENT_NOT_FOUND             => 'Parent Category is not found, add parent first',
        self::ERROR_NO_DEFAULT_ROW               => 'Default values row does not exists',
        self::ERROR_DUPLICATE_CATEGORY           => 'Duplicate category',
        self::ERROR_DUPLICATE_SCOPE              => 'Duplicate scope',
        self::ERROR_ROW_IS_ORPHAN                => 'Orphan rows that will be skipped due default row errors',
        self::ERROR_VALUE_IS_REQUIRED            => 'Required attribute \'%s\' has an empty value',
        self::ERROR_CATEGORY_NOT_FOUND_FOR_DELETE=> 'Category not found for delete'
    );

    /**
     * Column names that holds images files names
     *
     * @var array
     */
    protected $_imagesArrayKeys = array(
        'thumbnail', 'image'
    );

    protected $_newCategory = array();

    /**
     * Column names that holds values with particular meaning.
     *
     * @var array
     */
    protected $_particularAttributes = array(
        self::COL_STORE, self::COL_ROOT, self::COL_CATEGORY
    );

    /**
     * Permanent entity columns.
     *
     * @var array
     */
    protected $_permanentAttributes = array(
        self::COL_ROOT, self::COL_CATEGORY
    );

    /**
     * All stores code-ID pairs.
     *
     * @var array
     */
    protected $_storeCodeToId = array();

    /**
     * Store ID to its website stores IDs.
     *
     * @var array
     */
    protected $_storeIdToWebsiteStoreIds = array();

    /**
     * Website code-to-ID
     *
     * @var array
     */
    protected $_websiteCodeToId = array();

    /**
     * Website code to store code-to-ID pairs which it consists.
     *
     * @var array
     */
    protected $_websiteCodeToStoreIds = array();

    /**
     * Media files uploader
     *
     * @var Mage_ImportExport_Model_Import_Uploader
     */
    protected $_fileUploader;

    /**
     * Constructor.
     *
     */
    public function __construct()
    {
        parent::__construct();

        $this->_initWebsites()
             ->_initStores()
             ->_initCategories()
             ->_initAttributes();

        /* @var $categoryResource Mage_Catalog_Model_Resource_Category */
        $categoryResource = Mage::getModel('catalog/category')->getResource();
        $this->_entityTable   = $categoryResource->getEntityTable();

    }

    /**
     * Delete Categories.
     *
     * @return AvS_FastSimpleImport_Model_Import_Entity_Category
     */
    protected function _deleteCategories()
    {
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $idToDelete = array();

            foreach ($bunch as $rowNum => $rowData) {
                if ($this->validateRow($rowData, $rowNum) && self::SCOPE_DEFAULT == $this->getRowScope($rowData)) {
                    $idToDelete[] = $this->_categoriesWithRoots[$rowData[self::COL_ROOT]][$rowData[self::COL_CATEGORY]]['entity_id'];
                }
            }
            if ($idToDelete) {
                $this->_connection->query(
                    $this->_connection->quoteInto(
                        "DELETE FROM `{$this->_entityTable}` WHERE `entity_id` IN (?)", $idToDelete
                    )
                );
            }
        }
        return $this;
    }

    protected function _explodeEscaped($delimiter = '/', $string)
    {
        $exploded = explode($delimiter, $string);
        $fixed = array();
        for($k = 0, $l = count($exploded); $k < $l; ++$k){
            if($exploded[$k][strlen($exploded[$k]) - 1] == '\\') {
                if($k + 1 >= $l) {
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

    protected function _implodeEscaped($glue, $array)
    {
        $newArray = array();
        foreach($array as $value)
        {
            $newArray[] = str_replace($glue, '\\'.$glue, $value);
        }
        return implode('/',$newArray);
    }

    /**
     * Create Category entity from raw data.
     *
     * @throws Exception
     * @return bool Result of operation.
     */
    protected function _importData()
    {
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            $this->_deleteCategories();
        } else {
            $this->_saveCategories();
        }
        Mage::dispatchEvent('catalog_category_import_finish_before', array('adapter'=>$this));
        return true;
    }

    /**
     * Initialize categories text-path to ID hash.
     *
     * @return AvS_FastSimpleImport_Model_Import_Entity_Category
     */
    protected function _initCategories()
    {
        $collection = Mage::getResourceModel('catalog/category_collection')->addNameToResult();
        /* @var $collection Mage_Catalog_Model_Resource_Eav_Mysql4_Category_Collection */

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
                    'path' => $category->getPath(),
                    'level' => $category->getLevel(),
                    'position' => $category->getPosition()
                );
            }
        }
        return $this;
    }

    /**
     * Initialize stores hash.
     *
     * @return AvS_FastSimpleImport_Model_Import_Entity_Category
     */
    protected function _initStores()
    {
        /** @var $store Mage_Core_Model_Store */
        foreach (Mage::app()->getStores() as $store) {
            $this->_storeCodeToId[$store->getCode()] = $store->getId();
            $this->_storeIdToWebsiteStoreIds[$store->getId()] = $store->getWebsite()->getStoreIds();
        }
        return $this;
    }

    /**
     * Initialize website values.
     *
     * @return AvS_FastSimpleImport_Model_Import_Entity_Category
     */
    protected function _initWebsites()
    {
        /** @var $website Mage_Core_Model_Website */
        foreach (Mage::app()->getWebsites() as $website) {
            $this->_websiteCodeToId[$website->getCode()] = $website->getId();
            $this->_websiteCodeToStoreIds[$website->getCode()] = array_flip($website->getStoreCodes());
        }
        return $this;
    }

    /**
     * Initialize customer attributes.
     *
     * @return Mage_ImportExport_Model_Import_Entity_Customer
     */
    protected function _initAttributes()
    {
        $collection = Mage::getResourceModel('catalog/category_attribute_collection');

        foreach ($collection as $attribute) {
            /** @var $attribute Mage_Eav_Model_Entity_Attribute */
            $this->_attributes[$attribute->getAttributeCode()] = array(
                'id'          => $attribute->getId(),
                'is_required' => $attribute->getIsRequired(),
                'is_static'   => $attribute->isStatic(),
                'rules'       => $attribute->getValidateRules() ? unserialize($attribute->getValidateRules()) : null,
                'type'        => Mage_ImportExport_Model_Import::getAttributeType($attribute),
                'options'     => $this->getAttributeOptions($attribute),
                'attribute'   => $attribute
            );
        }
        return $this;
    }

    /**
     * Set valid attribute set and category type to rows with all scopes
     * to ensure that existing Categories doesn't changed.
     *
     * @param array $rowData
     * @return array
     */
    protected function _prepareRowForDb(array $rowData)
    {
        $rowData = parent::_prepareRowForDb($rowData);
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            return $rowData;
        }

        if (self::SCOPE_DEFAULT == $this->getRowScope($rowData)) {
            $rowData['name'] = $this->_getCategoryName($rowData);
            if (! $rowData['position']) $rowData['position'] = 10000;
        }

        return $rowData;
    }


    /**
     * Save category attributes.
     *
     * @param array $attributesData
     * @return AvS_FastSimpleImport_Model_Import_Entity_Category
     */
    protected function _saveCategoryAttributes(array $attributesData)
    {
        foreach ($attributesData as $tableName => $data) {
            $tableData = array();

            foreach ($data as $entityId => $attributes) {

                foreach ($attributes as $attributeId => $storeValues) {
                    foreach ($storeValues as $storeId => $storeValue) {
                        $tableData[] = array(
                            'entity_id'      => $entityId,
                            'entity_type_id' => $this->_entityTypeId,
                            'attribute_id'   => $attributeId,
                            'store_id'       => $storeId,
                            'value'          => $storeValue
                        );
                    }
                }
            }
            $this->_connection->insertOnDuplicate($tableName, $tableData, array('value'));
        }
        return $this;
    }

    /**
     * Gather and save information about category entities.
     *
     * @return AvS_FastSimpleImport_Model_Import_Entity_Category
     */
    protected function _saveCategories()
    {
        $strftimeFormat = Varien_Date::convertZendToStrftime(Varien_Date::DATETIME_INTERNAL_FORMAT, true, true);
        $nextEntityId   = Mage::getResourceHelper('importexport')->getNextAutoincrement($this->_entityTable);
        static $entityId;

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityRowsIn = array();
            $entityRowsUp = array();
            $attributes   = array();
            $uploadedGalleryFiles = array();

            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }
                $rowScope = $this->getRowScope($rowData);

                $rowData = $this->_prepareRowForDb($rowData);

                if (self::SCOPE_DEFAULT == $rowScope) {
                    $rowCategory = $rowData[self::COL_CATEGORY];

                    $parentCategory = $this->_getParentCategory($rowData);

                    // entity table data
                    $entityRow = array(
                        'parent_id'   => $parentCategory['entity_id'],
                        'level'       => $parentCategory['level'] + 1,
                        'created_at'  => empty($rowData['created_at']) ? now()
                                         : gmstrftime($strftimeFormat, strtotime($rowData['created_at'])),
                        'updated_at'  => now(),
                        'position'    => $rowData['position']
                    );

                    if (isset($this->_categoriesWithRoots[$rowData[self::COL_ROOT]][$rowData[self::COL_CATEGORY]]))
                    { //edit

                        $entityId = $this->_categoriesWithRoots[$rowData[self::COL_ROOT]][$rowData[self::COL_CATEGORY]]['entity_id'];
                        $entityRow['entity_id']        = $entityId;
                        $entityRow['path']             = $parentCategory['path'] .'/'.$entityId;
                        $entityRowsUp[]                = $entityRow;
                    } else
                    { // create
                        $entityId                      = $nextEntityId++;
                        $entityRow['entity_id']        = $entityId;
                        $entityRow['path']             = $parentCategory['path'] .'/'.$entityId;
                        $entityRow['entity_type_id']   = $this->_entityTypeId;
                        $entityRow['attribute_set_id'] = 0;
                        $entityRowsIn[]                = $entityRow;

                        $this->_newCategory[$rowData[self::COL_ROOT]][$rowData[self::COL_CATEGORY]] = array(
                            'entity_id' => $entityId,
                            'path' => $entityRow['path'],
                            'level' => $entityRow['level']
                        );

                    }
                }

                foreach ($this->_imagesArrayKeys as $imageCol) {
                    if (!empty($rowData[$imageCol])) { // 5. Media gallery phase
                        if (!array_key_exists($rowData[$imageCol], $uploadedGalleryFiles)) {
                            $uploadedGalleryFiles[$rowData[$imageCol]] = $this->_uploadMediaFiles($rowData[$imageCol]);
                        }
                        $rowData[$imageCol] = $uploadedGalleryFiles[$rowData[$imageCol]];
                    }
                }

                // Attributes phase
                $rowStore = self::SCOPE_STORE == $rowScope ? $this->_storeCodeToId[$rowData[self::COL_STORE]] : 0;

                /* @var $category Mage_Catalog_Model_Category */
                $category = Mage::getModel('catalog/category', $rowData);

                foreach (array_intersect_key($rowData, $this->_attributes) as $attrCode => $attrValue) {
                    if (!$this->_attributes[$attrCode]['is_static'] && strlen($attrValue)) {
                        
                        /** @var $attribute Mage_Eav_Model_Entity_Attribute */
                        $attribute = $this->_attributes[$attrCode]['attribute'];

                        if('multiselect' != $attribute->getFrontendInput()
                            && self::SCOPE_NULL == $rowScope) {
                            continue; // skip attribute processing for SCOPE_NULL rows
                        }

                        $attrId    = $attribute->getAttributeId();
                        $backModel = $attribute->getBackendModel();
                        $attrTable = $attribute->getBackend()->getTable();
                        $attrParams = $this->_attributes[$attrCode];
                        $storeIds  = array(0);

                        if ('select' == $attrParams['type']) {
                            if (isset($attrParams['options'][strtolower($attrValue)]))
                            {
                                $attrValue = $attrParams['options'][strtolower($attrValue)];
                            }
                        } elseif ('datetime' == $attribute->getBackendType() && strtotime($attrValue)) {
                            $attrValue = gmstrftime($strftimeFormat, strtotime($attrValue));
                        } elseif ($backModel) {
                            $attribute->getBackend()->beforeSave($category);
                            $attrValue = $category->getData($attribute->getAttributeCode());
                        }

                        if (self::SCOPE_STORE == $rowScope) {
                            if (self::SCOPE_WEBSITE == $attribute->getIsGlobal()) {
                                // check website defaults already set
                                if (!isset($attributes[$attrTable][$entityId][$attrId][$rowStore])) {
                                    $storeIds = $this->_storeIdToWebsiteStoreIds[$rowStore];
                                }
                            } elseif (self::SCOPE_STORE == $attribute->getIsGlobal()) {
                                $storeIds = array($rowStore);
                            }
                        }

                        foreach ($storeIds as $storeId) {
                            if('multiselect' == $attribute->getFrontendInput()) {
                                if(!isset($attributes[$attrTable][$entityId][$attrId][$storeId])) {
                                    $attributes[$attrTable][$entityId][$attrId][$storeId] = '';
                                } else {
                                    $attributes[$attrTable][$entityId][$attrId][$storeId] .= ',';
                                }
                                $attributes[$attrTable][$entityId][$attrId][$storeId] .= $attrValue;
                            } else {
                                $attributes[$attrTable][$entityId][$attrId][$storeId] = $attrValue;
                            }
                        }

                        $attribute->setBackendModel($backModel); // restore 'backend_model' to avoid 'default' setting
                    }
                }
            }

            Mage::log($attributes);

            $this->_saveCategoryEntity($entityRowsIn, $entityRowsUp);
            $this->_saveCategoryAttributes($attributes);
        }
        return $this;
    }


    /**
     * Returns an object for upload a media files
     */
    protected function _getUploader()
    {
        if (is_null($this->_fileUploader)) {
            $this->_fileUploader    = new Mage_ImportExport_Model_Import_Uploader();

            $this->_fileUploader->init();
            $this->_fileUploader->setFilesDispersion(false);

            $tmpDir     = Mage::getConfig()->getOptions()->getMediaDir() . '/import';
            $destDir    = Mage::getConfig()->getOptions()->getMediaDir() . '/catalog/category';
            if (!is_writable($destDir)) {
                @mkdir($destDir, 0777, true);
            }
            if (!$this->_fileUploader->setTmpDir($tmpDir)) {
                Mage::throwException("File directory '{$tmpDir}' is not readable.");
            }
            if (!$this->_fileUploader->setDestDir($destDir)) {
                Mage::throwException("File directory '{$destDir}' is not writable.");
            }
        }
        return $this->_fileUploader;
    }

    /**
     * Uploading files into the "catalog/category" media folder.
     * Return a new file name if the same file is already exists.
     * @todo Solve the problem with images that get imported multiple times.
     *
     * @param string $fileName
     * @return string
     */
    protected function _uploadMediaFiles($fileName)
    {
        try {
            $res = $this->_getUploader()->move($fileName);
            return $res['file'];
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Update and insert data in entity table.
     *
     * @param array $entityRowsIn Row for insert
     * @param array $entityRowsUp Row for update
     * @return Mage_ImportExport_Model_Import_Entity_Customer
     */
    protected function _saveCategoryEntity(array $entityRowsIn, array $entityRowsUp)
    {
        if ($entityRowsIn) {
            $this->_connection->insertMultiple($this->_entityTable, $entityRowsIn);
        }
        if ($entityRowsUp) {
            $this->_connection->insertOnDuplicate(
                $this->_entityTable,
                $entityRowsUp,
                array('parent_id', 'path', 'position', 'level','children_count')
            );
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
        return 'catalog_category';
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
     * Obtain scope of the row from row data.
     *
     * @param array $rowData
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
     * All website codes to ID getter.
     *
     * @return array
     */
    public function getWebsiteCodes()
    {
        return $this->_websiteCodeToId;
    }


    /**
     * Get the categorie's parent ID
     *
     * @param array $rowData
     * @return bool|mixed
     */
    protected function _getParentCategory($rowData)
    {
        $categoryParts = $this->_explodeEscaped('/',$rowData[self::COL_CATEGORY]);
        array_pop($categoryParts);
        $parent = $this->_implodeEscaped('/',$categoryParts);

        if ($parent)
        {
            if (isset($this->_categoriesWithRoots[$rowData[self::COL_ROOT]][$parent]))
            {
                return $this->_categoriesWithRoots[$rowData[self::COL_ROOT]][$parent];
            } elseif (isset($this->_newCategory[$rowData[self::COL_ROOT]][$parent])) {
                return $this->_newCategory[$rowData[self::COL_ROOT]][$parent];
            } else {
                return false;
            }
        } else {
            return reset($this->_categoriesWithRoots[$rowData[self::COL_ROOT]]);
        }
    }

    protected function _getCategoryName($rowData)
    {
        if (isset($rowData['name']) && strlen($rowData['name']))
            return $rowData['name'];
        $categoryParts = $this->_explodeEscaped('/',$rowData[self::COL_CATEGORY]);
        return end($categoryParts);
    }

    /**
     * Validate data row.
     *
     * @param array $rowData
     * @param int $rowNum
     * @return boolean
     */
    public function validateRow(array $rowData, $rowNum)
    {
        static $root = null;
        static $category = null;


        // check if row is already validated
        if (isset($this->_validatedRows[$rowNum])) {
            return !isset($this->_invalidRows[$rowNum]);
        }
        $this->_validatedRows[$rowNum] = true;

        //check for duplicates
        if (isset($this->_newCategory[$rowData[self::COL_ROOT]][$rowData[self::COL_CATEGORY]])) {
            $this->addRowError(self::ERROR_DUPLICATE_CATEGORY, $rowNum);
            return false;
        }
        $rowScope = $this->getRowScope($rowData);

        // BEHAVIOR_DELETE use specific validation logic
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            if (self::SCOPE_DEFAULT == $rowScope
                && !isset($this->_categoriesWithRoots[$rowData[self::COL_ROOT]][$rowData[self::COL_CATEGORY]]))
            {
                $this->addRowError(self::ERROR_CATEGORY_NOT_FOUND_FOR_DELETE, $rowNum);
                return false;
            }
            return true;
        }

        // common validation
        if (self::SCOPE_DEFAULT == $rowScope) { // category is specified, row is SCOPE_DEFAULT, new category block begins
            $rowData['name'] = $this->_getCategoryName($rowData);

            $this->_processedEntitiesCount ++;

            $root = $rowData[self::COL_ROOT];
            $category = $rowData[self::COL_CATEGORY];

            //check if parent category exists
            if ($this->_getParentCategory($rowData) === false)
            {

                $this->addRowError(self::ERROR_PARENT_NOT_FOUND, $rowNum);
                return false;
            }

            if (isset($this->_categoriesWithRoots[$root][$category])) {

            } else { // validate new category type and attribute set
                if (!isset($this->_newCategory[$root][$category])) {
                    $this->_newCategory[$root][$category] = array(
                        'entity_id'     => null,
                    );
                }
                if (isset($this->_invalidRows[$rowNum])) {
                    // mark SCOPE_DEFAULT row as invalid for future child rows if category not in DB already
                    $category = false;
                }
            }

            //check if the root exists
            if (! isset($this->_categoriesWithRoots[$root]))
            {
                $this->addRowError(self::ERROR_INVALID_ROOT, $rowNum);
                return false;
            }


            // check simple attributes
            foreach ($this->_attributes as $attrCode => $attrParams) {
                if (isset($rowData[$attrCode]) && strlen($rowData[$attrCode])) {
                    $this->isAttributeValid($attrCode, $attrParams, $rowData, $rowNum);
                } elseif ($attrParams['is_required'] && !isset($this->_categoriesWithRoots[$root][$category])) {
                    $this->addRowError(self::ERROR_VALUE_IS_REQUIRED, $rowNum, $attrCode);
                }
            }

        } else {
            if (null === $category) {
                $this->addRowError(self::ERROR_CATEGORY_IS_EMPTY, $rowNum);
            } elseif (false === $category) {
                $this->addRowError(self::ERROR_ROW_IS_ORPHAN, $rowNum);
            } elseif (self::SCOPE_STORE == $rowScope && !isset($this->_storeCodeToId[$rowData[self::COL_STORE]])) {
                $this->addRowError(self::ERROR_INVALID_STORE, $rowNum);
            }
        }
        return !isset($this->_invalidRows[$rowNum]);
    }

    /**
     * Get array of affected Categories
     *
     * @return array
     */
    public function getAffectedEntityIds()
    {
        $categoryIds = array();
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->isRowAllowedToImport($rowData, $rowNum)) {
                    continue;
                }
                if (!isset($this->_newCategory[$rowData[self::COL_CATEGORY]]['entity_id'])) {
                    continue;
                }
                $categoryIds[] = $this->_newCategory[$rowData[self::COL_CATEGORY]]['entity_id'];
            }
        }
        return $categoryIds;
    }

    /**
     * Check one attribute. Can be overridden in child. Copied this validator
     * from the customer importer.
     *
     * @param string $attrCode Attribute code
     * @param array $attrParams Attribute params
     * @param array $rowData Row data
     * @param int $rowNum
     * @return boolean
     */
    public function isAttributeValid($attrCode, array $attrParams, array $rowData, $rowNum)
    {
        $message = '';
        switch ($attrParams['type']) {
            case 'varchar':
                $val   = Mage::helper('core/string')->cleanString($rowData[$attrCode]);
                $valid = Mage::helper('core/string')->strlen($val) < self::DB_MAX_VARCHAR_LENGTH;
                $message = 'String is too long, only ' . self::DB_MAX_VARCHAR_LENGTH . ' characters allowed.';
                break;
            case 'decimal':
                $val   = trim($rowData[$attrCode]);
                $valid = (float)$val == $val;
                $message = 'Decimal value expected.';
                break;
            case 'select':
            case 'multiselect':
                $valid = isset($attrParams['options'][strtolower($rowData[$attrCode])]);
                $message = 'Possible options are: ' . implode(', ', array_keys($attrParams['options']));
                break;
            case 'int':
                $val   = trim($rowData[$attrCode]);
                $valid = (int)$val == $val;
                $message = 'Integer value expected.';
                break;
            case 'datetime':
                $val   = trim($rowData[$attrCode]);
                $valid = strtotime($val) !== false
                    || preg_match('/^\d{2}.\d{2}.\d{2,4}(?:\s+\d{1,2}.\d{1,2}(?:.\d{1,2})?)?$/', $val);
                $message = 'Datetime value expected.';
                break;
            case 'text':
                $val   = Mage::helper('core/string')->cleanString($rowData[$attrCode]);
                $valid = Mage::helper('core/string')->strlen($val) < self::DB_MAX_TEXT_LENGTH;
                $message = 'String is too long, only ' . self::DB_MAX_TEXT_LENGTH . ' characters allowed.';
                break;
            default:
                $valid = true;
                break;
        }

        if (!$valid) {
            $this->addRowError(Mage::helper('importexport')->__("Invalid value for '%s'") . '. ' . $message, $rowNum, $attrCode);
        } elseif (!empty($attrParams['is_unique'])) {
            if (isset($this->_uniqueAttributes[$attrCode][$rowData[$attrCode]])) {
                $this->addRowError(Mage::helper('importexport')->__("Duplicate Unique Attribute for '%s'"), $rowNum, $attrCode);
                return false;
            }
            $this->_uniqueAttributes[$attrCode][$rowData[$attrCode]] = true;
        }
        return (bool) $valid;
    }


    /**
     * Source model setter.
     *
     * @param array $source
     * @return Mage_ImportExport_Model_Import_Entity_Abstract
     */
    public function setArraySource($source)
    {
        $this->_source = $source;
        $this->_dataValidated = false;

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
}
