<?php  namespace Jacopo\Authentication\Tests;

use Event;
use Jacopo\Authentication\Classes\CustomProfile\Repository\CustomProfileRepository;
use Jacopo\Authentication\Models\ProfileField;
use Jacopo\Authentication\Models\ProfileFieldType;

/**
 * Test CustomProfileTest
 *
 * @author jacopo beschi jacopo@jacopobeschi.com
 */
class CustomProfileRepositoryTest extends DbTestCase {

    protected $custom_profile_1;
    protected $profile_id_1;
    protected $custom_profile_2;
    protected $profile_id_2;

    public function setUp()
    {
        parent::setUp();

        $this->profile_id_1 = 1;
        $this->custom_profile_1 = new CustomProfileRepository($this->profile_id_1);
        $this->profile_id_2 = 2;
        $this->custom_profile_2 = new CustomProfileRepository($this->profile_id_2);
    }

    /**
     * @test
     **/
    public function canShowAllCustomFieldsType()
    {
        $this->stopPermissionCheckEvent();
        $profile_type = ProfileFieldType::create(["description" => "invoice number"]);

        $profile_types = $this->custom_profile_1->getAllTypes();

        $this->objectHasAllArrayAttributes($profile_type->toArray(), $profile_types->first());
    }

    /**
     * @test
     **/
    public function canAddCustomFieldType()
    {
        $this->stopPermissionCheckEvent();
        $description   = "custom field type";

        $this->custom_profile_1->addNewType($description);

        $profile_types = $this->custom_profile_1->getAllTypes();
        $this->assertCount(1,$profile_types);
    }

    /**
     * @test
     **/
    public function canAddCustomField()
    {
        $this->stopPermissionCheckEvent();
        $field_value = "value";
        $profile_type_field_id = 1;

        $this->custom_profile_1->setField($profile_type_field_id, $field_value);

        $created_profile = ProfileField::first();
        $this->assertEquals($field_value, $created_profile->value);
    }
    
    /**
     * @test
     **/
    public function itThrowEventOnAddFieldType()
    {
        $found = false;
        Event::listen('customprofile.creating', function() use(&$found){ $found = true; return false;},100);

        $description   = "custom field type";
        $this->custom_profile_1->addNewType($description);

        $this->assertTrue($found);
    }

    /**
     * @test
     **/
    public function canDeleteCustomFieldTypeAndValues()
    {
        $this->stopPermissionCheckEvent();
        $description  = "description";
        $profile_type = $this->custom_profile_1->addNewType($description);
        $profile_type_field_id = $profile_type->id;
        $profile_1 = 2;
        ProfileField::create([
                             "profile_id"            => $profile_1,
                             "profile_field_type_id" => $profile_type_field_id,
                             "value"                 => "value1"
                             ]);

        $profile_2 = 1;
        ProfileField::create([
                             "profile_id"            => $profile_2,
                             "profile_field_type_id" => $profile_type_field_id,
                             "value"                 => "value2"
                             ]);

        $this->custom_profile_1->deleteType($profile_type_field_id);

        $this->assertTrue($this->custom_profile_1->getAllTypes()->isEmpty());
        $fields_found = $this->custom_profile_1->getAllTypesWithValues();
        $this->assertCount(0,$fields_found);
    }

    /**
     * @test
     **/
    public function itThrowEventOnDeleteFieldType()
    {
        $found = false;
        $this->stopPermissionCheckCreate();
        Event::listen('customprofile.deleting', function() use(&$found){ $found = true; return false;},100);

        $description  = "description";
        $profile_type = $this->custom_profile_1->addNewType($description);
        $profile_type_field_id = $profile_type->id;
        $this->custom_profile_1->deleteType($profile_type_field_id);


        $this->assertTrue($found);
    }

    /**
     * @test
     * @expectedException \Illuminate\Database\Eloquent\ModelNotFoundException
     **/
    public function throwsExceptionOnDeleteTypeIfNotExists()
    {
        $this->stopPermissionCheckEvent();
        $invalid_id = 11;
        $this->custom_profile_1->deleteType($invalid_id);
    }

    /**
     * @test
     **/
    public function canUpdateCustomField()
    {
        $this->stopPermissionCheckEvent();
        $field_value = "value";
        $profile_type_field_id = 1;
        $this->createCustomField($profile_type_field_id, $field_value);

        $new_value = "value2";
        $this->custom_profile_1->setField($profile_type_field_id, $new_value);

        $total_fields = 1;
        $this->assertEquals($total_fields, ProfileField::all()->count());
        $this->assertEquals($new_value, ProfileField::first()->value);
    }
    
    /**
     * @test
     **/
    public function updateOnlyTheUserProfileCustomField()
    {
        $this->stopPermissionCheckEvent();
        $profile_type_field_id = 1;
        $field_value_1 = "value1";
        $field_value_2 = "value2";

        $this->custom_profile_1->setField($profile_type_field_id, $field_value_1);
        $this->custom_profile_2->setField($profile_type_field_id, $field_value_2);

        $profile_fields = ProfileField::get();
        $this->assertEquals($field_value_1, $profile_fields[0]->value);
        $this->assertEquals($field_value_2, $profile_fields[1]->value);
    }

    /**
     * @test
     **/
    public function canGetAllTypesWithTheirAssociatedValueIfExists()
    {
        $this->stopPermissionCheckEvent();
        $profile_type = $this->createTwoProfileTypes();
        $value1 = "value";
        $this->custom_profile_1->setField($profile_type->id, $value1);
        $value2 = "value2";
        $this->custom_profile_2->setField($profile_type->id, $value2);

        $created_fields = $this->custom_profile_1->getAllTypesWithValues();

        $this->assertCount(2, $created_fields);
        $this->assertEquals($created_fields[0]->value, $value1);
        $this->assertNull($created_fields[1]->value);
        $this->assertEquals(1, $created_fields[0]->id);
        $this->assertEquals(2, $created_fields[1]->id);

        $created_fields_2 = $this->custom_profile_2->getAllTypesWithValues();

        $this->assertCount(2, $created_fields_2);
        $this->assertEquals($created_fields_2[0]->value, $value2);
        $this->assertNull($created_fields_2[1]->value);
        $this->assertEquals(1, $created_fields_2[0]->id);
        $this->assertEquals(2, $created_fields_2[1]->id);
    }

    /**
     * @return mixed
     */
    protected function createTwoProfileTypes()
    {
        $type_description1 = "custom field type with value";
        $profile_type      = $this->custom_profile_1->addNewType($type_description1);
        $type_description2 = "custom field type without value";
        $this->custom_profile_1->addNewType($type_description2);
        return $profile_type;
    }

    /**
     * @test
     **/
    public function canGetAllFieldsOfAProfile()
    {
        $this->stopPermissionCheckEvent();
        $profile_type_field_id_1 = 1;
        $value_1 = "value1";
        $this->createCustomField($profile_type_field_id_1, $value_1);
        $profile_type_field_id_2 = 2;
        $value_2 = "value2";
        $this->createCustomField($profile_type_field_id_2, $value_2);

        $fields = $this->custom_profile_1->getAllFields();
        $total_fields = 2;

        $this->assertEquals($total_fields, $fields->count());
        $this->assertEquals($value_1, $fields[0]->value);
        $this->assertEquals($value_2, $fields[1]->value);
    }

    /**
     * @param $profile_id
     * @param $profile_type_field_id
     * @param $field_value
     */
    protected function createCustomField($profile_type_field_id, $field_value)
    {
        ProfileField::create([
                             "profile_id"            => $this->profile_id_1,
                             "profile_field_type_id" => $profile_type_field_id,
                             "value"                 => $field_value
                             ]);
    }


    /**
     * @return mixed
     */
    protected function stopPermissionCheckEvent()
    {
        $this->stopPermissionCheckDelete();
        $this->stopPermissionCheckCreate();
    }

    /**
     * @return mixed
     */
    protected function stopPermissionCheckDelete()
    {
        return Event::listen(['customprofile.deleting'], function () { return false; }, 100);
    }

    protected function stopPermissionCheckCreate()
    {
        Event::listen(['customprofile.creating',], function () { return false; }, 100);
    }
}
 