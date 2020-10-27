<?php


namespace CRM\TwingleCampaign\BAO;

use Civi;
use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM_Utils_Array;
use DateTime;
use CRM\TwingleCampaign\BAO\CustomField as CustomField;
use CRM\TwingleCampaign\BAO\TwingleProjectOptions as TwingleProjectOptions;
use Exception;
use CiviCRM_API3_Exception;

include_once E::path() . '/CRM/TwingleCampaign/BAO/CustomField.php';
include_once E::path() . '/CRM/TwingleCampaign/BAO/TwingleProjectOptions.php';


class TwingleProject {

  public const IN = 'IN';

  public const OUT = 'OUT';

  public const CIVICRM = 'CIVICRM';

  public const TWINGLE = 'TWINGLE';

  private static $bInitialized = FALSE;

  private static $customFieldMapping;

  private static $translations;

  private static $campaigns;

  private static $templates;

  private $id;

  private $values;

  private $options;


  /**
   * TwingleProject constructor.
   *
   * @param array $project
   * Result array of Twingle API call to
   * https://project.twingle.de/api/by-organisation/$organisation_id
   *
   * @param string $origin
   * Origin of the arrays. It can be one of two constants:
   * TwingleProject::TWINGLE|CIVICRM
   *
   * @throws Exception
   */
  public function __construct(array $project, string $origin) {

    // Fetch custom field mapping once
    self::init();

    // Create TwingleProjectOptions object
    $this->options = new TwingleProjectOptions($project['options'], $origin);

    // Unset project options in $project array
    unset($project['options']);

    // If values come from CiviCRM Campaign API
    if ($origin == self::CIVICRM) {

      // Set id (campaign id) attribute
      $this->id = $project['id'];

      // Translate custom field names into Twingle field names
      self::translateCustomFields($project, self::OUT);

      // Translate keys and values
      self::formatValues($project, self::OUT);
      self::translateKeys($project, self::OUT);

    }

    // Add value for campaign type
    $project['campaign_type_id'] = 'twingle_project';

    // Set project values attribute
    $this->values = $project;
  }


  /**
   * Get custom field mapping.
   * This function will be fully executed only once, when the TwingleProject
   * class gets instantiated for the first time.
   *
   * @throws Exception
   */
  private static function init() {

    // Initialize custom field mapping
    if (self::$bInitialized) {
      return;
    }
    self::$customFieldMapping = CustomField::getMapping();

    // Initialize json files as arrays
    $file_paths = [
      'translations' => '/CRM/TwingleCampaign/resources/dictionary.json',
      'templates'    => '/CRM/TwingleCampaign/resources/twingle_api_templates.json',
      'campaigns'    => '/CRM/TwingleCampaign/resources/campaigns.json',
    ];

    foreach ($file_paths as $key => $file_path) {

      // Get array from json file
      $file_path = E::path() . $file_path;
      $json_file = file_get_contents($file_path);
      $json_file_name = pathinfo($file_path)['filename'];
      $array = json_decode($json_file, TRUE);

      // Throw and log an error if json file can't be read
      if (!$array) {
        $message = ($json_file_name)
          ? "Could not read json file $json_file_name"
          : "Could not locate json file in path: $file_path";
        Civi::log()->error($message);
        throw new Exception($message);
      }

      // Set attribute
      self::$$key = $array;
    }

    self::$bInitialized = TRUE;

  }


  /**
   * Create the TwingleProject as a campaign in CiviCRM if it does not exist
   *
   * @param bool $is_test
   * If true: don't do any changes
   *
   * @return array
   * Returns a response array that contains title, id, project_id and status
   *
   * @throws CiviCRM_API3_Exception
   * @throws Exception
   */
  public function create(bool $is_test = FALSE) {

    // Create campaign only if it does not already exist
    if (!$is_test) {

      // Prepare project values for import into database
      $values_prepared_for_import = $this->values;
      self::formatValues(
        $values_prepared_for_import,
        self::IN
      );
      self::translateKeys(
        $values_prepared_for_import,
        self::IN
      );
      self::translateCustomFields(
        $values_prepared_for_import,
        self::IN
      );

      // Prepare project option values for import into database
      $options_prepared_for_import = $this->options->getValues();
      TwingleProjectOptions::formatValues(
        $options_prepared_for_import,
        self::IN
      );
      self::translateCustomFields(
        $options_prepared_for_import,
        self::IN
      );

      // Merge project values and project options values
      $merged = array_merge(
        $values_prepared_for_import,
        $options_prepared_for_import
      );

      // Set id
      $merged['id'] = $this->id;

      // Create campaign
      $result = civicrm_api3('Campaign', 'create', $merged);

      // Update id
      $this->id = $result['id'];

      // Check if campaign was created successfully
      if ($result['is_error'] == 0) {
        $response = $this->getResponse('TwingleProject created');
      }
      else {
        $response = $this->getResponse('TwingleProject creation failed');
      }

    }
    // If this is a test, do not create campaign
    else {
      $response = $this->getResponse('TwingleProject not yet created');
    }

    return $response;
  }


  /**
   * Update an existing project
   *
   * @param array $values
   * Array with values to update
   *
   * @param string|null $origin
   * Origin of the array. It can be one of two constants:
   *   TwingleProject::TWINGLE|CIVICRM
   *
   * @throws Exception
   */
  public function update(array $values, string $origin = NULL) {

    if ($origin == self::CIVICRM) {

      // Set project id
      $this->id = $values['id'];

      // Translate custom field names
      self::translateCustomFields($values, self::OUT);

      // Format project values
      self::formatValues($values, self::OUT);

      // Separate options from project values
      self::separateOptions($values);
    }

    // Update project options
    $this->options->update($values['options'], $origin);

    // Unset options array in project values
    unset($values['options']);

    // Update project values
    $this->values = array_merge($this->values, $values);
  }


  /**
   * Export values. Ensures that only those values will be exported which the
   * Twingle API expects.
   *
   * @return array
   * Array with all values to send to the Twingle API
   *
   * @throws Exception
   */
  public function export() {

    $values = $this->values;
    self::formatValues($values, self::OUT);
    self::translateKeys($values, self::OUT);

    // Get template for project
    $project = self::$templates['project'];

    // Replace array items which the Twingle API does not expect
    foreach ($values as $key => $value) {
      if (!in_array($key, $project)) {
        unset($values[$key]);
      }
    }

    return $values;
  }

  /**
   * Export options. Ensures that only those values will be exported which the
   * Twingle API expects. Missing values will get complemented with default
   * values.
   *
   * @return array
   * Array with all options to send to the Twingle API
   *
   * @throws Exception
   *
   */
  public function exportOptions() {

    $options = $this->values['options'];
    self::formatValues($options, self::OUT);
    self::translateKeys($options, self::OUT);

    // Get Template for project options
    $project_options_template = self::$templates['project_options'];

    // Replace array items which the Twingle API does not expect
    foreach ($options as $key => $value) {
      if (!key_exists($key, $project_options_template)) {
        unset($options[$key]);
      }
    }

    // Complement missing options with default values
    return array_merge($project_options_template, $options);
  }


  /**
   * Check if a TwingleProject campaign already exists and if so set attributes
   * to the values of the existing campaign.
   *
   * @return bool
   * Returns TRUE id the TwingleProject campaign already exists
   *
   * @throws CiviCRM_API3_Exception
   *
   * @throws Exception
   */
  public function exists() {

    $result = [];
    $single = FALSE;

    // Get custom field name for project_id
    $cf_project_id = TwingleProject::$customFieldMapping['twingle_project_id'];

    // If there is more than one campaign for a project, handle the duplicates
    while (!$single) {
      $result = civicrm_api3('Campaign', 'get', [
        'sequential'   => 1,
        'is_active'    => 1,
        $cf_project_id => $this->values['id'],
      ]);

      if ($result['count'] > 1) {
        TwingleProject::handleDuplicates($result);
      }
      else {
        $single = TRUE;
      }
    }

    // If the campaign for the TwingleProject already exists, some of the
    // project's attributes must be updated from the campaign
    if ($result['count'] == 1) {

      // Extract project values from result array
      $values = $result['values'][0];

      // Set campaign id attribute
      $this->id = $values['id'];

      // Translate custom field names back
      self::translateCustomFields($values, self::OUT);

      // Translate keys from CiviCRM format to Twingle format
      self::translateKeys($values, self::OUT);

      // Separate options from project values
      self::separateOptions($values);

      // Translate keys from Twingle format to CiviCRM format
      self::translateKeys($values, self::IN);

      // Set attributes to the values of the existing TwingleProject campaign
      // to reflect the state of the actual campaign in the database
      $this->update($values, self::TWINGLE);

      return TRUE;
    }
    else {
      return FALSE;
    }
  }


  /**
   * Instantiate an existing project by campaign id
   *
   * @param $id
   *
   * @return TwingleProject
   * @throws CiviCRM_API3_Exception
   * @throws Exception
   */
  public static function fetch($id) {
    $result = civicrm_api3('Campaign', 'getsingle', [
      'sequential' => 1,
      'id'         => $id,
    ]);

    // Separate options from project values
    self::separateOptions($result['values']);

    return new TwingleProject(
      $result['values'],
      self::CIVICRM
    );
  }


  /**
   * Deactivate all duplicates of a project but the newest one
   *
   * @param array $result
   * The $result array of a civicrm_api3-get-project call
   *
   * @throws CiviCRM_API3_Exception
   */
  private function handleDuplicates(array $result) {

    // Sort projects ascending by the value of the last_modified_date
    uasort($result['values'], function ($a, $b) {
      return $a['last_modified_date'] <=> $b['last_modified_date'];
    });

    // Delete the newest project from array to keep it active
    array_shift($result['values']);

    // deactivate the projects
    foreach ($result['values'] as $p) {
      self::deactivateById($p['id']);
    }

  }


  /**
   * Translate array keys between CiviCRM Campaigns and Twingle
   *
   * @param array $values
   * array of which keys shall be translated
   *
   * @param string $direction
   * TwingleProject::IN -> translate array keys from Twingle format into
   * CiviCRM format <br>
   * TwingleProject::OUT -> translate array keys from CiviCRM format into
   * Twingle format
   *
   * @throws Exception
   */
  public static function translateKeys(array &$values, string $direction) {

    // Get translations for fields
    $field_translations = self::$translations['fields'];

    // Set the direction of the translation
    if ($direction == self::OUT) {
      $field_translations = array_flip($field_translations);
    }
    // Throw error if $direction constant does not match IN or OUT
    elseif ($direction != self::IN) {
      throw new Exception(
        "Invalid Parameter $direction for translateKeys()"
      );
      // TODO: use specific exception or create own
    }

    // Translate keys
    foreach ($field_translations as $origin => $translation) {
      $values[$translation] = $values[$origin];
      unset($values[$origin]);
    }
  }


  /**
   * Translate values between CiviCRM Campaigns and Twingle
   *
   * @param array $values
   * array of which values shall be translated
   *
   * @param string $direction
   * TwingleProject::IN -> translate array values from Twingle to CiviCRM <br>
   * TwingleProject::OUT -> translate array values from CiviCRM to Twingle
   *
   * @throws Exception
   */
  private function formatValues(array &$values, string $direction) {

    if ($direction == self::IN) {

      // Change timestamp into DateTime string
      if ($values['last_update']) {
        $values['last_update'] =
          self::getDateTime($values['last_update']);
      }

      // empty project_type to 'default'
      if (!$values['type']) {
        $values['type'] = 'default';
      }
    }
    elseif ($direction == self::OUT) {

      // Change DateTime string into timestamp
      $values['last_modified_date'] =
        self::getTimestamp($values['last_modified_date']);

      // default project_type to ''
      $values['type'] = $values['type'] == 'default'
        ? ''
        : $values['type'];

    }
    else {

      throw new Exception(
        "Invalid Parameter $direction for formatValues()"
      );

    }
  }


  /**
   * Translate between Twingle field names and custom field names
   *
   * @param array $values
   * array of which keys shall be translated
   *
   * @param string $direction
   * TwingleProject::IN -> translate field names into custom field names <br>
   * TwingleProject::OUT -> translate custom field names into Twingle field
   * names
   *
   */
  private static function translateCustomFields(array &$values, string $direction) {

    // Translate from Twingle field name to custom field name
    if ($direction == self::IN) {

      foreach (TwingleProject::$customFieldMapping as $field => $custom) {

        if (array_key_exists(
          str_replace(
            'twingle_project_',
            '',
            $field
          ),
          $values)
        ) {

          $values[$custom] = $values[str_replace(
            'twingle_project_',
            '',
            $field
          )];

          unset($values[str_replace(
              'twingle_project_',
              '',
              $field
            )]
          );
        }
      }
    }
    // Translate from custom field name to Twingle field name
    elseif ($direction == self::OUT) {

      foreach (TwingleProject::$customFieldMapping as $field => $custom) {

        if (array_key_exists(
          $custom,
          $values
        )
        ) {
          $values[str_replace(
            'twingle_project_',
            '',
            $field
          )] = $values[$custom];
          unset($values[$custom]);
        }
      }
    }
  }

  /**
   * A function that picks all option values from the values array and puts them
   * into an own array.
   *
   * @param array $values
   */
  private static function separateOptions(array &$values) {

    $options = [];

    // Get array with template for project values and options
    $options_template = self::$templates['project_options'];

    // Map array items into $values and $options array
    foreach ($values as $key => $value) {
      if (key_exists($key, $options_template)) {
        $options[$key] = $value;
      }
    }

    // Insert options array into values array
    $values['options'] = $options;
  }


  /**
   * Deactivate this TwingleProject campaign
   *
   * @return bool
   * TRUE if deactivation was successful
   *
   * @throws CiviCRM_API3_Exception
   */
  public function deactivate() {

    return self::deactivateByid($this->id);

  }

  /**
   * Deactivate a TwingleProject campaign by ID
   *
   * @param $id
   * ID of the TwingleProject campaign that should get deactivated
   *
   * @return bool
   * TRUE if deactivation was successful
   *
   * @throws CiviCRM_API3_Exception
   */
  public static function deactivateById($id) {

    $result = civicrm_api3('Campaign', 'getsingle', [
      'id'         => $id,
      'sequential' => 1,
    ]);

    $result = civicrm_api3('Campaign', 'create', [
      'title'     => $result['title'],
      'id'        => $id,
      'is_active' => '0',
    ]);

    // Return TRUE if TwingleProject campaign was deactivated successfully
    if ($result['is_error'] == 0) {
      return TRUE;
    }
    // Return FALSE if deactivation failed
    else {
      return FALSE;
    }

  }


  /**
   * Get a response that describes the status of a TwingleProject
   *
   * @param string $status
   * status of the TwingleProject you want the response for
   *
   * @return array
   * Returns a response array that contains title, id, project_id and status
   */
  public function getResponse(string $status) {
    return [
      'title'      => $this->values['name'],
      'id'         => (int) $this->id,
      'project_id' => (int) $this->values['id'],
      'status'     => $status,
    ];
  }


  /**
   * Validates $input to be either a DateTime string or an Unix timestamp
   *
   * @param $input
   * Pass a DateTime string or a Unix timestamp
   *
   * @return int
   * Returns a Unix timestamp or NULL if $input is invalid
   */
  public static function getTimestamp($input) {

    // Check whether $input is a Unix timestamp
    if (
    $dateTime = DateTime::createFromFormat('U', $input)
    ) {
      return $input;
    }
    // ... or a DateTime string
    elseif (
    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $input)
    ) {
      return $dateTime->getTimestamp();
    }
    // ... or invalid
    else {
      return NULL;
    }

  }


  /**
   * Validates $input to be either a DateTime string or an Unix timestamp
   *
   * @param $input
   * Pass a DateTime string or a Unix timestamp
   *
   * @return string
   * Returns a DateTime string or NULL if $input is invalid
   */
  public static function getDateTime($input) {

    // Check whether $input is a Unix timestamp
    if (
    $dateTime = DateTime::createFromFormat('U', $input)
    ) {
      return $dateTime->format('Y-m-d H:i:s');
    }
    // ... or a DateTime string
    elseif (
    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $input)
    ) {
      return $input;
    }
    // ... or invalid
    else {
      return NULL;
    }

  }


  /**
   * Return a timestamp of the last update of the TwingleProject
   *
   * @return int|null
   */
  public function lastUpdate() {
    $lastProjectUpdate = self::getTimestamp($this->values['last_modified_date']);
    $lastOptionsUpdate = self::getTimestamp($this->options->lastUpdate());
    $lastUpdate = $lastProjectUpdate > $lastOptionsUpdate
      ? $lastProjectUpdate
      : $lastOptionsUpdate;
    return self::getTimestamp($lastUpdate);
  }


  /**
   * Returns the project_id of a TwingleProject
   *
   * @return int
   */
  public function getProjectId() {
    return $this->values['id'];
  }


  /**
   * @return mixed
   */
  public function getId() {
    return $this->id;
  }

}
