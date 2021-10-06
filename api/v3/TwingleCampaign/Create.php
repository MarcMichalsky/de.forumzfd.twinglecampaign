<?php

use CRM_TwingleCampaign_BAO_TwingleCampaign as TwingleCampaign;
use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * TwingleCampaign.Create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_campaign_Create_spec(array &$spec) {
  $spec['id'] = [
    'name'         => 'id',
    'title'        => E::ts('Twingle Campaign ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('The Twingle Campaign ID'),
  ];
  $spec['name'] = [
    'name'         => 'name',
    'title'        => E::ts('Twingle Campaign Name'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('Name of the Twingle Project'),
  ];
  $spec['title'] = [
    'name'         => 'title',
    'title'        => E::ts('Twingle Campaign Title'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description'  => E::ts('Title of the Twingle Campaign'),
  ];
  $spec['parent_id'] = [
    'name'         => 'parent_id',
    'title'        => E::ts('Parent Campaign'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 1,
    'description'  => E::ts('Optional parent id for this Campaign'),
  ];
  $spec['clone'] = [
    'name'         => 'clone',
    'title'        => E::ts('Clone'),
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'description'  => E::ts('Set this value to true if this campaign is about to be cloned to recreate cid'),
  ];
}


/**
 * # TwingleCampaign.Create API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws CiviCRM_API3_Exception
 * @see civicrm_api3_create_success
 */
function civicrm_api3_twingle_campaign_Create(array $params): array {

  // filter parameters
  $allowed_params = [];
  _civicrm_api3_twingle_campaign_Create_spec($allowed_params);
  $params = array_intersect_key($params, $allowed_params);

  try {
    // instantiate TwingleCampaign
    $campaign = new TwingleCampaign($params);
    // try to create the TwingleCampaign
    $campaign->create(TRUE);
    return civicrm_api3_create_success(
      $campaign->getResponse('TwingleCampaign created'),
      $params,
      'TwingleCampaign',
      'Create'
    );
  } catch(Exception $e){
    return civicrm_api3_create_error(
      'Could not create TwingleCampaign: ' . $e->getMessage()
    );
  }

}
