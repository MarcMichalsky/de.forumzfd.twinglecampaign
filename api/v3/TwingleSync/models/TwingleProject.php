<?php


namespace CRM\TwingleCampaign\Models;

use CRM_TwingleCampaign_ExtensionUtil as E;
use DateTime;
use CRM\TwingleCampaign\Models\CustomField as CustomField;

include_once E::path() . '/CRM/TwingleCampaign/Upgrader/models/CustomField.php';


class TwingleProject {

  private static $bInitialized = FALSE;

  private $values;

  private $timestamp;

  private $settings;

  private static $customFields = [];

  /**
   * TwingleProject constructor.
   *
   * @param array $values
   *
   * @throws \Exception
   */
  public function __construct(array $values) {

    $this->timestamp = $values['last_update'];

    // Format data types of the values for import into CiviCRM
    $this->formatForImport($values);

    // Add necessary attributes
    $this->values['title'] = $values['name'];
    $this->values['campaign_type_id'] = 'twingle_project';

    // Fetch custom fields once and store them in static attribute
    self::init();

    // Map parameters to custom field names (e.g. "custom_21")
    foreach (self::$customFields as $customField) {
      if (!empty($values[str_replace('twingle_project_', '', $customField->getName())])) {
        $this->values['custom_' . $customField->getId()] =
          $values[str_replace('twingle_project_', '', $customField->getName())];
      }
    }
  }

  /**
   * Get all related custom fields as CustomField objects in an static array.
   * This function will be fully executed only once, when the TwingleProject
   * class gets instantiated for the first time.
   *
   * @throws \Exception
   */
  private static function init() {
    if (self::$bInitialized) {
      return;
    }

    $json_file = file_get_contents(E::path() .
      '/CRM/TwingleCampaign/Upgrader/resources/campaigns.json');
    $campaign_info = json_decode($json_file, TRUE);

    if (!$campaign_info) {
      \Civi::log()->error("Could not read json file");
      throw new \Exception('Could not read json file');
    }

    foreach ($campaign_info['custom_fields'] as $custom_field) {
      $result = CustomField::fetch($custom_field['name']);
      array_push(self::$customFields, $result);
    }

    self::$bInitialized = TRUE;
  }

  /**
   * Create the project as a campaign in CiviCRM if it does not exist
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public function create() {
    $values = $this->values;
    try {
      return civicrm_api3('Campaign', 'create', $values);
    } catch (\CiviCRM_API3_Exception $e) {
      return null;
    }
  }

  /**
   * Formats values to import them as campaigns
   *
   * @param $values
   */
  private function formatForImport(&$values) {

    // Change timestamp into DateTime string
    if (!empty($values['last_update'])) {
      $date = DateTime::createFromFormat('U', $values['last_update'] );
      $values['last_update'] = $date->format('Y-m-d H:i:s');
    }

    // Change event type empty string into 'default'
    if ($values['type'] == ''){
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
      $date = DateTime::createFromFormat('Y-m-d H:i:s', $values['last_update'] );
      $values['last_update'] = $date->getTimestamp();
    }

    // Change event type 'default' into empty string
    if ($values['type'] == 'default'){
      $values['type'] = '';
    }
  }

  public function syncSettings() {

  }


}