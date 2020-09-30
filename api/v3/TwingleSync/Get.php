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
    'description'  => E::ts('The key to access the Twingle API'),
  ];
  $spec['test'] = [
    'name'         => 'is_test',
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

  // Is this call a test?
  $is_test = (boolean) $params['is_test'];

  // If function call provides an API key, use it instead of the API key set
  // on the extension settings page
  $apiKey = empty($params['twingle_api_key'])
    ? CRM_Core_BAO_Setting::getItem('', 'twingle_api_key')
    : $params['twingle_api_key'];
  $twingleApi = new TwingleApiCall($apiKey);

  // Get all projects from Twingle and store them in $projects
  $projects = $twingleApi->getProject();

  // Create projects as campaigns if they do not exist and store results in
  // $result_values
  $i = 0;
  foreach ($projects as $project) {
    if (is_array($project)) {
      $result_values['sync']['projects'][$i++] = $twingleApi
        ->syncProject($project, $is_test);
    }
  }

  return civicrm_api3_create_success($result_values);
}
