<?php


namespace CRM\TwingleCampaign\Models;

use CRM_TwingleCampaign_ExtensionUtil as E;
use DateTime;
use CRM\TwingleCampaign\Models\CustomField as CustomField;

include_once E::path() . '/CRM/TwingleCampaign/Upgrader/models/CustomField.php';


class TwingleProject {

  private static $bInitialized = FALSE;

  private $id;

  private $project_id;

  private $values;

  private $timestamp;

  private $settings;

  private static $customFieldMapping;

  /**
   * TwingleProject constructor.
   *
   * @param array $values
   *
   * If values come from CiviCRM Campaign API, it is necessary to
   * translate the custom field names back
   * @param bool $translate
   *
   * @throws \Exception
   */
  public function __construct(array $values, $translate = FALSE) {

    // Import values
    $this->values = $values;

    // Translate values if values come from CiviCRM Campaign API
    if ($translate) {
      $this->values = $this->translateValues(TRUE);
      $this->id = $values['id'];
    }

    // Format data types of the values for import into CiviCRM
    $this->formatForImport($values);

    // Fetch custom field mapping once
    self::init();

  }

  /**
   * Get custom field mapping.
   * This function will be fully executed only once, when the TwingleProject
   * class gets instantiated for the first time.
   *
   * @throws \Exception
   */
  private static function init() {
    if (self::$bInitialized) {
      return;
    }
    self::$customFieldMapping = CustomField::getMapping();
    self::$bInitialized = TRUE;
  }

  /**
   * Create the project as a campaign in CiviCRM if it does not exist
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public function create() {

    // Translate $value keys to custom field names
    $translatedValues = $this->translateValues();

    // Create project if it does not exist yet and give back the result
    if (!$this->exists()) {
      $result = civicrm_api3('Campaign', 'create', $translatedValues);
      $this->id = $result['id'];
      $this->timestamp = $result['last_update'];
      return [
        'title'      => $this->values['title'],
        'id'         => $this->id,
        'project_id' => $this->values['id'],
        'state'      => 'created',
      ];
    }
    // Give information back if project already exists
    return [
      'title'      => $this->values['title'],
      'id'         => is_array($this->id) ? implode(', ', $this->id) : $this->id,
      'project_id' => $this->values['id'],
      'state'      => 'exists',
    ];
  }

  /**
   * Update an existing project
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public function update() {
    // Translate $value keys to custom field names
    $translatedValues = $this->translateValues();

    $result = civicrm_api3('Campaign', 'create', $translatedValues);
    return [
      'title'      => $this->values['title'],
      'id'         => $this->id,
      'project_id' => $this->values['id'],
      'state'      => 'fetched',
    ];
  }

  /**
   * Check if a project already exists
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function exists() {

    // Get custom field names
    $cf_project_id = TwingleProject::$customFieldMapping['twingle_project_id'];
    $cf_last_update = TwingleProject::$customFieldMapping['twingle_project_last_update'];

    $count = FALSE;
    $result = [];

    // If there is more than one campaign for a project, handle the duplicates
    while (!$count) {
      $result = civicrm_api3('Campaign', 'get', [
        'sequential'   => 1,
        'return'       => ['id', $cf_last_update],
        'is_active'    => '1',
        $cf_project_id => $this->values['id'],
      ]);

      if ($result['count'] > 1) {
        TwingleProject::handleDuplicates($result);
      }
      else {
        $count = TRUE;
      }
    }

    if ($result['count'] == 1) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  public static function fetch($id) {
    $result = civicrm_api3('Campaign', 'getsingle', [
      'sequential' => 1,
      'id'         => $id,
    ]);

    return new TwingleProject($result, TRUE);
  }

  /**
   * Deactivate all duplicates but the newest one
   *
   * @param array $result
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function handleDuplicates(array $result) {

    // Sort projects by last_update
    uasort($result['values'], function ($a, $b) {
      return $a['last_update'] <=> $b['last_update'];
    });

    // Delete newest project from array
    array_shift($result['values']);

    // Instantiate projects to deactivate them
    foreach ($result['values'] as $p) {
      $project = TwingleProject::fetch($p['id']);
      $project->deactivate();
    }

  }

  /**
   * Translate $value keys to custom field names
   *
   * @param bool $rev
   *
   * @return array
   */
  private function translateValues($rev = FALSE) {
    $values = [];
    if (!$rev) {
      foreach (TwingleProject::$customFieldMapping as $field => $custom) {
        if (array_key_exists(
          str_replace('twingle_project_', '', $field),
          $this->values)
        ) {
          $values[$custom] = $this->values[str_replace(
            'twingle_project_',
            '',
            $field
          )];
        }
      }
    }
    else {
      foreach (TwingleProject::$customFieldMapping as $field => $custom) {
        if (array_key_exists($custom, $this->values)
        ) {
          $values[str_replace(
            'twingle_project_',
            '',
            $field
          )] = $this->values[$custom];
        }
      }
    }
    // Add necessary values
    $values['campaign_type_id'] = 'twingle_project';
    $values['title'] = $this->values['name'];
    return $values;
  }

  /**
   * Formats values to import them as campaigns
   *
   * @param $values
   */
  private function formatForImport(&$values) {

    // Change timestamp into DateTime string
    if (!empty($values['last_update'])) {
      $date = DateTime::createFromFormat('U', $values['last_update']);
      $values['last_update'] = $date->format('Y-m-d H:i:s');
    }

    // Change event type empty string into 'default'
    if ($values['type'] == '') {
      $values['type'] = 'default';
    }
  }

  /**
   * Formats values to send them to Twingle API
   *
   * @param $values
   */
  private function formatForExport(&$values) {

    // Change DateTime string into timestamp
    if (!empty($values['last_update'])) {
      $date = DateTime::createFromFormat('Y-m-d H:i:s', $values['last_update']);
      $values['last_update'] = $date->getTimestamp();
    }

    // Change event type 'default' into empty string
    if ($values['type'] == 'default') {
      $values['type'] = '';
    }
  }

  /**
   * Deactivate a project
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function deactivate() {
    $result = civicrm_api3('Campaign', 'create', [
      'title'     => $this->values['title'],
      'id'        => $this->id,
      'is_active' => '0',
    ]);

    if ($result['is_error'] == 0) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  public function syncSettings() {

  }

  /**
   * @return mixed
   */
  public function getId() {
    return $this->id;
  }

  /**
   * @param mixed $id
   */
  public function setId($id): void {
    $this->id = $id;
  }

  /**
   * @return mixed
   */
  public function getProjectId() {
    return $this->project_id;
  }

  /**
   * @param mixed $project_id
   */
  public function setProjectId($project_id): void {
    $this->project_id = $project_id;
  }

  /**
   * @return mixed
   */
  public function getTimestamp() {
    return $this->timestamp;
  }

  /**
   * @param mixed $timestamp
   */
  public function setTimestamp($timestamp): void {
    $this->timestamp = $timestamp;
  }




}