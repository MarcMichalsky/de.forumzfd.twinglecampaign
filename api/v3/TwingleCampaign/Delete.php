<?php

use CRM_TwingleCampaign_BAO_TwingleCampaign as TwingleCampaign;
use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * TwingleCampaign.Delete API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_campaign_Delete_spec(array &$spec) {
  $spec['id'] = [
    'name'         => 'id',
    'title'        => E::ts('Twingle Campaign ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('The Twingle Campaign ID'),
  ];
}

/**
 * # TwingleCampaign.Delete API
 * This API allows you to delete a single TwingleCampaign at a time.
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws CiviCRM_API3_Exception
 * @see civicrm_api3_create_success
 */
function civicrm_api3_twingle_campaign_Delete(array $params): array {

  // Filter parameters
  $allowed_params = [];
  _civicrm_api3_twingle_campaign_Delete_spec($allowed_params);
  $params = array_intersect_key($params, $allowed_params);

  // Instantiate TwingleCampaign
  $campaign = new TwingleCampaign([], $params['id']);

  // Delete TwingleCampaign via method
  $campaign->delete();

  // Return results
  return civicrm_api3_create_success(
    $campaign->getResponse('TwingleCampaign deleted'),
    $params,
    'TwingleCampaign',
    'Delete'
  );
}
