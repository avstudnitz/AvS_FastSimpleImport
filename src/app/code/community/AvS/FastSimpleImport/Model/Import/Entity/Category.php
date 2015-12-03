<?php

/**
 * Entity Adapter for importing Magento Categories
 *
 * @category   AvS
 * @package    AvS_FastSimpleImport
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author     Paul Hachmang <paul@h-o.nl>
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
     * Code of a primary attribute which identifies the entity group if import contains of multiple rows
     *
     * @var string
     */
    protected $_masterAttributeCode = '_category';

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

    /** @var bool */
    protected $_ignoreDuplicates = false;

    /** @var bool */
    protected $_unsetEmptyFields = false;

    /** @var bool|string */
    protected $_symbolEmptyFields = false;

    /** @var bool|string */
    protected $_symbolIgnoreFields = false;

    protected $_defaultAttributeSetId = 0;

    public function setIgnoreDuplicates($ignore)
    {
        $this->_ignoreDuplicates = (boolean) $ignore;
    }


    public function getIgnoreDuplicates()
    {
        return $this->_ignoreDuplicates;
    }


    /**
     * @param boolean $value
     * @return $this
     */
    public function setUnsetEmptyFields($value) {
        $this->_unsetEmptyFields = (boolean) $value;
        return $this;
    }


    /**
     * @param string $value
     * @return $this
     */
    public function setSymbolEmptyFields($value) {
        $this->_symbolEmptyFields = $value;
        return $this;
    }


    /**
     * @param string $value
     * @return $this
     */
    public function setSymbolIgnoreFields($value) {
        $this->_symbolIgnoreFields = $value;
        return $this;
    }


    /**
     * Set the error limit when the importer will stop
     * @param $limit
     */
    public function setErrorLimit($limit) {
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
        parent::__construct();

        $this
            ->_initOnTabAttributes()
            ->_initWebsites()
            ->_initStores()
            ->_initCategories()
            ->_initAttributes()
            ->_initAttributeSetId();

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
                $this->_filterRowData($rowData);
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

    public function getCategoriesWithRoots() {
        return $this->_categoriesWithRoots;
    }


    protected function _explodeEscaped($delimiter = '/', $string)
    {
        $exploded = explode($delimiter, $string);
        $fixed = array();
        for($k = 0, $l = count($exploded); $k < $l; ++$k){
            $eIdx = strlen($exploded[$k]) - 1;
            if($eIdx >= 0 && $exploded[$k][$eIdx] == '\\') {
                if($k + 1 >= $l) {
                    $fixed[] = trim($exploded[$k]);
                    break;
                }
                $exploded[$k][$eIdx] = $delimiter;
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
            $this->_saveOnTab();
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

                //allow importing by ids.
                if (!isset($this->_categoriesWithRoots[$structure[1]])) {
                    $this->_categoriesWithRoots[$structure[1]] = array();
                }
                $this->_categoriesWithRoots[$structure[1]][$category->getId()] =
                    $this->_categoriesWithRoots[$rootCategoryName][$index];
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
     * @return $this
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
     * Initialize the default attribute_set_id
     * @return $this
     */
    protected function _initAttributeSetId() {
        $this->_defaultAttributeSetId = Mage::getSingleton('catalog/category')->getDefaultAttributeSetId();
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
            if (! isset($rowData['position'])) $rowData['position'] = 10000; // diglin - prevent warning message
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
                $this->_filterRowData($rowData);

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
                        $rowData['entity_id']          = $entityId;
                    } else
                    { // create
                        $entityId                      = $nextEntityId++;
                        $entityRow['entity_id']        = $entityId;
                        $entityRow['path']             = $parentCategory['path'] .'/'.$entityId;
                        $entityRow['entity_type_id']   = $this->_entityTypeId;
                        $entityRow['attribute_set_id'] = $this->_defaultAttributeSetId;
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
                    if (!$this->_attributes[$attrCode]['is_static']) {

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
                        } elseif ($backModel && 'available_sort_by' != $attrCode) {
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
            $this->_fileUploader->removeValidateCallback('catalog_product_image');
            $this->_fileUploader->setFilesDispersion(false);

            $tmpDir     = Mage::getConfig()->getOptions()->getMediaDir() . '/import';
            $destDir    = Mage::getConfig()->getOptions()->getMediaDir() . '/catalog/category';
            if (!is_writable($destDir)) {
                @mkdir($destDir, 0777, true);
            }
            // diglin - add auto creation in case folder doesn't exist
            if (!file_exists($tmpDir)) {
                @mkdir($tmpDir, 0777, true);
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
     * Returns boolean TRUE if row scope is default (fundamental) scope.
     *
     * @param array $rowData
     * @return bool
     */
    protected function _isRowScopeDefault(array $rowData) {
        return strlen(trim($rowData[self::COL_CATEGORY])) ? true : false;
    }

    /**
     * Obtain scope of the row from row data.
     *
     * @param array $rowData
     * @return int
     */
    public function getRowScope(array $rowData)
    {
        if (isset($rowData[self::COL_CATEGORY]) && strlen(trim($rowData[self::COL_CATEGORY]))) {
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

        if (isset($rowData['fsi_line_number'])) {
            $rowNum = $rowData['fsi_line_number'];
        }
        $this->_filterRowData($rowData);

        // check if row is already validated
        if (isset($this->_validatedRows[$rowNum])) {
            return !isset($this->_invalidRows[$rowNum]);
        }
        $this->_validatedRows[$rowNum] = true;

        //check for duplicates
        if (isset($rowData[self::COL_ROOT])
            && isset($rowData[self::COL_CATEGORY])
            && isset($this->_newCategory[$rowData[self::COL_ROOT]][$rowData[self::COL_CATEGORY]])) {
            if (! $this->getIgnoreDuplicates()) {
                $this->addRowError(self::ERROR_DUPLICATE_CATEGORY, $rowNum);
            }

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

        if (isset($this->_invalidRows[$rowNum])) {
            $category = false; // mark row as invalid for next address rows
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
                $this->_filterRowData($rowData);
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
                $message = 'String is too long, only ' . self::DB_MAX_VARCHAR_LENGTH . ' characters allowed. Your input: ' . $rowData[$attrCode] . ', length: ' . strlen($val);
                break;
            case 'decimal':
                $val   = trim($rowData[$attrCode]);
                $valid = (float)$val == $val;
                $message = 'Decimal value expected. Your Input: '.$rowData[$attrCode];
                break;
            case 'select':
            case 'multiselect':
                $valid = isset($attrParams['options'][strtolower($rowData[$attrCode])]);
                $message = 'Possible options are: ' . implode(', ', array_keys($attrParams['options'])) . '. Your input: ' . $rowData[$attrCode];
                break;
            case 'int':
                $val   = trim($rowData[$attrCode]);
                $valid = (int)$val == $val;
                $message = 'Integer value expected. Your Input: ' . $rowData[$attrCode];
                break;
            case 'datetime':
                $val   = trim($rowData[$attrCode]);
                $valid = strtotime($val) !== false
                    || preg_match('/^\d{2}.\d{2}.\d{2,4}(?:\s+\d{1,2}.\d{1,2}(?:.\d{1,2})?)?$/', $val);
                $message = 'Datetime value expected. Your Input: ' . $rowData[$attrCode];
                break;
            case 'text':
                $val   = Mage::helper('core/string')->cleanString($rowData[$attrCode]);
                $valid = Mage::helper('core/string')->strlen($val) < self::DB_MAX_TEXT_LENGTH;
                $message = 'String is too long, only ' . self::DB_MAX_TEXT_LENGTH . ' characters allowed. Your input: ' . $rowData[$attrCode] . ', length: ' . strlen($val);
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


    /**
     * Partially reindex newly created and updated products
     *
     * @return AvS_FastSimpleImport_Model_Import_Entity_Product
     */
    public function reindexImportedCategories()
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

    public function updateChildrenCount() {
        //we only need to update the children count when we are updating, not when we are deleting.
        if (! in_array($this->getBehavior(), array(Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND))) {
            return;
        }

        /** @var Varien_Db_Adapter_Pdo_Mysql $connection */
        $connection = $this->_connection;

        $categoryTable = Mage::getSingleton('core/resource')->getTableName('catalog/category');
        $categoryTableTmp = $categoryTable . '_tmp';
        $connection->query('DROP TEMPORARY TABLE IF EXISTS ' . $categoryTableTmp);
        $connection->query("CREATE TEMPORARY TABLE {$categoryTableTmp} LIKE {$categoryTable};
            INSERT INTO {$categoryTableTmp} SELECT * FROM {$categoryTable};
            UPDATE {$categoryTable} cce
            SET children_count =
            (
                SELECT count(cce2.entity_id) - 1 as children_county
                FROM {$categoryTableTmp} cce2
                WHERE PATH LIKE CONCAT(cce.path,'%')
            );
        ");
    }


    /**
     * Reindex all categories
     * @throws Exception
     * @return $this
     */
    protected function _indexDeleteEvents()
    {
        return $this->_reindexUpdatedCategories();
    }


    /**
     * Reindex all categories
     * @return $this
     * @throws Exception
     */
    protected function _reindexUpdatedCategories()
    {

        if (Mage::helper('core')->isModuleEnabled('Enterprise_Index')) {
            Mage::dispatchEvent('fastsimpleimport_reindex_category_enterprise_before');
            Mage::getSingleton('enterprise_index/observer')->refreshIndex(Mage::getModel('cron/schedule'));
        } else {
            $entityIds = $this->_getProcessedCategoryIds();

            $categoryFlatHelper = Mage::helper('catalog/category_flat');
            if ($categoryFlatHelper->isAvailable() && $categoryFlatHelper->isAccessible()) {

                Mage::dispatchEvent('fastsimpleimport_reindex_category_before_flat', array('entity_id' => &$entityIds));
                Mage::getResourceSingleton('catalog/category_flat')->reindexAll();
            }

            if (Mage::helper('core')->isModuleEnabled('EcomDev_UrlRewrite')) {

                Mage::dispatchEvent('fastsimpleimport_reindex_category_before_ecomdev_urlrewrite', array('entity_id' => &$entityIds));
                Mage::getResourceSingleton('ecomdev_urlrewrite/indexer')->updateCategoryRewrites($entityIds);
            } else {

                Mage::dispatchEvent('fastsimpleimport_reindex_category_before_urlrewrite', array('entity_id' => &$entityIds));
                /* @var $urlModel Mage_Catalog_Model_Url */
                $urlModel = Mage::getSingleton('catalog/url');
                $urlModel->clearStoreInvalidRewrites();
                foreach ($entityIds as $productId) {
                    $urlModel->refreshCategoryRewrite($productId);
                }
            }
        }

        Mage::dispatchEvent('fastsimpleimport_reindex_category_after', array('entity_id' => &$entityIds));

        return $this;
    }


    /**
     * Ids of products which have been created, updated or deleted
     *
     * @return array
     */
    protected function _getProcessedCategoryIds()
    {
        $categoryIds = array();
        $source = $this->getSource();

        $source->rewind();
        while ($source->valid()) {
            $current = $source->current();
            if (isset($this->_newCategory[$current[self::COL_ROOT]][$current[self::COL_CATEGORY]])) {
                $categoryIds[] = $this->_newCategory[$current[self::COL_ROOT]][$current[self::COL_CATEGORY]];
            } elseif (isset($this->_categoriesWithRoots[$current[self::COL_ROOT]][$current[self::COL_CATEGORY]])) {
                $categoryIds[] = $this->_categoriesWithRoots[$current[self::COL_ROOT]][$current[self::COL_CATEGORY]];
            }

            $source->next();
        }

        return $categoryIds;
    }

    /**
     * Removes empty keys in case value is null or empty string
     * Behavior can be turned off with config setting "fastsimpleimport/general/clear_field_on_empty_string"
     * You can define a string which can be used for clearing a field, configured in "fastsimpleimport/product/symbol_for_clear_field"
     *
     * @param array $rowData
     */
    protected function _filterRowData(&$rowData)
    {
        if ($this->_unsetEmptyFields || $this->_symbolEmptyFields || $this->_symbolIgnoreFields) {
            foreach($rowData as $key => $fieldValue) {
                if ($this->_unsetEmptyFields && !strlen($fieldValue)) {
                    unset($rowData[$key]);
                } else if ($this->_symbolEmptyFields && trim($fieldValue) == $this->_symbolEmptyFields) {
                    $rowData[$key] = NULL;
                } else if ($this->_symbolIgnoreFields && trim($fieldValue) == $this->_symbolIgnoreFields) {
                    unset($rowData[$key]);
                }
            }
        }
    }

    /**
     * Validate data rows and save bunches to DB.
     * Taken from https://github.com/tim-bezhashvyly/Sandfox_ImportExportFix
     *
     * @return Mage_ImportExport_Model_Import_Entity_Abstract
     */
    protected function _saveValidatedBunches()
    {
        $source = $this->_getSource();
        $bunchRows = array();
        $startNewBunch = false;
        $maxDataSize = Mage::getResourceHelper('importexport')->getMaxDataSize();
        $bunchSize = Mage::helper('importexport')->getBunchSize();

        $source->rewind();
        $this->_dataSourceModel->cleanBunches();

        while ($source->valid() || count($bunchRows) || isset($entityGroup)) {
            if ($startNewBunch || !$source->valid()) {
                /* If the end approached add last validated entity group to the bunch */
                if (!$source->valid() && isset($entityGroup)) {
                    $bunchRows = array_merge($bunchRows, $entityGroup);
                    unset($entityGroup);
                }
                $this->_dataSourceModel->saveBunch($this->getEntityTypeCode(), $this->getBehavior(), $bunchRows);
                $bunchRows = array();
                $startNewBunch = false;
            }
            if ($source->valid()) {
                if ($this->_errorsCount >= $this->_errorsLimit) { // errors limit check
                    return $this;
                }
                $rowData = $source->current();

                $this->_processedRowsCount++;

                if (isset($rowData[$this->_masterAttributeCode]) && trim($rowData[$this->_masterAttributeCode])) {
                    /* Add entity group that passed validation to bunch */
                    if (isset($entityGroup)) {
                        $bunchRows = array_merge($bunchRows, $entityGroup);
                        $productDataSize = strlen(serialize($bunchRows));

                        /* Check if the nw bunch should be started */
                        $isBunchSizeExceeded = ($bunchSize > 0 && count($bunchRows) >= $bunchSize);
                        $startNewBunch = $productDataSize >= $maxDataSize || $isBunchSizeExceeded;
                    }

                    /* And start a new one */
                    $entityGroup = array();
                }

                if ($this->validateRow($rowData, $source->key()) && isset($entityGroup)) {
                    /* Add row to entity group */
                    $entityGroup[$source->key()] = $this->_prepareRowForDb($rowData);
                } elseif (isset($entityGroup)) {
                    /* In case validation of one line of the group fails kill the entire group */
                    unset($entityGroup);
                }
                $source->next();
            }
        }
        return $this;
    }


    /**
     * @param $sku
     * @return array|false
     */
    public function getEntityByCategory($root, $category)
    {
        if (isset($this->_categoriesWithRoots[$root][$category])) {
            return $this->_categoriesWithRoots[$root][$category];
        }

        if (isset($this->_newCategory[$root][$category])) {
            return $this->_newCategory[$root][$category];
        }

        return false;
    }


    /**
     * @return $this
     */
    protected function _initOnTabAttributes()
    {
        if (Mage::helper('core')->isModuleEnabled('OnTap_Merchandiser')) {
            $this->_particularAttributes = array_merge($this->_particularAttributes, [
                '_ontap_heroproducts',
                '_ontap_attribute',
                '_ontap_attribute_value',
                '_ontap_attribute_logic',
                '_ontap_ruled_only',
                '_ontap_automatic_sort'
            ]);
        }
        return $this;
    }

    /**
     * Stock item saving.
     * Overwritten in order to fix bug with stock data import
     * See http://www.magentocommerce.com/bug-tracking/issue/?issue=13539
     * See https://github.com/avstudnitz/AvS_FastSimpleImport/issues/3
     *
     * @return Mage_ImportExport_Model_Import_Entity_Product
     */
    protected function _saveOnTab()
    {
        if (! Mage::helper('core')->isModuleEnabled('OnTap_Merchandiser')) {
            return $this;
        }

        $entityTable = Mage::getSingleton('core/resource')->getTableName('merchandiser_category_values');
        $categoryId = null;
        $attributeIdsByCode = [];

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $onTapData = array();

            // Format bunch to stock data rows
            foreach ($bunch as $rowNum => $rowData) {
                $this->_filterRowData($rowData);
                if (!$this->isRowAllowedToImport($rowData, $rowNum)) {
                    continue;
                }

                if (self::SCOPE_DEFAULT == $this->getRowScope($rowData)) {
                    $category = $this->getEntityByCategory($rowData[self::COL_ROOT], $rowData[self::COL_CATEGORY]);
                    $categoryId = (int) $category['entity_id'];

                    $onTapData[$categoryId] = [
                        'category_id' => $categoryId,
                        'heroproducts'     => '',
                        'attribute_codes'  => '',
                        'smart_attributes' => '',
                        'ruled_only'       => '',
                        'automatic_sort'   => ''
                    ];
                }

                //we have a non-SCOPE_DEFAULT row, we check if it has a stock_id, if not, skip it.
                if (self::SCOPE_DEFAULT == $this->getRowScope($rowData)) {
                    if (isset($rowData['_ontap_ruled_only'])) {
                        $onTapData[$categoryId]['ruled_only'] = (int) $rowData['_ontap_ruled_only'];
                    }
                    if (isset($rowData['_ontap_automatic_sort'])) {
                        $onTapData[$categoryId]['automatic_sort'] = $rowData['_ontap_automatic_sort'];
                    }
                }

                //get the _ontab_* smart attribute values and map them to the new keys that are present in the database.
                $smartAttributes = array_intersect_key($rowData, [
                    '_ontap_attribute' => '',
                    '_ontap_attribute_value' => '',
                    '_ontap_attribute_logic' => '',
                ]);

                //only add if we've found data
                //todo check if we've got all values, there should be three, else it will throw an error here.
                if ($smartAttributes) {
                    if (! isset($onTapData[$categoryId]['attribute_codes'])) {
                        $onTapData[$categoryId]['attribute_codes'] = [];
                        $onTapData[$categoryId]['smart_attributes'] = [];
                    }

                    $smartAttributes = array_combine(['attribute', 'value', 'link'],  $smartAttributes);

                    if (! isset($attributeIdsByCode[$smartAttributes['attribute']])) {
                        $attributeIdsByCode[$smartAttributes['attribute']] =
                            Mage::getSingleton('catalog/product')
                                ->getResource()
                                ->getAttribute($smartAttributes['attribute'])
                                ->getId();
                    }

                    $attributeCode = $smartAttributes['attribute'];
                    $smartAttributes['attribute'] = $attributeIdsByCode[$smartAttributes['attribute']];

                    $onTapData[$categoryId]['attribute_codes'][] = $attributeCode;
                    $onTapData[$categoryId]['smart_attributes'][] = $smartAttributes;
                }

                if (isset($rowData['_ontap_heroproducts'])) {
                    if (! isset($onTapData[$categoryId]['heroproducts'])) {
                        $onTapData[$categoryId]['heroproducts'] = [];
                    }
                    $onTapData[$categoryId]['heroproducts'][] = $rowData['_ontap_heroproducts'];
                }
            }

            if ($onTapData) {
                //flatten data
                foreach ($onTapData as $catId => &$onTapRow) {
                    if (! empty($onTapRow['attribute_codes'])) {
                        $onTapRow['attribute_codes'] = implode(',', array_unique($onTapRow['attribute_codes']));
                    }
                    if (! empty($onTapRow['heroproducts'])) {
                        $onTapRow['heroproducts'] = implode(',', $onTapRow['heroproducts']);
                    }
                    if (! empty($onTapRow['smart_attributes'])) {
                        $onTapRow['smart_attributes'] = serialize($onTapRow['smart_attributes']);
                    }

                    if (count($onTapRow) <= 1) {
                        unset($onTapData[$catId]);
                    }
                }

                //Insert Data
                if ($onTapData) {
                    $this->_connection->insertOnDuplicate($entityTable, $onTapData);
                }
            }
        }
        return $this;
    }
}
