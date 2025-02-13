<?php

namespace SilverStripe\Forms\Tests\GridField;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\CSSContentParser;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\Tests\GridField\GridFieldDetailFormTest\Category;
use SilverStripe\Forms\Tests\GridField\GridFieldDetailFormTest\CategoryController;
use SilverStripe\Forms\Tests\GridField\GridFieldDetailFormTest\GroupController;
use SilverStripe\Forms\Tests\GridField\GridFieldDetailFormTest\PeopleGroup;
use SilverStripe\Forms\Tests\GridField\GridFieldDetailFormTest\Person;
use SilverStripe\Forms\Tests\GridField\GridFieldDetailFormTest\PolymorphicPeopleGroup;
use SilverStripe\Forms\Tests\GridField\GridFieldDetailFormTest\TestController;

class GridFieldDetailFormTest extends FunctionalTest
{
    protected static $fixture_file = 'GridFieldDetailFormTest.yml';

    protected static $extra_dataobjects = [
        Person::class,
        PeopleGroup::class,
        PolymorphicPeopleGroup::class,
        Category::class,
    ];

    protected static $extra_controllers = [
        CategoryController::class,
        TestController::class,
        GroupController::class,
    ];

    protected static $disable_themes = true;

    public function testValidator()
    {
        $this->logInWithPermission('ADMIN');

        $response = $this->get('GridFieldDetailFormTest_Controller');
        $this->assertFalse($response->isError());
        $parser = new CSSContentParser($response->getBody());
        $addlinkitem = $parser->getBySelector('.grid-field .new-link');
        $addlink = (string) $addlinkitem[0]['href'];

        $response = $this->get($addlink);
        $this->assertFalse($response->isError());

        $parser = new CSSContentParser($response->getBody());
        $addform = $parser->getBySelector('#Form_ItemEditForm');
        $addformurl = (string) $addform[0]['action'];

        $response = $this->post(
            $addformurl,
            [
                'FirstName' => 'Jeremiah',
                'ajax' => 1,
                'action_doSave' => 1
            ]
        );

        $parser = new CSSContentParser($response->getBody());
        $errors = $parser->getBySelector('span.required');
        $this->assertEquals(1, count($errors ?? []));

        $response = $this->post(
            $addformurl,
            [
                'ajax' => 1,
                'action_doSave' => 1
            ]
        );

        $parser = new CSSContentParser($response->getBody());
        $errors = $parser->getBySelector('span.required');
        $this->assertEquals(2, count($errors ?? []));
    }

    public function testAddForm()
    {
        $this->logInWithPermission('ADMIN');
        $group = PeopleGroup::get()
            ->filter('Name', 'My Group')
            ->sort('Name')
            ->First();
        $count = $group->People()->Count();

        $response = $this->get('GridFieldDetailFormTest_Controller');
        $this->assertFalse($response->isError());
        $parser = new CSSContentParser($response->getBody());
        $addlinkitem = $parser->getBySelector('.grid-field .new-link');
        $addlink = (string) $addlinkitem[0]['href'];

        $response = $this->get($addlink);
        $this->assertFalse($response->isError());

        $parser = new CSSContentParser($response->getBody());
        $addform = $parser->getBySelector('#Form_ItemEditForm');
        $addformurl = (string) $addform[0]['action'];

        $response = $this->post(
            $addformurl,
            [
                'FirstName' => 'Jeremiah',
                'Surname' => 'BullFrog',
                'action_doSave' => 1
            ]
        );
        $this->assertFalse($response->isError());

        $group = PeopleGroup::get()
            ->filter('Name', 'My Group')
            ->sort('Name')
            ->First();
        $this->assertEquals($count + 1, $group->People()->Count());
    }

    public function testAddFormWithPolymorphicHasOne()
    {
        // Log in for permissions check
        $this->logInWithPermission('ADMIN');
        // Prepare gridfield and other objects
        $group = new PolymorphicPeopleGroup();
        $group->write();
        $gridField = $group->getCMSFields()->dataFieldByName('People');
        $gridField->setForm(new Form());
        $detailForm = $gridField->getConfig()->getComponentByType(GridFieldDetailForm::class);
        $record = new Person();

        // Trigger creation of the item edit form
        $reflectionDetailForm = new \ReflectionClass($detailForm);
        $reflectionMethod = $reflectionDetailForm->getMethod('getItemRequestHandler');
        $reflectionMethod->setAccessible(true);
        $itemrequest = $reflectionMethod->invoke($detailForm, $gridField, $record, new Controller());
        $itemrequest->ItemEditForm();

        // The polymorphic values should be pre-loaded
        $this->assertEquals(PolymorphicPeopleGroup::class, $record->PolymorphicGroupClass);
        $this->assertEquals($group->ID, $record->PolymorphicGroupID);
    }

    public function testViewForm()
    {
        $this->logInWithPermission('ADMIN');

        $response = $this->get('GridFieldDetailFormTest_Controller');
        $parser   = new CSSContentParser($response->getBody());

        $viewLink = $parser->getBySelector('.ss-gridfield-items .first .view-link');
        $viewLink = (string) $viewLink[0]['href'];

        $response = $this->get($viewLink);
        $parser   = new CSSContentParser($response->getBody());

        $firstName = $parser->getBySelector('#Form_ItemEditForm_FirstName');
        $surname   = $parser->getBySelector('#Form_ItemEditForm_Surname');

        $this->assertFalse($response->isError());
        $this->assertEquals('Jane', (string) $firstName[0]);
        $this->assertEquals('Doe', (string) $surname[0]);
    }

    public function testEditForm()
    {
        $this->logInWithPermission('ADMIN');
        $group = PeopleGroup::get()
            ->filter('Name', 'My Group')
            ->sort('Name')
            ->First();
        $firstperson = $group->People()->First();
        $this->assertTrue($firstperson->Surname != 'Baggins');

        $response = $this->get('GridFieldDetailFormTest_Controller');
        $this->assertFalse($response->isError());
        $parser = new CSSContentParser($response->getBody());
        $editlinkitem = $parser->getBySelector('.ss-gridfield-items .first .edit-link');
        $editlink = (string) $editlinkitem[0]['href'];

        $response = $this->get($editlink);
        $this->assertFalse($response->isError());

        $parser = new CSSContentParser($response->getBody());
        $editform = $parser->getBySelector('#Form_ItemEditForm');
        $editformurl = (string) $editform[0]['action'];

        $response = $this->post(
            $editformurl,
            [
                'FirstName' => 'Bilbo',
                'Surname' => 'Baggins',
                'action_doSave' => 1
            ]
        );
        $this->assertFalse($response->isError());

        $group = PeopleGroup::get()
            ->filter('Name', 'My Group')
            ->sort('Name')
            ->First();
        $this->assertListContains([['Surname' => 'Baggins']], $group->People());
    }

    public function testEditFormWithManyMany()
    {
        $this->logInWithPermission('ADMIN');

        // Edit the first person
        $response = $this->get('GridFieldDetailFormTest_CategoryController');
        // Find the link to add a new favourite group
        $parser = new CSSContentParser($response->getBody());
        $addLink = $parser->getBySelector('#Form_Form_testgroupsfield .new-link');
        $addLink = (string) $addLink[0]['href'];

        // Add a new favourite group
        $response = $this->get($addLink);
        $parser = new CSSContentParser($response->getBody());
        $addform = $parser->getBySelector('#Form_ItemEditForm');
        $addformurl = (string) $addform[0]['action'];

        $response = $this->post(
            $addformurl,
            [
                'Name' => 'My Favourite Group',
                'ajax' => 1,
                'action_doSave' => 1
            ]
        );
        $this->assertFalse($response->isError());

        $person = $this->objFromFixture(Person::class, 'jane');
        $favouriteGroup = $person->FavouriteGroups()->first();

        $this->assertInstanceOf(PeopleGroup::class, $favouriteGroup);
    }

    public function testEditFormWithManyManyExtraData()
    {
        $this->logInWithPermission('ADMIN');

        // Lists all categories for a person
        $response = $this->get('GridFieldDetailFormTest_CategoryController');
        $this->assertFalse($response->isError());
        $parser = new CSSContentParser($response->getBody());
        $editlinkitem = $parser->getBySelector('.ss-gridfield-items .first .edit-link');
        $editlink = (string) $editlinkitem[0]['href'];

        // Edit a single category, incl. manymany extrafields added manually
        // through GridFieldDetailFormTest_CategoryController
        $response = $this->get($editlink);
        $this->assertFalse($response->isError());
        $parser = new CSSContentParser($response->getBody());
        $editform = $parser->getBySelector('#Form_ItemEditForm');
        $editformurl = (string) $editform[0]['action'];

        $manyManyField = $parser->getByXpath('//*[@id="Form_ItemEditForm"]//input[@name="ManyMany[IsPublished]"]');
        $this->assertTrue((bool)$manyManyField);

        // Test save of IsPublished field
        $response = $this->post(
            $editformurl,
            [
                'Name' => 'Updated Category',
                'ManyMany' => [
                    'IsPublished' => 1,
                    'PublishedBy' => 'Richard'
                ],
                'action_doSave' => 1
            ]
        );
        $this->assertFalse($response->isError());
        $person = $this->objFromFixture(Person::class, 'jane');
        $category = $person->Categories()->filter(['Name' => 'Updated Category'])->First();
        $this->assertEquals(
            [
                'IsPublished' => 1,
                'PublishedBy' => 'Richard'
            ],
            $person->Categories()->getExtraData('', $category->ID)
        );

        // Test update of value with falsey value
        $response = $this->post(
            $editformurl,
            [
                'Name' => 'Updated Category',
                'ManyMany' => [
                    'PublishedBy' => ''
                ],
                'action_doSave' => 1
            ]
        );
        $this->assertFalse($response->isError());

        $person = $this->objFromFixture(Person::class, 'jane');
        $category = $person->Categories()->filter(['Name' => 'Updated Category'])->First();
        $this->assertEquals(
            [
                'IsPublished' => 0,
                'PublishedBy' => ''
            ],
            $person->Categories()->getExtraData('', $category->ID)
        );
    }

    public function testNestedEditForm()
    {
        $this->logInWithPermission('ADMIN');

        $group = $this->objFromFixture(PeopleGroup::class, 'group');
        $person = $group->People()->First();
        $category = $person->Categories()->First();

        // Get first form (GridField managing PeopleGroup)
        $response = $this->get('GridFieldDetailFormTest_GroupController');
        $this->assertFalse($response->isError());
        $parser = new CSSContentParser($response->getBody());

        $groupEditLink = $parser->getByXpath(
            '//tr[contains(@class, "ss-gridfield-item") and contains(@data-id, "'
            . $group->ID . '")]//a'
        );
        $this->assertEquals(
            'GridFieldDetailFormTest_GroupController/Form/field/testfield/item/' . $group->ID . '/edit',
            (string)$groupEditLink[0]['href']
        );

        // Get second level form (GridField managing Person)
        $response = $this->get((string)$groupEditLink[0]['href']);
        $this->assertFalse($response->isError());
        $parser = new CSSContentParser($response->getBody());
        $personEditLink = $parser->getByXpath(
            '//fieldset[@id="Form_ItemEditForm_People"]' .
            '//tr[contains(@class, "ss-gridfield-item") and contains(@data-id, "' . $person->ID . '")]//a'
        );
        $this->assertEquals(
            sprintf(
                '/GridFieldDetailFormTest_GroupController/Form/field/testfield/item/%d/ItemEditForm/field/People'
                . '/item/%d/edit%s',
                $group->ID,
                $person->ID,
                '?gridState-People-1=%7B%22GridFieldAddRelation%22%3Anull%7D'
            ),
            (string)$personEditLink[0]['href']
        );

        // Get third level form (GridField managing Category)
        $response = $this->get((string)$personEditLink[0]['href']);
        $this->assertFalse($response->isError());
        $parser = new CSSContentParser($response->getBody());
        $categoryEditLink = $parser->getByXpath(
            '//fieldset[@id="Form_ItemEditForm_Categories"]'
            . '//tr[contains(@class, "ss-gridfield-item") and contains(@data-id, "' . $category->ID . '")]//a'
        );
        $this->assertEquals(
            sprintf(
                '/GridFieldDetailFormTest_GroupController/Form/field/testfield/item/%d/ItemEditForm/field/People'
                . '/item/%d/ItemEditForm/field/Categories/item/%d/edit%s',
                $group->ID,
                $person->ID,
                $category->ID,
                '?gridState-Categories-2=%7B%22GridFieldAddRelation%22%3Anull%7D'
            ),
            (string)$categoryEditLink[0]['href']
        );

        // Fourth level form would be a Category detail view
    }

    public function testCustomItemRequestClass()
    {
        $this->logInWithPermission('ADMIN');

        $component = new GridFieldDetailForm();
        $this->assertEquals('SilverStripe\\Forms\\GridField\\GridFieldDetailForm_ItemRequest', $component->getItemRequestClass());
        $component->setItemRequestClass('GridFieldDetailFormTest_ItemRequest');
        $this->assertEquals('GridFieldDetailFormTest_ItemRequest', $component->getItemRequestClass());
    }

    public function testItemEditFormCallback()
    {
        $this->logInWithPermission('ADMIN');

        $category = new Category();
        $component = new GridFieldDetailForm();
        $component->setItemEditFormCallback(
            function ($form, $component) {
                $form->Fields()->push(new HiddenField('Callback'));
            }
        );
        // Note: A lot of scaffolding to execute the tested logic,
        // due to the coupling of form creation with itemRequest handling (and its context)
        $itemRequest = new GridFieldDetailForm_ItemRequest(
            GridField::create('Categories', 'Categories'),
            $component,
            $category,
            Controller::curr(),
            'Form'
        );
        $itemRequest->setRequest(Controller::curr()->getRequest());
        $form = $itemRequest->ItemEditForm();
        $this->assertNotNull($form->Fields()->fieldByName('Callback'));
    }

    /**
     * Tests that a has-many detail form is pre-populated with the parent ID.
     */
    public function testHasManyFormPrePopulated()
    {
        $group = $this->objFromFixture(
            PeopleGroup::class,
            'group'
        );

        $this->logInWithPermission('ADMIN');

        $response = $this->get('GridFieldDetailFormTest_Controller');
        $parser = new CSSContentParser($response->getBody());
        $addLink = $parser->getBySelector('.grid-field .new-link');
        $addLink = (string) $addLink[0]['href'];

        $response = $this->get($addLink);
        $parser = new CSSContentParser($response->getBody());
        $title = $parser->getBySelector('#Form_ItemEditForm_GroupID_Holder span');
        $id = $parser->getBySelector('#Form_ItemEditForm_GroupID_Holder input');

        $this->assertEquals($group->Name, (string) $title[0]);
        $this->assertEquals($group->ID, (string) $id[0]['value']);
    }

    public function testRedirectMissingRecords()
    {
        $origAutoFollow = $this->autoFollowRedirection;
        $this->autoFollowRedirection = false;

        // GridField is filtered people in "My Group", which doesn't include "jack"
        $included = $this->objFromFixture(Person::class, 'joe');
        $excluded = $this->objFromFixture(Person::class, 'jack');

        $response = $this->get(sprintf(
            'GridFieldDetailFormTest_Controller/Form/field/testfield/item/%d/edit',
            $included->ID
        ));
        $this->assertFalse(
            $response->isRedirect(),
            'Existing records are not redirected'
        );

        $response = $this->get(sprintf(
            'GridFieldDetailFormTest_Controller/Form/field/testfield/item/%d/edit',
            $excluded->ID
        ));
        $this->assertTrue(
            $response->isRedirect(),
            'Non-existing records are redirected'
        );

        $this->autoFollowRedirection = $origAutoFollow;
    }
}
