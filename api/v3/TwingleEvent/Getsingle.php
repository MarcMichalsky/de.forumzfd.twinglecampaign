<?php

use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * TwingleEvent.Getsingle API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_event_Getsingle_spec(array &$spec) {
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
    'description'  => E::ts('Parent Project ID for this Event'),
  ];
  $spec['event_id'] = [
    'name'         => 'event_id',
    'title'        => E::ts('Twingle Event ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Twingle ID for this Event'),
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
  $spec['is_active'] = [
    'name'         => 'is_active',
    'title'        => E::ts('Campaign is active'),
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'description'  => E::ts('Is this Campaign enabled or disabled/cancelled?'),
  ];
  $spec['confirmed_at'] = [
    'name'         => 'confirmed_at',
    'title'        => E::ts('Confirmed at'),
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'description'  => E::ts('When the Event was confirmed by its initiator'),
  ];
}

/**
 * TwingleEvent.Getsingle API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws CiviCRM_API3_Exception
 * @see civicrm_api3_create_success
 */
function civicrm_api3_twingle_event_Getsingle(array $params): array {

  $returnValues = civicrm_api3('TwingleEvent', 'get', $params);
  $count = $returnValues['count'];

  if ($count != 1) {
    return civicrm_api3_create_error("Expected one TwingleEvent but found $count");
  }
  return civicrm_api3_create_success(
    $returnValues['values'][$returnValues['id']],
    $params,
    'TwingleEvent',
    'Getsingle'
  );
}
