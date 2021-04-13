<?php

use CRM_TwingleCampaign_BAO_CustomField as CustomField;
use CRM_TwingleCampaign_ExtensionUtil as E;


/**
 * A singleton that caches mappings and settings
 *
 * @package CRM\TwingleCampaign\Utils
 */
class CRM_TwingleCampaign_Utils_ExtensionCache {

  private $customFieldMapping;

  private $campaignIds;

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

    // Get ids for Twingle related campaign types
    $this->campaignIds = require(
      E::path() . '/CRM/TwingleCampaign/resources/campaigns.php'
    );
    foreach ($this->campaignIds['campaign_types'] as $campaign_type) {
      $campaign_type_id = civicrm_api3(
        'OptionValue',
        'get',
        [
          'sequential' => 1,
          'name'       => $campaign_type['name'],
          'options'    => ['limit' => 0],
        ]
      )['values'];
      if ($campaign_type_id) {
        $this->campaignIds['campaign_types'][$campaign_type['name']]['id'] =
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
    return require(
      E::path() . '/CRM/TwingleCampaign/resources/dictionary.php'
    );
  }

  /**
   * @return array
   */
  public function getCampaigns(): array {
    return require(
      E::path() . '/CRM/TwingleCampaign/resources/campaigns.php'
    );
  }

  /**
   * @return array
   */
  public function getCampaignIds(): array {
    return $this->campaignIds;
  }

  /**
   * @return array
   */
  public function getTemplates(): array {
    return require(
      E::path() . '/CRM/TwingleCampaign/resources/twingle_api_templates.php'
    );
  }

  /**
   * @return mixed
   */
  public function getOptionValues() {
    return require(
      E::path() . '/CRM/TwingleCampaign/resources/option_values.php'
    );
  }

}