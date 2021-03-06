<?php

use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM_TwingleCampaign_BAO_Configuration as Configuration;

/**
 * # APIWrapper class for TwingleDonation.submit
 * This wrapper maps an incoming contribution to a campaign created by the
 * de.forumzfd.twinglecampaign extension.
 */
class CRM_TwingleCampaign_Utils_APIWrapper {

  /**
   * ## PREPARE callback method for event listener
   *
   * @param $event
   */
  public static function PREPARE($event) {
    $request = $event->getApiRequestSig();
    if ($request == '3.twingledonation.submit') {
      $event->wrapAPI(['CRM_TwingleCampaign_Utils_APIWrapper', 'mapDonation']);
    }
  }

  /**
   * ## RESPOND callback method for event listener
   *
   * @param $event
   */
  public static function RESPOND($event) {
    $request = $event->getApiRequestSig();
    if (
      $request == '3.twingledonation.submit' &&
      Configuration::get('twinglecampaign_soft_credits')
    ) {

      $response = $event->getResponse();

      // Make a copy of $response to performe some altering functions on it
      $response_copy = $response;

      // Create soft credit for contribution
      if (array_key_exists('contribution', $response['values'])) {
        $contribution = array_shift($response_copy['values']['contribution']);
        if (array_key_exists('campaign_id', $contribution)) {
          try {
            $twingle_event = civicrm_api3(
              'TwingleEvent',
              'getsingle',
              ['id' => $contribution['campaign_id']]
            );
            $response['values']['soft_credit'] =
              self::createSoftCredit($contribution, $twingle_event)['values'];
            $event->setResponse($response);
          } catch (CiviCRM_API3_Exception $e) {
            // If an error is thrown, no event was found: so do nothing
          }
        }
      }
      // Create soft credit for sepa mandate
      elseif (array_key_exists('sepa_mandate', $response['values'])) {
        $sepa_mandate = array_pop($response_copy['values']['sepa_mandate']);

        try {
          $contribution = civicrm_api3(
            'Contribution',
            'getsingle',
            ['id' => $sepa_mandate['entity_id']]
          );
        } catch (CiviCRM_API3_Exception $e) {
          Civi::log()->error(
            E::LONG_NAME .
            ' could not create Soft Credit: contribution id unknown',
            [
              'contribution_id' => $sepa_mandate['entity_id']
            ]
          );
        }

        if (isset($contribution['contribution_campaign_id'])) {
          try {
            $twingle_event = civicrm_api3(
              'TwingleEvent',
              'getsingle',
              ['id' => $contribution['contribution_campaign_id']]
            );
            $response['values']['soft_credit'] =
              self::createSoftCredit($contribution, $twingle_event)['values'];
            $event->setResponse($response);
          } catch (CiviCRM_API3_Exception $e) {
            // If an error is thrown, no event was found: so do nothing
          }
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
   * @param $apiRequest
   * @param $callsame
   *
   * @return mixed
   */
  public function mapDonation($apiRequest, $callsame) {

    if (array_key_exists(
      'campaign_id',
      $apiRequest['params'])
    ) {
      if (is_numeric($apiRequest['params']['campaign_id'])) {
        $targetCampaign['id'] = $apiRequest['params']['campaign_id'];
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
        // If no event was found, sync all Events and try it again
        try {
          $test = civicrm_api3('TwingleEvent', 'sync');
          $targetCampaign = civicrm_api3(
            'TwingleEvent',
            'getsingle',
            ['event_id' => $apiRequest['params']['custom_fields']['event']]
          );
        } catch (CiviCRM_API3_Exception $e) {
          // there's nothing left to do
        }
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
      $apiRequest['params']['campaign_id'] = $targetCampaign['id'];
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