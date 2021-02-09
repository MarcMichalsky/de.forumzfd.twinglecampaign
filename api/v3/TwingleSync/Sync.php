<?php

use CRM_TwingleCampaign_BAO_TwingleApiCall as TwingleApiCall;
use CRM_TwingleCampaign_BAO_TwingleProject as TwingleProject;
use CRM_TwingleCampaign_BAO_TwingleEvent as TwingleEvent;
use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * TwingleSync.Sync API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_sync_Sync_spec(array &$spec) {
  $spec['twingle_api_key'] = [
    'name'         => 'twingle_api_key',
    'title'        => E::ts('Twingle API key'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('The key to access the Twingle API'),
  ];
  $spec['limit'] = [
    'name'         => 'limit',
    'title'        => E::ts('Limit'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Limit for the number of events that should get requested per call to the Twingle API'),
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
 * # TwingleSync.Sync API
 * This API synchronizes all *TwingleProject* and *TwingleEvent* campaigns.
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws CiviCRM_API3_Exception
 * @see civicrm_api3_create_success
 */
function civicrm_api3_twingle_sync_Sync(array $params): array {

  // filter parameters
  $allowed_params = [];
  _civicrm_api3_twingle_sync_Sync_spec($allowed_params);
  $params = array_intersect_key($params, $allowed_params);

  $result_values = [];

  // Synchronize all TwingleProject campaigns
  $projects = civicrm_api3(
    'TwingleProject',
    'sync',
    $params
  )['values'];

  // Synchronize all TwingleEvent campaigns
  foreach ($projects as $project) {
    if (is_array($project)) {
      $_params = $params;
      $_params['project_id'] = $project['project_id'];
      $result_values[] = [
        'project' => $project,
        'events'  => array_values(
          civicrm_api3(
            'TwingleEvent',
            'sync',
            $_params
          )['values']
        ),
      ];
    }
  }

  return civicrm_api3_create_success($result_values);
}
