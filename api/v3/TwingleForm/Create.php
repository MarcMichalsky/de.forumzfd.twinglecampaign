<?php

use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * TwingleForm.Create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_form_Create_spec(array &$spec) {
  $spec['id'] = [
    'name'         => 'id',
    'title'        => E::ts('TwingleProject ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 1,
    'description'  => E::ts('ID of the TwingleProject campaign'),
  ];
  $spec['url'] = [
    'name'         => 'url',
    'title'        => E::ts('TwingleProject Page URL'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description'  => E::ts('URL of the TwingleProject Page'),
  ];
}

/**
 * TwingleForm.Create API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws CiviCRM_API3_Exception
 * @see civicrm_api3_create_success
 *
 */
function civicrm_api3_twingle_form_Create(array $params): array {

  // filter parameters
  $allowed_params = [];
  _civicrm_api3_twingle_form_Create_spec($allowed_params);
  $params = array_intersect_key($params, $allowed_params);

  // Validate url
  if (!filter_var($params['url'], FILTER_VALIDATE_URL)) {
    Civi::log()->error(
      E::LONG_NAME .
      ': invalid url was provided via TwingleForm.create',
      $params
    );
    return civicrm_api3_create_error('invalid URL', $params);
  }

  // Re-create TwingleProject
  $result = civicrm_api3('TwingleProject', 'create', $params);

  // Retrun results
  if ($result['is_error'] != 1) {
    return civicrm_api3_create_success(
      $result['values'],
      $params,
      'TwingleForm',
      'create',
    );
  }
  else {
    return civicrm_api3_create_error(
      'Could not create TwingleForm: ' . $result['error_message']
    );
  }
}
