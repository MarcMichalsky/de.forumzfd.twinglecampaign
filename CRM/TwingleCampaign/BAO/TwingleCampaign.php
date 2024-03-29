<?php

use CRM_TwingleCampaign_Utils_ExtensionCache as ExtensionCache;
use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM_TwingleCampaign_Exceptions_TwingleCampaignException as TwingleCampaignException;

class CRM_TwingleCampaign_BAO_TwingleCampaign {

  // IN means: heading into CiviCRM database
  public const IN = 'IN';

  // OUT means: coming from the CiviCRM database
  public const OUT = 'OUT';

  private $prefix;

  private $id;

  private $values;

  /**
   * ## TwingleCampaign constructor
   *
   * @param array $values
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \CRM_TwingleCampaign_Exceptions_TwingleCampaignException
   */
  public function __construct(array $values = []) {

    $this->prefix = 'twingle_campaign_';
    $this->id = $values['id'] ?? NULL;
    $this->values['campaign_type_id'] = 'twingle_campaign';

    // If there is already an ID for this TwingleProject, get its values from
    // the database
    if ($this->id != NULL) {
      $this->fetch($this->id);
    }

    // Update the campaign values
    $this->update($values);

    // Get the parent TwingleProject
    // (it doesn't matter how many levels above in the campaign tree it is)
    $this->getParentProject();

    // If this is a new TwingleCampaign or if it is a cloned TwingleCampaign,
    // calculate a cid
    if (
      !isset($this->values['cid']) ||
      (isset($values['clone']) && $values['clone'])
    ) {
      $this->createCid();
    }

    // Create an url from the parent TwingleProject url and the cid of this
    // TwingleCampaign
    $this->createUrl();
  }

  /**
   * ## Create TwingleCampaign
   * Create this TwingleCampaign as a campaign in CiviCRM
   *
   * @param bool $no_hook
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function create(bool $no_hook = FALSE) {

    // Set a flag to not trigger the hook
    if ($no_hook) {
      $_SESSION['CiviCRM']['de.forumzfd.twinglecampaign']['no_hook'] = TRUE;
    }

    $values = $this->values;

    $this->translateCustomFields($values, self::IN);

    $result = civicrm_api3('Campaign', 'create', $values);

    if (!array_key_exists('is_error', $result) || $result['is_error'] != 0) {
      throw new CiviCRM_API3_Exception('TwingleCampaign creation failed');
    }
  }

  /**
   * ## Fetch TwingleCampaign
   * Populate this instance with values from an existing TwingleCampaign.
   *
   * @throws CiviCRM_API3_Exception
   */
  public function fetch(int $id) {
    $this->values = civicrm_api3('TwingleCampaign', 'getsingle',
      ['id' => $id]);
  }

  /**
   * ## Get Parent Project
   * Determines the id of the parent TwingleProject. If there is no parent
   * TwingleProject, an error message is shown on UI and the campaign gets
   * deleted.
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \CRM_TwingleCampaign_Exceptions_TwingleCampaignException
   */
  private function getParentProject(): void {

    // Get campaign type id for TwingleProject
    $twingle_project_campaign_type_id =
      ExtensionCache::getInstance()
        ->getCampaignIds()['campaign_types']['twingle_project']['id'];

    // Determine the parent project id by looping through the campaign tree
    // until the parent campaign type is a TwingleProject
    $parent_id = $this->values['parent_id'] ?? civicrm_api3(
        'TwingleCampaign',
        'getsingle',
        ['id' => $this->id]
      )['parent_id'];

    $parent_campaign_type_id = NULL;

    while ($parent_id && $parent_campaign_type_id != $twingle_project_campaign_type_id) {
      // Get parent campaign
      $parent_campaign = civicrm_api3('Campaign', 'getsingle',
        ['id' => $parent_id]);

      if (isset($parent_campaign['is_error'])) {
        if ($parent_campaign['is_error'] != 1) {
          throw new CiviCRM_API3_Exception($parent_campaign['error_message']);
        }
      }

      $parent_campaign_type_id = $parent_campaign['campaign_type_id'];
      if ($parent_campaign_type_id != $twingle_project_campaign_type_id && isset($parent_campaign['parent_id'])) {
        $parent_id = $parent_campaign['parent_id'];
      }
      else {
        break;
      }
    }

    // Set parent_project_id and retrieve parent_project_url
    if ($parent_campaign_type_id == $twingle_project_campaign_type_id) {
      $this->values['parent_project_id'] = $parent_id;

      // Get custom field names for twingle_project_page and
      // twingle_project_url fields
      $cf_page = ExtensionCache::getInstance()
        ->getCustomFieldMapping('twingle_project_page');
      $cf_url = ExtensionCache::getInstance()
        ->getCustomFieldMapping('twingle_project_url');

      // Try to extract twingle_project_url from parent_campaign
      if (!empty($parent_campaign[$cf_url])) {
        $this->values['parent_project_url'] = $parent_campaign[$cf_url];
      }
      // If there is no twingle_project_url use the parent_project_page instead
      elseif (!empty($parent_campaign[$cf_page])) {
        $this->values['parent_project_url'] = $parent_campaign[$cf_page];
      }
      // If both values are missing, try a synchronization
      else {
        $parent_campaign = civicrm_api3('TwingleProject', 'sync',
          ['id' => $parent_id]);

        // Now try again to extract the twingle_project_page url
        if ($parent_campaign[$cf_url]) {
          $this->values['parent_project_url'] = $parent_campaign[$cf_page];
        }

        // If twingle_project_widget value is still missing, show an alert on
        // UI, log an error and delete this TwingleCampaign
        else {
          CRM_Core_Session::setStatus(
            ts("Could not determine parent TwingleProject URL. This URL is needed to create the TwingleEvent URL. Please check the logs."),
            ts('Parent project URL missing'),
            'error'
          );
          Civi::log()->error(
            E::LONG_NAME .
            ' could not determine parent TwingleProject URL.',
            $this->getResponse()
          );
          throw new TwingleCampaignException('Parent project URL missing');
        }
      }

      // If this TwingleCampaign has no parent TwingleProject above it in the
      // campaign tree
    }
    else {
      CRM_Core_Session::setStatus(
        ts("TwingleCampaigns can only get created as a child of a TwingleProject in the campaign tree."),
        ts('No parent TwingleProject found'),
        'alert'
      );
      throw new TwingleCampaignException('No parent TwingleProject found');
    }
  }

  /**
   * ## Create URL
   * Create a URL by adding a tw_cid
   */
  private
  function createUrl() {
    // Trim parent_project_url
    $this->values['parent_project_url'] = trim($this->values['parent_project_url']);
    // If url ends with a '/', remove it
    if (substr($this->values['parent_project_url'], -1) == '/') {
      $this->values['parent_project_url'] =
        substr_replace($this->values['parent_project_url'], '', -1);
    }
    $this->values['url'] =
      $this->values['parent_project_url'] . '?tw_cid=' . $this->values['cid'];
  }

  /**
   *
   */
  private
  function createCid() {
    $this->values['cid'] = uniqid();
  }

  /**
   * ## Translate field names and custom field names
   *
   * Constants for **$direction**:<br>
   * **Campaign::IN** Translate field name to custom field name <br>
   * **Campaign::OUT** Translate from custom field name to field name
   *
   * @param array $values
   * array of keys to translate
   *
   * @param string $direction
   * const: Campaign::OUT or Campaign::OUT
   */
  public
  function translateCustomFields(array &$values, string $direction) {

    // Translate field name to custom field name
    if ($direction == self::IN) {

      foreach (ExtensionCache::getInstance()
                 ->getCustomFieldMapping() as $field => $custom) {

        if (array_key_exists(
          str_replace(
            $this->prefix,
            '',
            $field
          ),
          $values)
        ) {

          $values[$custom] = $values[str_replace(
            $this->prefix,
            '',
            $field
          )];

          unset($values[str_replace(
              $this->prefix,
              '',
              $field
            )]
          );
        }
      }
    }
    // Translate from custom field name to field name
    elseif ($direction == self::OUT) {

      foreach (ExtensionCache::getInstance()
                 ->getCustomFieldMapping() as $field => $custom) {

        if (array_key_exists(
          $custom,
          $values
        )
        ) {
          $values[str_replace(
            $this->prefix,
            '',
            $field
          )] = $values[$custom];
          unset($values[$custom]);
        }
      }
    }
  }

  /**
   * ## Delete TwingleCampaign
   * Deletes this TwingleCampaign from CiviCRM
   */
  public function delete() {
    if ($this->id) {
      try {
        civicrm_api3('Campaign', 'delete', ['id' => $this->id]);
      } catch (CiviCRM_API3_Exception $e) {
        Civi::log()->error(
          E::LONG_NAME .
          ' could not delete TwingleCampaign: ' .
          $e->getMessage(),
          $this->getResponse()
        );
      }
    }
  }

  /**
   * ## Get a response
   * Get a response that describes the status of this TwingleCampaign instance.
   * Returns an array that contains **title**, **id**, **parent_project_id**
   * (if available) and **status** (if provided)
   *
   * @param string|null $status
   * status of the TwingleCampaign you want to give back along with the response
   *
   * @return array
   */
  public
  function getResponse(string $status = NULL): array {
    $keys = [
      'id',
      'name',
      'title',
      'parent_project_id',
      'parent_id',
      'cid',
      'url',
    ];
    $response = [];
    foreach ($keys as $key) {
      if (isset($this->values[$key])) {
        $response[$key] = $this->values[$key];
      }
    }
    if ($status) {
      $response['status'] = $status;
    }
    return $response;
  }


  /**
   * ## Update values
   * Updates all values in **$this->values** array.
   *
   * @param array $values
   * values that should get updated
   */
  private
  function update(array $values) {
    $filter = ExtensionCache::getInstance()
      ->getTemplates()['TwingleCampaign']['campaign_data'];
    foreach ($values as $key => $value) {
      if (in_array($key, $filter)) {
        $this->values[$key] = $value;
      }
    }
  }

  /**
   * ## Get ID
   *
   * @return mixed|null
   */
  public function getId(): int {
    return (int) $this->id;
  }

}