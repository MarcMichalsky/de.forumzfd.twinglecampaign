<?php

use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * TwingleProject.Getsingle API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_project_Getsingle_spec(array &$spec) {
  $spec['id'] = [
    'name'         => 'id',
    'title'        => E::ts('Campaign ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Unique Campaign ID'),
  ];
  $spec['name'] = [
    'name'         => 'name',
    'title'        => E::ts('Campaign Name'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('Name of the Campaign'),
  ];
  $spec['title'] = [
    'name'         => 'title',
    'title'        => E::ts('Campaign Title'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('Title of the Campaign'),
  ];
  $spec['start_date'] = [
    'name'         => 'start_date',
    'title'        => E::ts('Campaign Start Date'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('Date and Time that Campaign starts.'),
  ];
  $spec['project_id'] = [
    'name'         => 'project_id',
    'title'        => E::ts('Twingle Project ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Twingle ID for this project'),
  ];
  $spec['identifier'] = [
    'name'         => 'identifier',
    'title'        => E::ts('Twingle Project Identifier'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('Twingle Project Identifier'),
  ];
  $spec['last_modified_id'] = [
    'name'         => 'last_modified_id',
    'title'        => E::ts('Campaign Modified By'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('FK ti civicrm_contact, who recently edited this campaign'),
    'FKClassName'  => 'CRM_Contact_DAO_Contact',
  ];
  $spec['last_modified_date'] = [
    'name'         => 'last_modified_date',
    'title'        => E::ts('Campaign Modified Date'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('FK ti civicrm_contact, who recently edited this campaign'),
  ];
  $spec['type'] = [
    'name'         => 'type',
    'title'        => E::ts('Twingle Project Type'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('The type of the Twingle Project'),
  ];
  $spec['allow_more'] = [
    'name'         => 'allow_more',
    'title'        => E::ts('Allow more'),
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'description'  => E::ts('Allow to donate more than is defined in the target'),
  ];
  $spec['organisation_id'] = [
    'name'         => 'organisation_id',
    'title'        => E::ts('Twingle Organisation ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Your Twingle Organisation ID'),
  ];
  $spec['is_active'] = [
    'name'         => 'is_active',
    'title'        => E::ts('Campaign is active'),
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'description'  => E::ts('Is this Campaign enabled or disabled/cancelled?'),
  ];
}

/**
 * # TwingleProject.Getsingle API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws CiviCRM_API3_Exception
 * @see civicrm_api3_create_success
 */
function civicrm_api3_twingle_project_Getsingle(array $params): array {

  // filter parameters
  $allowed_params = [];
  _civicrm_api3_twingle_project_Getsingle_spec($allowed_params);
  $params = array_intersect_key($params, $allowed_params);

  // Get TwingleProject by provided parameters
  $returnValues = civicrm_api3('TwingleProject', 'get', $params);
  $count = $returnValues['count'];

  // Check whether only a single TwingleProject is found
  if ($count != 1) {
    return civicrm_api3_create_error(
      "Expected one TwingleProject but found $count"
    );
  }
  return civicrm_api3_create_success(
    $returnValues['values'][$returnValues['id']],
    $params, 'TwingleProject',
    'Getsingle'
  );
}
