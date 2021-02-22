<?php

use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * # APIWrapper class for TwingleDonation.submit
 * This wrapper maps an incoming contribution to a campaign created by the
 * de.forumzfd.twinglecampaign extension.
 */
class CRM_TwingleCampaign_Utils_APIWrapper {

  private static $campaign_id;

  /**
   * ## PREPARE callback method for event listener
   *
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
   * ## RESPOND callback method for event listener
   *
   * @param $event
   */
  public static function RESPOND($event) {
    $request = $event->getApiRequestSig();
    if ($request == '3.twingledonation.submit') {

      $response = $event->getResponse();

      // Create soft credit for contribution
      $contribution = $response['values']['contribution']
      [array_key_first($response['values']['contribution'])];
      if (array_key_exists('campaign_id', $contribution)) {
        try {
          $twingle_event = civicrm_api3(
            'TwingleEvent',
            'getsingle',
            ['id' => $contribution['campaign_id']]
          )['values'];
          $response['values']['soft_credit'] =
            self::createSoftCredit($contribution, $twingle_event)['values'];
          $event->setResponse($response);
        } catch (CiviCRM_API3_Exception $e) {
          // Do nothing
        }
      }
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
   */
  public function mapDonation($apiRequest, $callsame) {

    if (array_key_exists(
      'campaign_id',
      $apiRequest['params'])
    ) {
      if (is_numeric($apiRequest['params']['campaign_id'])) {
        $targetCampaign['values']['id'] = $apiRequest['params']['campaign_id'];
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
        $apiRequest['params']['custom_fields']) &&
      !empty($apiRequest['params']['custom_fields']['event'])
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

    if (isset($targetCampaign)) {
      $apiRequest['params']['campaign_id'] = $targetCampaign['values']['id'];
    }

    return $callsame($apiRequest);
  }

  /**
   * ## Create soft credit
   *
   * This method creates a soft credit for an event initiator.
   *
   * @param array $contribution
   * @param array $event
   *
   * @return array
   */
  private static function createSoftCredit(
    array $contribution,
    array $event): array {
    try {
      return civicrm_api3('ContributionSoft', 'create', [
        'contribution_id'     => $contribution['id'],
        'amount'              => $contribution['total_amount'],
        'currency'            => $contribution['currency'],
        'contact_id'          => $event['contact_id'],
        'soft_credit_type_id' => 'twingle_event_donation',
      ]);
    } catch (CiviCRM_API3_Exception $e) {
      Civi::log()->error(
        E::LONG_NAME .
        ' could not create soft credit: ',
        [
          'contact_id'      => $event['contact_id'],
          'contact'         => $event['contact'],
          'contribution_id' => $contribution['id'],
        ]
      );
    }
  }

}