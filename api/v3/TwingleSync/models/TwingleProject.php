<?php


namespace CRM\TwingleCampaign\Models;

use CRM_TwingleCampaign_ExtensionUtil as E;
use DateTime;
use CRM\TwingleCampaign\Models\CustomField as CustomField;

include_once E::path() . '/CRM/TwingleCampaign/Upgrader/models/CustomField.php';


class TwingleProject {

  public const IN = 'IN';

  public const OUT = 'OUT';

  public const CIVICRM = 'CIVICRM';

  public const TWINGLE = 'TWINGLE';

  private static $bInitialized = FALSE;

  private static $customFieldMapping;

  private $id;

  private $values;

  private $settings;


  /**
   * TwingleProject constructor.
   *
   * @param array $values
   * Array of values from which to create a TwingleProject
   *
   * @param string $origin
   * Origin of the array. It can be one of two constants:
   *   TwingleProject::TWINGLE|CIVICRM
   */
  public function __construct(array $values, string $origin) {

    // If values come from CiviCRM Campaign API
    if ($origin == self::CIVICRM) {

      // Set id (campaign id) attribute
      $this->id = $values['id'];

      // Translate custom field names into Twingle field names
      self::translateCustomFields($values, self::$OUT);

    }
    // If values come from Twingle API
    elseif ($origin == self::TWINGLE) {

      // Translate keys for import
      self::translateKeys($values, self::IN);

      // Format values for import
      self::formatValues($values, self::IN);

    }

    // Add value for campaign type
    $values['campaign_type_id'] = 'twingle_project';

    // Import values
    $this->values = $values;

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
   * Create the TwingleProject as a campaign in CiviCRM if it does not exist
   *
   * @param bool $is_test
   * If true: don't do any changes
   *
   * @return array
   * Returns a response array that contains title, id, project_id and state
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function create(bool $is_test = FALSE) {


    // Create campaign only if it does not already exist
    if (!$this->exists()) {
      if (!$is_test) {

        // Translate Twingle field names into custom field names
        $translatedFields = $this->values;
        self::translateCustomFields($translatedFields, self::OUT);

        // Create campaign
        $result = civicrm_api3('Campaign', 'create', $translatedFields);

        // Set id attribute
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
    }
    else {
      // Give information back if campaign already exists
      $response = $this->getResponse('TwingleProject already exists');
    }
    return $response;
  }


  /**
   * Update an existing project
   *
   * If true: don't do any changes
   *
   * @param bool $is_test
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public function update(bool $is_test = FALSE) {

    $response = '';

    // Translate Twingle field names to custom field names
    $translatedFields = $this->values;
    self::translateCustomFields($translatedFields, self::OUT);

    if (!$is_test) {
      $result = civicrm_api3('Campaign', 'create', $translatedFields);

      if ($result['is_error'] == 0) {
        $response = $this->getResponse('TwingleProject updated from Twingle');
      }
      else {
        $response = $this->getResponse('Updated from Twingle failed');
      }
    }
    else {
      $response = $this->getResponse('TwingleProject outdated');
    }

    return $response;
  }


  /**
   * Export values
   *
   * @return array
   */
  public function export() {
    $values = $this->values;
    $this->formatForExport($values);
    return $values;
  }


  /**
   * Check if a project already exists
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function exists() {

    // Get custom field name for project_id
    $cf_project_id = TwingleProject::$customFieldMapping['twingle_project_id'];

    $single = FALSE;
    $result = [];

    // If there is more than one campaign for a project, handle the duplicates
    while (!$single) {
      $result = civicrm_api3('Campaign', 'get', [
        'sequential'   => 1,
        'return'       => ['id', 'last_modified_date'],
        'is_active'    => '1',
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

      // set campaign id attribute
      $this->id = $result['values'][0]['id'];

      // set last_modified_date
      $this->values['last_modified_date'] =
        $result['values'][0]['last_modified_date'];

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
   * @return \CRM\TwingleCampaign\Models\TwingleProject
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

    // Instantiate the left projects to deactivate them
    foreach ($result['values'] as $p) {
      $project = TwingleProject::fetch($p['id']);
      $project->deactivate();
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

    // Get json file with translations
    $file_path = E::path() .
      '/api/v3/TwingleSync/resources/dictionary.json';
    $json_file = file_get_contents($file_path);
    $json_file_name = pathinfo($file_path)['filename'];
    $translations = json_decode($json_file, TRUE);

    // Throw an error if json file can't be read
    if (!$translations) {
      $message = ($json_file_name)
        ? "Could not read json file $json_file_name"
        : "Could not locate json file in path: $file_path";
      throw new \Exception($message);
      //TODO: use specific exception or create own
    }

    // Select only fields
    $fields = $translations['fields'];

    // Set the direction of the translation
    if ($direction == self::OUT) {
      array_flip($fields);
    }
    // Throw error if $direction constant does not match IN or OUT
    elseif ($direction != self::IN) {
      throw new \Exception(
        "Invalid Parameter $direction for translateKeys()"
      );
      // TODO: use specific exception or create own
    }

    // Translate keys
    foreach ($translations as $origin => $translation) {
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
      $values['last_modified_date'] =
        self::getDateTime($values['last_modified_date']);

      // empty project_type to 'default
      $values['type'] = $values['type'] == ''
        ? 'default'
        : $values['type'];

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
          unset($values[$field]);
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
   * Deactivate a project
   *
   * @return bool
   * TRUE if deactivation was successful
   *
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
    // TODO: sync project settings
  }


  /**
   * Get a response that describes the state of a TwingleProject
   *
   * @param string $state
   * State of the TwingleProject you want the response for
   *
   * @return array
   * Returns a response array that contains title, id, project_id and state
   */
  public function getResponse(string $state) {
    return [
      'title'      => $this->values['title'],
      'id'         => $this->id,
      'project_id' => $this->values['id'],
      'state'      => $state,
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
   * @return mixed
   */
  public function getId() {
    return $this->id;
  }

}
