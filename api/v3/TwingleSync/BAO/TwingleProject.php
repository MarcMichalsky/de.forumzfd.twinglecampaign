<?php


namespace CRM\TwingleCampaign\BAO;

use CRM_TwingleCampaign_ExtensionUtil as E;
use DateTime;
use CRM\TwingleCampaign\BAO\CustomField as CustomField;

include_once E::path() . '/CRM/TwingleCampaign/Upgrader/BAO/CustomField.php';


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
   * @param array $options
   * Result array of Twingle Api call to
   * https://project.twingle.de/api/$project_id/options
   *
   * @param string $origin
   * Origin of the arrays. It can be one of two constants:
   * TwingleProject::TWINGLE|CIVICRM
   *
   * @throws \Exception
   */
  public function __construct(array $project, array $options, string $origin) {

    // Fetch custom field mapping once
    self::init();

    // If values come from CiviCRM Campaign API
    if ($origin == self::CIVICRM) {

      // Set id (campaign id) attribute
      $this->id = $project['id'];

      // Translate custom field names into Twingle field names
      self::translateCustomFields($project, self::$OUT);

    }
    // If values come from Twingle API
    elseif ($origin == self::TWINGLE) {

      // Translate keys for import
      self::translateKeys($project, self::IN);

      // Format values for import
      self::formatValues($project, self::IN);
      self::formatValues($options, self::IN);

    }

    // Add value for campaign type
    $project['campaign_type_id'] = 'twingle_project';

    // Import project values and options
    $this->values = $project;
    $this->options = $options;
  }


  /**
   * Get custom field mapping.
   * This function will be fully executed only once, when the TwingleProject
   * class gets instantiated for the first time.
   *
   * @throws \Exception
   */
  private static function init() {

    // Initialize custom field mapping
    if (self::$bInitialized) {
      return;
    }
    self::$customFieldMapping = CustomField::getMapping();

    // Initialize json files as arrays
    $file_paths = [
      'translations' => '/api/v3/TwingleSync/resources/dictionary.json',
      'templates'    => '/api/v3/TwingleSync/resources/twingle_api_templates.json',
      'campaigns'    => '/CRM/TwingleCampaign/Upgrader/resources/campaigns.json',
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
        \Civi::log()->error($message);
        throw new \Exception($message);
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
   * @throws \CiviCRM_API3_Exception
   */
  public function create(bool $is_test = FALSE) {

    // Create campaign only if it does not already exist
    if (!$is_test) {

      // Translate Twingle field names into custom field names
      $translatedFields = array_merge($this->options, $this->values);
      self::translateCustomFields($translatedFields, self::IN);

      // Set id
      $translatedFields['id'] = $this->id;

      // Create campaign
      $result = civicrm_api3('Campaign', 'create', $translatedFields);

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
   * @param string $origin
   * Origin of the array. It can be one of two constants:
   *   TwingleProject::TWINGLE|CIVICRM
   *
   * @throws \Exception
   */
  public function update(array $values, array $options, string $origin) {

    if ($origin == self::TWINGLE) {
      // Format values and translate keys
      self::translateKeys($values, self::IN);
      self::formatValues($values, self::IN);

      //Format options and translate keys
      self::translateKeys($options, self::IN);
      self::formatValues($options, self::IN);
    }
    elseif ($origin == self::CIVICRM) {
      $this->id = $values['id'];
      self::translateCustomFields($values, self::OUT);
    }

    // Update values and options
    $this->values = array_merge($this->values, $values);
    $this->options = array_merge($this->options, $options);

  }


  /**
   * Export values. Ensures that only those values will be exported which the
   * Twingle API expects.
   *
   * @return array
   * Array with all values to send to the Twingle API
   *
   * @throws \Exception
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
   * @throws \Exception
   *
   */
  public function exportOptions() {

    $options = $this->options;
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
   * Check if a project already exists
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public function exists() {

    $result = [];

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

      // Split result array into project values and options
      $values_and_options = self::splitValues($result['values'][0]);

      // Set attributes to the values of the existing TwingleProject campaign
      $this->update(
        $values_and_options['values'],
        $values_and_options['options'],
        self::CIVICRM
      );

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
   * @return \CRM\TwingleCampaign\BAO\TwingleProject
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public static function fetch($id) {
    $result = civicrm_api3('Campaign', 'getsingle', [
      'sequential' => 1,
      'id'         => $id,
    ]);

    return new TwingleProject($result, TRUE);
  }


  /**
   * Deactivate all duplicates of a project but the newest one
   *
   * @param array $result
   * The $result array of a civicrm_api3-get-project call
   *
   * @throws \CiviCRM_API3_Exception
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
   * @throws \Exception
   */
  private static function translateKeys(array &$values, string $direction) {

    // Get translations for fields
    $field_translations = self::$translations['fields'];

    // Set the direction of the translation
    if ($direction == self::OUT) {
      $field_translations = array_flip($field_translations);
    }
    // Throw error if $direction constant does not match IN or OUT
    elseif ($direction != self::IN) {
      throw new \Exception(
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
   * @throws \Exception
   */
  private function formatValues(array &$values, string $direction) {

    if ($direction == self::IN) {

      // Change timestamp into DateTime string
      if ($values['last_modified_date']) {
        $values['last_modified_date'] =
          self::getDateTime($values['last_modified_date']);
      }

      // empty project_type to 'default
      if ($values['type']) {
        $values['type'] = $values['type'] == ''
          ? 'default'
          : $values['type'];
      }

      // format donation rhythm
      if (is_array($values['donation_rhythm'])) {
        $tmp = [];
        foreach ($values['donation_rhythm'] as $key => $value) {
          if ($value) {
            $tmp[$key] = $key;
          }
        }
        $values['donation_rhythm'] = \CRM_Utils_Array::implodePadded($tmp);
      }

      // Format project target format
      if (key_exists('has_projecttarget_as_money', $values)) {
      $values['has_projecttarget_as_money'] =
        $values['has_projecttarget_as_money'] ? 'in Euro' : 'percentage';
      }

      // Format contact fields
      if ($values['exclude_contact_fields']) {
        $possible_contact_fields =
          self::$campaigns['custom_fields']
          ['twingle_project_exclude_contact_fields']['option_values'];

        $exclude_contact_fields = explode(',', $values['exclude_contact_fields']);

        foreach ($exclude_contact_fields as $exclude_contact_field) {
          unset($possible_contact_fields[$exclude_contact_field]);
        }

        $values['exclude_contact_fields'] =
          \CRM_Utils_Array::implodePadded($possible_contact_fields);
      }

      // Format languages
      if ($values['languages']) {
        $values['languages'] =
          \CRM_Utils_Array::implodePadded(
            explode(
              ',',
              $values['languages']
            )
          );
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

      // Cast project_target to integer
      $values['project_target'] = (int) $values['project_target'];

    }
    else {

      throw new \Exception(
        "Invalid Parameter $direction for formatValues()"
      );
      // TODO: use specific exception or create own

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
            )]);
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
   * A function that will split one array coming from a TwingleProject campaign
   * into a value array (containing basic project information) and another
   * array containing the project options.
   *
   * @param array $input
   * Array that comes from TwingleProject campaign
   *
   * @return array[]
   * Associative array that contains two arrays: $values & $options
   *
   * @throws \Exception
   */
  private function splitValues(array $input) {

    $values = [];
    $options = [];

    // Get array with template for project values and options
    $values_template = self::$templates['project'];
    $options_template = self::$templates['project_options'];

    // Map array items into $values and $options array
    foreach ($input as $key => $value) {
      if (in_array($key, $values_template)) {
        $values[$key] = $value;
      }
      if (key_exists($key, $options_template)) {
        $options[$key] = $value;
      }
    }

    return [
      'values' => $values,
      'options' => $options
    ];
  }


  /**
   * Deactivate this TwingleProject campaign
   *
   * @return bool
   * TRUE if deactivation was successful
   *
   * @throws \CiviCRM_API3_Exception
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
   * @throws \CiviCRM_API3_Exception
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


  public function syncSettings() {
    // TODO: sync project settings
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
      'title'      => $this->values['title'],
      'id'         => $this->id,
      'project_id' => $this->values['id'],
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
    return self::getTimestamp($this->values['last_modified_date']);
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
