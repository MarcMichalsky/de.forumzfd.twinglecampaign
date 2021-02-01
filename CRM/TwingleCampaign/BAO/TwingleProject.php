<?php

use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;
use CRM_TwingleCampaign_BAO_Campaign as Campaign;


class CRM_TwingleCampaign_BAO_TwingleProject extends Campaign {

  /**
   * ## TwingleProject constructor
   *
   * @param array $values
   * Project values
   *
   * @param int|null $id
   * CiviCRM Campaign id
   */
  public function __construct(array $values, int $id = NULL) {
    parent::__construct($values, $id);

    $this->prefix = 'twingle_project_';
    $this->values['campaign_type_id'] = 'twingle_project';
    $this->id_custom_field = Cache::getInstance()
      ->getCustomFieldMapping()['twingle_project_id'];

  }
  

  /**
   * ## Export values
   * Ensures that only those values will be exported which the Twingle API
   * expects. These values are defined in
   * *CRM/TwingleCampaign/resources/twingle_api_templates.json*
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
   * ## Create this TwingleProject as a campaign in CiviCRM
   *
   * Returns _TRUE_ if creation was successful or _FALSE_ if it creation failed.
   *
   * @param bool $no_hook
   * Do not trigger postSave hook to prevent recursion
   *
   * @return bool
   * @throws \Exception
   */
  public function create(bool $no_hook = FALSE): bool {

    // Prepare project values for import into database
    $values_prepared_for_import = $this->values;
    $this->formatValues(
      $values_prepared_for_import,
      self::IN
    );
    $this->translateKeys(
      $values_prepared_for_import,
      self::IN
    );
    $this->translateCustomFields(
      $values_prepared_for_import,
      self::IN
    );

    // Set id
    $values_prepared_for_import['id'] = $this->id;

    // Set a flag to not trigger the hook
    if ($no_hook) {
      $_SESSION['CiviCRM']['de.forumzfd.twinglecampaign']['no_hook'] = TRUE;
    }

    // Create campaign
    $result = civicrm_api3('Campaign', 'create', $values_prepared_for_import);

    // Update id
    $this->id = $result['id'];

    // Check if campaign was created successfully
    if ($result['is_error'] == 0) {
      return TRUE;
    }
    else {
      throw new Exception($result['error_message']);
    }
  }


  /**
   * ## Clone this TwingleProject
   *
   * This method removes the id and the identifier from this instance and in
   * the next step it pushes the clone as a new project with the same values to
   * Twingle.
   *
   * @throws \Exception
   */
  public function clone() {
    unset($this->values['id']);
    unset($this->values['identifier']);
    $this->create(); // this will also trigger the postSave hook
  }


  /**
   * ## Translate values between CiviCRM Campaigns and Twingle formats
   *
   * Constants for **$direction**:<br>
   * **TwingleProject::IN** translate array values from Twingle to CiviCRM format<br>
   * **TwingleProject::OUT** translate array values from CiviCRM to Twingle format
   *
   * @param array $values
   * array of values to translate
   *
   * @param string $direction
   * const: TwingleProject::IN or TwingleProject::OUT
   *
   * @throws Exception
   */
  public function formatValues(array &$values, string $direction) {

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
        ? FALSE
        : TRUE;
    }
    else {

      throw new Exception(
        "Invalid Parameter $direction for formatValues()"
      );

    }
  }


  /**
   * ## Get a response
   * Get a response that describes the status of this TwingleProject instance
   * Returns an array that contains **title**, **id**, **project_id** and
   * **status** (if provided)
   *
   * @param string|null $status
   * status of the TwingleProject you want to give back along with the response
   *
   * @return array
   *
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
   * ## Last update
   * Returns a timestamp of the last update of the TwingleProject campaign.
   *
   * @return int|string|null
   */
  public function lastUpdate() {
    return self::getTimestamp($this->values['last_update']);
  }


  /**
   * ## Get project id
   * Returns the **project_id** of this TwingleProject.
   *
   * @return int
   */
  public function getProjectId(): int {
    return (int) $this->values['id'];
  }

}
