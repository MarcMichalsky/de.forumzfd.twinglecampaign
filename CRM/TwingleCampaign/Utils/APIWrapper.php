<?php

/**
 * # APIWrapper class for TwingleDonation.submit
 * This wrapper maps an incoming contribution to a campaign created by the
 * de.forumzfd.twinglecampaign extension.
 */
class CRM_TwingleCampaign_Utils_APIWrapper {

  private static $campaign_id;

  /**
   * ## Callback method for event listener
   * @param $event
   */
  public static function PREPARE($event) {
    $request = $event->getApiRequestSig();
    if ($request == '3.twingledonation.submit') {
      $event->wrapAPI(['CRM_TwingleCampaign_Utils_APIWrapper', 'mapDonation']);
      //TODO: Exception handling and logging
    }
  }

  /**
   * ## Map donation to Campaign
   * This functions tries to map an incoming Twingle donation to a campaign
   * by referring to its project, event or campaign id. The identified campaign
   * id will be written into the **campaign_id** field of the request, so that
   * the de.systopia.twingle extension can include the campaign into the
   * contribution which it will create.
   *
   * @throws CiviCRM_API3_Exception
   */
  public function mapDonation($apiRequest, $callsame) {

    if (array_key_exists(
      'campaign_id',
      $apiRequest['params'])
    ) {
      if (is_numeric($apiRequest['params']['campaign_id'])) {
        $targetCampaign = $apiRequest['params']['campaign_id'];
      }
      else {
        try {
          $targetCampaign = civicrm_api3(
            'TwingleCampaign',
            'getsingle',
            ['cid' => $apiRequest['params']['campaign_id']]
          );
        } catch (CiviCRM_API3_Exception $e) {
          unset($apiRequest['params']['campaign_id']);
        }
      }
    }
    elseif (array_key_exists(
      'event',
      $apiRequest['params']['custom_fields'])
    ) {
      try {
        $targetCampaign = civicrm_api3(
          'TwingleEvent',
          'getsingle',
          ['event_id' => $apiRequest['params']['custom_fields']['event']]
        );
      } catch (CiviCRM_API3_Exception $e) {
        // Do nothing
      }
    }
    else {
      try {
        $targetCampaign = civicrm_api3(
          'TwingleProject',
          'getsingle',
          ['identifier' => $apiRequest['params']['project_id']]
        );
      } catch (CiviCRM_API3_Exception $e) {
        // Do nothing
      }
    }

    // TODO: Soft credits

    if (isset($targetCampaign)) {
      $apiRequest['params']['campaign_id'] = $targetCampaign['values']['id'];
    }

    return $callsame($apiRequest);
  }

}