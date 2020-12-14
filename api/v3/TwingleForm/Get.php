<?php

use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;

/**
 * TwingleForm.Get API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_form_Get_spec(array &$spec) {
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
 * TwingleForm.Get API
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
function civicrm_api3_twingle_form_Get(array $params) {

  // Get custom fields
  $custom_field_mapping = Cache::getInstance()->getCustomFieldMapping();

  // Replace twingle_project_type key with custom field name
  if (key_exists('twingle_project_type', $params)) {
    $params[$custom_field_mapping['twingle_project_type']] =
      $params['twingle_project_type'];
    unset($params['twingle_project_type']);
  }

  $request = [
    'sequential' => 1,
    'is_active' => 1,
    'campaign_type_id' => "twingle_project"
  ];
  foreach($params as $key => $param) {
    $request[$key] = $param;
  }

  try {
    $result = civicrm_api3('Campaign', 'get', $request);

    if ($result['is_error'] == 0) {
      $returnValues = [];
      foreach($result['values'] as $value) {
        array_push(
          $returnValues,
          [
            'id' => $value['id'],
            'twingle_project_id' => $value[$custom_field_mapping['twingle_project_id']],
            'title' => $value['title'],
            'name' => $value['name'],
            'project_type' => $value[$custom_field_mapping['twingle_project_type']],
            'embed_code' => $value[$custom_field_mapping['twingle_project_widget']],
            'counter' => $value[$custom_field_mapping['twingle_project_counter']]
          ]
        );
      }
      return civicrm_api3_create_success($returnValues, $params, 'TwingleForm', 'Get');
    }
    else {
      // TODO: Edit error message
      throw new API_Exception(/*error_message*/ 'Everyone knows that the magicword is "sesame"', /*error_code*/ 'magicword_incorrect');
    }
  } catch (Exception $e) {
    throw new API_Exception(/*error_message*/ 'Everyone knows that the magicword is "sesame"', /*error_code*/ 'magicword_incorrect');
  }
}
