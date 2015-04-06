<?php

class AvS_FastSimpleImport_Test_Model_Category_Import extends EcomDev_PHPUnit_Test_Case
{
    /**
     * @var AvS_FastSimpleImport_Model_Import_Entity_Category
     */
    protected $_model;

    public function setUp()
    {
        $this->_model = $this->getModelMock('fastsimpleimport/import_entity_category', null, false, array(), null, false);
        parent::setUp();
    }

    /**
     * @test
     * @covers AvS_FastSimpleImport_Model_Import_Entity_Category::validateRow
     * @group working
     */
    public function testValidateRow()
    {
        $salt = substr(uniqid(), -6);

        $row = array(
            '_root' => 'Not Default' . $salt,
            '_category' => 'Some cat' . $salt,
        );

        // Assert Validation failed
        $result = $this->_model->validateRow($row, 1);
        $this->assertFalse($result);

        // Assert error message is correct
        $errors = $this->_model->getErrorMessages();
        $this->assertArrayHasKey(AvS_FastSimpleImport_Model_Import_Entity_Category::ERROR_INVALID_ROOT, $errors);
    }
}