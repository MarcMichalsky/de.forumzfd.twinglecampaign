<?php

use CRM_TwingleCampaign_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use CRM_TwingleCampaign_BAO_TwingleProject as TwingleProject;
use \Civi\Test\Api3TestTrait;

/**
 * # Collection of tests for the TwingleProject class
 *
 * - tests the full TwingleProject circle (from Twingle to database to Twingle)
 * - tests the input validation
 *
 * @group headless
 */
class CRM_TwingleCampaign_BAO_TwingleProjectTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  private $project;

  private $dummyProject;
  private $badDummyProject;

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * @throws \Exception
   */
  public function setUp() {

    // Create project from dummy values
    $this->dummyProject =
      require(E::path() . '/tests/resources/twingle_project_dummy.php');
    $this->badDummyProject =
      require(E::path() . '/tests/resources/twingle_project_bad_dummy.php');
    $this->project = new TwingleProject($this->dummyProject);
    $this->project->create(TRUE);

    parent::setUp();
  }

  /**
   * @throws \CiviCRM_API3_Exception
   */
  public function tearDown() {

    // Delete project
    $this->project->delete();
    unset($this->project);

    parent::tearDown();
  }

  /**
   * ## The full TwingleProject circle
   * This test simulates a TwingleProject that is fetched from the database and
   * than gets instantiated and sent back to Twingle.
   *
   * It is important that all project values sent to the Twingle API have the
   * same data types like when they were retrieved from Twingle. To test this,
   * the export array will be compared to the original dummy array.
   *
   * dummy:array -> project:object -> database -> project:object -> export:array
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public function testFullTwingleProjectCircle() {

    // Get project via API from database
    $project = civicrm_api3(
      'TwingleProject',
      'getsingle',
      ['id' => $this->project->getId()]);
    $project = new TwingleProject($project, $project['id']);

    // Complement project values with dummy values. This is important because
    // not all values coming from the Twingle API are stored in the database,
    // but the Twingle API requires that all parameters are set.
    $project->complement($this->dummyProject);

    // Export project
    $export = $project->export();

    // Check if the project is sent to Twingle in the same state it arrived
    $this->assertEquals(
      $this->dummyProject,
      $export,
      'Export values differ from import values.'
    );
  }

  /**
   * ## Input validation
   * Checks if the input validation works properly.
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public function testInputValidation() {

    // Get project via API from database
    $project = civicrm_api3(
      'TwingleProject',
      'getsingle',
      ['id' => $this->project->getId()]);
    $project = new TwingleProject($project, $project['id']);

    // Check if validation successes with healthy input
    $validation_success = $project->validate();
    $this->assertTrue(
      $validation_success['valid'],
      'Validation failed with healthy inputs.'
    );

    // Update project with values which simulate incorrect user input
    $project->update($this->badDummyProject);

    // Run validation again
    $validation_fail = $project->validate();

    // Check if validation failed (as it should)
    $this->assertFalse(
      $validation_fail['valid'],
      'Validation did not fail as expected.'
    );

    // Check if all 6 wrong inputs were found
    $this->assertCount(
      6, $validation_fail['messages'],
      'Did not find all 6 wrong inputs.'
    );
  }
}

