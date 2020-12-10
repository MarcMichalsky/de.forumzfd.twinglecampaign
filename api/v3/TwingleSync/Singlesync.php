<?php

use CRM_TwingleCampaign_BAO_TwingleApiCall as TwingleApiCall;
use CRM_TwingleCampaign_BAO_TwingleProject as TwingleProject;
use CRM_TwingleCampaign_BAO_TwingleEvent as TwingleEvent;
use CRM_TwingleCampaign_BAO_CampaignType as CampaignType;
use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;
use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * TwingleSync.Singlesync API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_sync_Singlesync_spec(array &$spec) {
  $spec['campaign_id'] = [
    'name'         => 'campaign_id',
    'title'        => E::ts('Campaign ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 1,
    'description'  => E::ts('ID of the campaign that should get synced'),
  ];
  $spec['twingle_api_key'] = [
    'name'         => 'twingle_api_key',
    'title'        => E::ts('Twingle API key'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('The key to access the Twingle API'),
  ];
  $spec['is_test'] = [
    'name'         => 'is_test',
    'title'        => E::ts('Test'),
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'description'  => E::ts('If this is set true, no database change will be made'),
  ];
}

/**
 * TwingleSync.Singlesync API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws API_Exception
 * @see civicrm_api3_create_success
 *
 */
function civicrm_api3_twingle_sync_Singlesync($params) {

  $result_values = [];

  // Is this call a test?
  $is_test = (boolean) $params['is_test'];

  // If function call provides an API key, use it instead of the API key set
  // on the extension settings page
  $apiKey = empty($params['twingle_api_key'])
    ? trim(Civi::settings()->get('twingle_api_key'))
    : trim($params['twingle_api_key']);

  $twingleApi = new TwingleApiCall($apiKey, 1);

  $campaign_type_id = 0;
  $project_campaign_type_id =
    CampaignType::fetch('twingle_project')->getValue();
  $event_campaign_type_id =
    CampaignType::fetch('twingle_event')->getValue();

  $custom_field_mapping = Cache::getInstance()->getCustomFieldMapping();

  $result = civicrm_api3('Campaign', 'get', [
    'sequential' => 1,
    'id'         => $params['campaign_id'],
  ]);

  if ($result['is_error'] == 0) {
    if ($result['count'] == 1) {
      $campaign_type_id = $result['values'][0]['campaign_type_id'];
    }
    else {
      $count = $result['count'];
      throw new API_Exception(
        "Expected one Campaign but found $count",
      );
    }
  }
  else {
    throw new API_Exception($result['error_message']);
  }

  switch ($campaign_type_id) {

    case $project_campaign_type_id:
      // Get a single project from the Twingle API
      // TODO: Es darf nur ein Projekt sein! $result['values'][0][$custom_field_mapping['twingle_project_id']] pruefen! Sonst project pushen
      $project = $twingleApi->getProject(
        $result['values'][0][$custom_field_mapping['twingle_project_id']]
      );
      // Create project as campaign and store results in $result_values
      $result_values['project'] =
        TwingleProject::sync($project, $twingleApi, $is_test);
      break;

    case $event_campaign_type_id:
      $event = $twingleApi->getEvent(
        $result['values'][0][$custom_field_mapping['twingle_event_project_id']],
        $result['values'][0][$custom_field_mapping['twingle_event_id']]
      );
      $result_values['event'] =
        TwingleEvent::sync($event, $twingleApi, $is_test);
      break;

    default:
      throw new API_Exception(
        "Campaign does not belong to TwingleCampaign Extension"
      );
  }
  return civicrm_api3_create_success($result_values);
}

