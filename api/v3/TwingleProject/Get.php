<?php

use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;
use CRM_TwingleCampaign_BAO_TwingleProject as TwingleProject;

/**
 * TwingleProject.Get API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_project_Get_spec(array &$spec) {
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
 * TwingleProject.Get API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws \CiviCRM_API3_Exception
 * @throws \API_Exception
 * @see civicrm_api3_create_success
 */
function civicrm_api3_twingle_project_Get(array $params): array {

  // filter parameters
  $allowed_params = [];
  _civicrm_api3_twingle_project_Get_spec($allowed_params);
  $params = array_intersect_key($params, $allowed_params);

  $custom_field_mapping = Cache::getInstance()->getCustomFieldMapping();
  $custom_field_mapping_reverse = array_flip($custom_field_mapping);

  $params['campaign_type_id'] = 'twingle_project';
  // Do not limit the number of results
  $query = ['options' => ['limit' => 0]];

  foreach ($params as $key => $value) {
    if ( $key != 'id' &&
      array_key_exists('twingle_project_' . $key, $custom_field_mapping)
    ) {
      $query[$custom_field_mapping['twingle_project_' . $key]] = $value;
    }
    elseif ($key == 'project_id') {
      $query[$custom_field_mapping['twingle_project_id']] = $value;
    }
    else {
      $query[$key] = $value;
    }
  }

  $result = civicrm_api3('Campaign', 'get', $query);

  if ($result['is_error'] == 0) {
    $returnValues = [];

    // Translate custom fields
    foreach ($result['values'] as $project) {
      foreach ($project as $key => $value) {
        if (array_key_exists($key, $custom_field_mapping_reverse)) {
          $returnValues[$project['id']][$custom_field_mapping_reverse[$key]]
            = $value;
        }
        else {
          $returnValues[$project['id']][$key] = $value;
        }
      }
      foreach ($returnValues[$project['id']] as $key => $value) {
        if ($key != 'twingle_project_id' && strpos($key, 'twingle_project_') === 0) {
          $key_short = str_replace('twingle_project_', '', $key);
          $returnValues[$project['id']][$key_short] = $value;
          unset($returnValues[$project['id']][$key]);
        }
        elseif ($key == 'twingle_project_id') {
          $returnValues[$project['id']]['project_id'] = $value;
          unset($returnValues[$project['id']]['twingle_project_id']);
        }
      }
      try {
        TwingleProject::translateKeys(
          $returnValues[$project['id']],
          TwingleProject::PROJECT,
          TwingleProject::OUT
        );
        TwingleProject::formatValues(
          $returnValues[$project['id']],
          TwingleProject::OUT
        );
      } catch (Exception $e) {
        throw new API_Exception($e->getMessage());
      }
    }

    return civicrm_api3_create_success($returnValues, $params, 'TwingleProject', 'Get');
  }

  else {
    return $result;
  }
}
