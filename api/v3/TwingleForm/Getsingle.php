<?php
use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * TwingleForm.Getsingle API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_form_Getsingle_spec(array &$spec) {
  $spec['id'] = [
    'name'         => 'id',
    'title'        => E::ts('TwingleProject ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('ID of the TwingleProject campaign'),
  ];
  $spec['name'] = [
    'name'         => 'name',
    'title'        => E::ts('TwingleProject name'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('Name of the TwingleProject campaign'),
  ];
  $spec['title'] = [
    'name'         => 'title',
    'title'        => E::ts('TwingleProject title'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('Title of the TwingleProject campaign'),
  ];
  $spec['twingle_project_type'] = [
    'name'         => 'twingle_project_type',
    'title'        => E::ts('TwingleProject type'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('Project type can be default, event or membership'),
  ];
}

/**
 * TwingleForm.Getsingle API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws \API_Exception
 * @throws \CiviCRM_API3_Exception
 * @see civicrm_api3_create_success
 */
function civicrm_api3_twingle_form_Getsingle(array $params): array {

  // filter parameters
  $allowed_params = [];
  _civicrm_api3_twingle_form_Getsingle_spec($allowed_params);
  $params = array_intersect_key($params, $allowed_params);

  $returnValues = civicrm_api3('TwingleForm', 'get', $params);
  $count = $returnValues['count'];

  if ($count != 1){
    return civicrm_api3_create_error("Expected one TwingleForm but found $count");
  }
    return $returnValues['values'][$returnValues['id']];
}
