<?php

use CRM_TwingleCampaign_BAO_CustomField as CustomField;
use CRM_TwingleCampaign_ExtensionUtil as E;


/**
 * A singleton that caches mappings and settings
 *
 * @package CRM\TwingleCampaign\Utilities
 */
class CRM_TwingleCampaign_Utils_ExtensionCache {

  protected static $_instance = NULL;

  private $customFieldMapping;

  private $translations;

  private $campaigns;

  private $templates;

  /**
   * Get an instance (singleton)
   * @return self|null
   */
  public static function getInstance() {
    if (null === self::$_instance) {
      self::$_instance = new self;
    }
    return self::$_instance;
  }

  /**
   * Protected ExtensionCache constructor.
   *
   * @throws \CiviCRM_API3_Exception
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
  }

  protected function __clone() {}

  /**
   * @return array
   */
  public function getCustomFieldMapping(): array {
    return $this->customFieldMapping;
  }

  /**
   * @return mixed
   */
  public function getTranslations() {
    return $this->translations;
  }

  /**
   * @return mixed
   */
  public function getCampaigns() {
    return $this->campaigns;
  }

  /**
   * @return mixed
   */
  public function getTemplates() {
    return $this->templates;
  }


}