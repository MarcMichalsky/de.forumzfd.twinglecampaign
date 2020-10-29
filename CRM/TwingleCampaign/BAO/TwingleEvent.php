<?php


namespace CRM\TwingleCampaign\BAO;

use Civi;
use CRM\TwingleCampaign\Utils\ExtensionCache as Cache;
use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM_Utils_Array;
use DateTime;
use CRM\TwingleCampaign\BAO\CustomField as CustomField;
use Exception;
use CiviCRM_API3_Exception;

include_once E::path() . '/CRM/TwingleCampaign/BAO/CustomField.php';


class TwingleEvent extends Campaign {

  /**
   * TwingleEvent constructor.
   *
   * @param array $event
   * Result array of Twingle API call to
   * https://project.twingle.de/api/$project_id/event
   *
   * @param string $origin
   * Origin of the arrays. It can be one of two constants:
   * TwingleEvent::TWINGLE|CIVICRM
   *
   * @throws Exception
   */
  public function __construct(array $event, string $origin) {
    parent::__construct($event, $origin);

    $this->className = get_class($this);

    // Add value for campaign type
    $event['campaign_type_id'] = 'twingle_event';

    // Get custom field name for event_id
    $this->id_custom_field = Cache::getInstance()
      ->getCustomFieldMapping()['twingle_event_id'];

  }


  /**
   * Translate values between CiviCRM Campaigns and Twingle
   *
   * @param array $values
   * array of which values shall be translated
   *
   * @param string $direction
   * TwingleEvent::IN -> translate array values from Twingle to CiviCRM <br>
   * TwingleEvent::OUT -> translate array values from CiviCRM to Twingle
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
      $values['last_update'] =
        self::getTimestamp($values['last_update']);

      // Default project_type to ''
      $values['type'] = $values['type'] == 'default'
        ? ''
        : $values['type'];

      // Cast project target to integer
      $values['project_target'] = (int) $values['project_target'];

    }
    else {

      throw new Exception(
        "Invalid Parameter $direction for formatValues()"
      );

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
      ->getTemplates()['event_embed_data'];

    // Transfer all embed_data values
    foreach ($embed_data_keys as $key) {
      $this->values[$key] = htmlspecialchars($embedData[$key]);
    }
  }


  /**
   * Get a response that describes the status of a TwingleEvent
   *
   * @param string $status
   * status of the TwingleEvent you want the response for
   *
   * @return array
   * Returns a response array that contains title, id, project_id and status
   */
  public function getResponse(string $status) {
    return [
      'title'      => $this->values['name'],
      'id'         => (int) $this->id,
      'event_id'   => (int) $this->values['id'],
      'project_id' => (int) $this->values['project_id'],
      'status'     => $status,
    ];
  }


  /**
   * Returns the project_id of a TwingleEvent
   *
   * @return int
   */
  public function getProjectId() {
    return (int) $this->values['project_id'];
  }


  /**
   * Returns the event_id of a TwingleEvent
   *
   * @return int
   */
  public function getEventId() {
    return (int) $this->values['id'];
  }

}
