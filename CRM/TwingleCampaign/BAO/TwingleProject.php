<?php

use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;
use CRM_TwingleCampaign_BAO_Campaign as Campaign;
use CRM_TwingleCampaign_BAO_TwingleApiCall as TwingleApiCall;

class CRM_TwingleCampaign_BAO_TwingleProject extends Campaign {

  /**
   * TwingleProject constructor.
   *
   * @param array $project
   * Result array of Twingle API call to
   * https://project.twingle.de/api/by-organisation/$organisation_id
   *
   * @param int|null $id
   *
   * @throws \Exception
   */
  function __construct(array $project, int $id = NULL) {
    parent::__construct($project);

    $this->id = $id;
    $this->prefix = 'twingle_project_';
    $this->values['campaign_type_id'] = 'twingle_project';
    $this->id_custom_field = Cache::getInstance()
      ->getCustomFieldMapping()['twingle_project_id'];

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
  public function export(): array {

    $values = $this->values;
    self::formatValues($values, self::OUT);

    // Get template for project
    $project = Cache::getInstance()->getTemplates()['TwingleProject'];

    // Replace array items which the Twingle API does not expect
    foreach ($values as $key => $value) {
      if (!in_array($key, $project)) {
        unset($values[$key]);
      }
    }

    return $values;
  }


  /**
   * Create the Campaign as a campaign in CiviCRM if it does not exist
   *
   * @param bool $is_test
   * If true: don't do any changes
   *
   * @return bool
   * Returns a boolean
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public function create(bool $is_test = FALSE): array {

    // Create campaign only if this is not a test
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
    $this->translateCustomFields(
      $values_prepared_for_import,
      self::IN
    );

    // Set id
    $values_prepared_for_import['id'] = $this->id;

      // Create campaign
      $result = civicrm_api3('Campaign', 'create', $values_prepared_for_import);

    // Update id
    $this->id = $result['id'];

    // Check if campaign was created successfully
    if ($result['is_error'] == 0) {
      return TRUE;
    }
    else {
      $errorMessage = $result['error_message'];
      throw new Exception("$errorMessage");
    }
  }


  /**
   * Translate values between CiviCRM Campaigns and Twingle format
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
  public static function formatValues(array &$values, string $direction) {

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
      $values['last_update'] =
        self::getTimestamp($values['last_update']);

      // Default project_type to ''
      $values['type'] = $values['type'] == 'default'
        ? ''
        : $values['type'];

      // Cast project target to integer
      $values['project_target'] = (int) $values['project_target'];

      // Set default for 'allow_more'
      $values['allow_more'] = empty($values['allow_more'])
        ? False
        : True;

    }
    else {

      throw new Exception(
        "Invalid Parameter $direction for formatValues()"
      );

    }
  }

  /**
   * Translate array keys between CiviCRM Campaigns and Twingle
   *
   * @param array $values
   * array of which keys shall be translated
   *
   * @param string $direction
   * Campaign::IN -> translate array keys from Twingle format into
   * CiviCRM format <br>
   * Campaign::OUT -> translate array keys from CiviCRM format into
   * Twingle format
   *
   * @throws Exception
   */
  public static function translateKeys(array &$values, string $direction) {

    // Get translations for fields
    $field_translations = Cache::getInstance()
      ->getTranslations()['TwingleProject'];

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
   * Set embed data fields
   *
   * @param array $embedData
   * Array with embed data from Twingle API
   */
  public function setEmbedData(array $embedData) {

    // Get all embed_data keys from template
    $embed_data_keys = Cache::getInstance()
      ->getTemplates()['project_embed_data'];

    // Transfer all embed_data values
    foreach ($embed_data_keys as $key) {
      $this->values[$key] = $embedData[$key];
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
  public function getResponse(string $status = NULL): array {
    $project_type = empty($this->values['type']) ? 'default' : $this->values['type'];
    $response =
      [
        'title'        => $this->values['name'],
        'id'           => (int) $this->id,
        'project_id'   => (int) $this->values['id'],
        'project_type' => $project_type,
      ];
    if ($status) {
      $response['status'] = $status;
    }
    return $response;
  }

  /**
   * Return a timestamp of the last update of the Campaign
   *
   * @return int|string|null
   */
  public function lastUpdate() {

    return self::getTimestamp($this->values['last_update']);
  }


  /**
   * Returns the project_id of a TwingleProject
   *
   * @return int
   */
  public function getProjectId(): int {
    return (int) $this->values['id'];
  }

}
