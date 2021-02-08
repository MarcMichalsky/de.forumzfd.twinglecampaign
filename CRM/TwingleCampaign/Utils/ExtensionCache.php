<?php

use CRM_TwingleCampaign_BAO_CustomField as CustomField;
use CRM_TwingleCampaign_ExtensionUtil as E;


/**
 * A singleton that caches mappings and settings
 *
 * @package CRM\TwingleCampaign\Utilities
 */
class CRM_TwingleCampaign_Utils_ExtensionCache {

  private $customFieldMapping;

  private $translations;

  private $campaigns;

  private $templates;

  /**
   * ## Get an instance (singleton)
   *
   * @return CRM_TwingleCampaign_Utils_ExtensionCache
   */
  public static function getInstance(): CRM_TwingleCampaign_Utils_ExtensionCache {
    if (NULL === Civi::cache()->get('twinglecampaign_cache')) {
      Civi::cache()->set('twinglecampaign_cache', new self);
    }
    return Civi::cache()->get('twinglecampaign_cache');
  }

  /**
   * Protected ExtensionCache constructor.
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  protected function __construct() {

    // Get a mapping of custom fields
    $this->customFieldMapping = CustomField::getMapping();

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
      $this->$key = $array;
    }

    // Get ids for Twingle related campaign types
    foreach ($this->campaigns['campaign_types'] as $campaign_type) {
      $campaign_type_id = civicrm_api3(
        'OptionValue',
        'get',
        ['sequential' => 1, 'name' => $campaign_type['name']]
      )['values'];
      if ($campaign_type_id) {
        $this->campaigns['campaign_types'][$campaign_type['name']]['id'] =
          $campaign_type_id[0]['value'];
      }
    }

  }

  /**
   * ## Get custom field mapping
   * Returns a mapping custom fields of the TwingleCampaign extension.
   * * If a **$fieldName** is provided, this method returns its custom field
   * name
   * * Without parameter, the method returns the whole mapping
   *
   * @param string|null $fieldName
   *
   * @return array|string
   */
  public function getCustomFieldMapping(string $fieldName = NULL) {
    if ($fieldName) {
      return $this->customFieldMapping[$fieldName];
    }
    return $this->customFieldMapping;
  }

  /**
   * @return array
   */
  public function getTranslations(): array {
    return $this->translations;
  }

  /**
   * @return array
   */
  public function getCampaigns(): array {
    return $this->campaigns;
  }

  /**
   * @return array
   */
  public function getTemplates(): array {
    return $this->templates;
  }


}