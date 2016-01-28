<?php

class AvS_FastSimpleImport_Test_Model_Category_Import extends EcomDev_PHPUnit_Test_Case
{
    /**
     * @var AvS_FastSimpleImport_Model_Import_Entity_Category
     */
    protected $_model;

    public function setUp()
    {
        $this->_model = $this->getModelMock(
            'fastsimpleimport/import_entity_category',
            null,
            false,
            array(),
            null,
            false
        );

        parent::setUp();
    }

    /**
     * @test
     * @covers AvS_FastSimpleImport_Model_Import_Entity_Category::validateRow
     * @uses AvS_FastSimpleImport_Model_Import_Entity_Category::_explodeEscaped
     * @uses AvS_FastSimpleImport_Model_Import_Entity_Category::getRowScope
     * @uses AvS_FastSimpleImport_Model_Import_Entity_Category::_getCategoryName
     * @uses AvS_FastSimpleImport_Model_Import_Entity_Category::_filterRowData
     */
    public function validateRowWrongRoot()
    {
        $row = array(
            // Add a Salt to make sure it does not match any possible leftover from a failed fixture
            '_root' => 'Not Default ' . substr(uniqid(), -6),
            '_category' => 'Some category',
        );

        // Setup my own error message to prevent test for failing if it is changed later
        $this->_model->addMessageTemplate(
            AvS_FastSimpleImport_Model_Import_Entity_Category::ERROR_INVALID_ROOT,
            'Invalid Root'
        );

        // Assert Validation failed
        $result = $this->_model->validateRow($row, 1);
        $this->assertFalse($result);

        // Assert error message is correct
        $errors = $this->_model->getErrorMessages();
        $this->assertArrayHasKey('Invalid Root', $errors);
    }
}