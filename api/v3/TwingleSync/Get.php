<?php

use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM\TwingleCampaign\Models\TwingleApiCall as TwingleApiCall;

include_once E::path() . '/api/v3/TwingleSync/models/TwingleApiCall.php';

/**
 * TwingleSync.Get API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_sync_Get_spec(&$spec) {
  $spec['twingle_api_key'] = [
    'name'         => 'twingle_api_key',
    'title'        => E::ts('Twingle API key'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('The key you need to access the Twingle API'),
  ];
  $spec['test'] = [
    'name'         => 'test',
    'title'        => E::ts('Test'),
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'description'  => E::ts('If this is set true, no database change will be made'),
  ];
}

/**
 * TwingleSync.Get API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws \CiviCRM_API3_Exception|\API_Exception
 * @see civicrm_api3_create_success
 *
 */
function civicrm_api3_twingle_sync_Get($params) {
  $result_values = [];

  $apiKey = empty($params['twingle_api_key'])
    ? CRM_Core_BAO_Setting::getItem('', 'twingle_api_key')
    : $params['twingle_api_key'];

  // TODO: Do the magic!

  $twingleApi = new TwingleApiCall($apiKey);
  $result_values['projects'] = $twingleApi->getProject();
  foreach ($result_values['projects'] as $project) {
    if (is_array($project)) {
      $result_values['campaigns_created'][$project['id']] = $twingleApi->createProject($project);
    }
  }

  return civicrm_api3_create_success($result_values);
}
