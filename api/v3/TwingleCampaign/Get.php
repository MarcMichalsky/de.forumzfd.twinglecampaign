<?php

use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;

/**
 * TwingleCampaign.Get API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_campaign_Get_spec(array &$spec) {
  $spec['id'] = [
    'name'         => 'id',
    'title'        => E::ts('Twingle Campaign ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('The Twingle Campaign ID'),
  ];
  $spec['cid'] = [
    'name'         => 'cid',
    'title'        => E::ts('Twingle Campaign CID'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('A unique TwingleCampaign identifier for external usage'),
  ];
  $spec['project_id'] = [
    'name'         => 'project_id',
    'title'        => E::ts('Parent Twingle Project ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Twingle ID of the parent TwingleProject'),
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
}

/**
 * # TwingleCampaign.Get API
 * Gets TwingleCampaign campaigns by the provided parameters.<br>
 * If a TwingleProject **project_id** is provided, this API returns all its
 * TwingleCampaign children campaigns.
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
function civicrm_api3_twingle_campaign_Get(array $params): array {

  // filter parameters
  $allowed_params = [];
  _civicrm_api3_twingle_campaign_Get_spec($allowed_params);
  $params = array_intersect_key($params, $allowed_params);

  $returnValues = [];

  // Get campaign type id for TwingleCampaign
  $twingle_campaign_campaign_type_id =
    Cache::getInstance()
      ->getCampaignIds()['campaign_types']['twingle_campaign']['id'];

  // If no id but a project_id is provided, get all TwingleCampaign children of
  // this TwingleProject
  if (array_key_exists('project_id', $params) && $params['project_id']) {

    // Get TwingleProject
    $project = civicrm_api3('TwingleProject',
      'get',
      ['project_id' => $params['project_id']]
    );

    // Remove 'parent_id' from $params
    unset($params['project_id']);

    // Include parent TwingleProject id in $params
    $params['parent_id'] = $project['id'];

    // Include campaign type ot TwingleCampaigns in $params
    $params['campaign_type_id'] = $twingle_campaign_campaign_type_id;

    // Do not limit the number of results
    $params['options'] = ['limit' => 0];

    // Get TwingleCampaign children campaigns of the TwingleProject
    $campaigns = civicrm_api3('Campaign',
      'get',
      $params
    );
  }
  // If no 'project_id' is provided, get all TwingleCampaigns by the provided
  // $params
  else {

    // Include campaign type ot TwingleCampaigns in $params
    $params['campaign_type_id'] = $twingle_campaign_campaign_type_id;

    // Translate cid custom field
    if (array_key_exists('cid', $params) && !empty($params['cid'])) {
      $cf_cid = Cache::getInstance()
        ->getCustomFieldMapping('twingle_campaign_cid');
      $params[$cf_cid] = $params['cid'];
      unset($params['cid']);
    }

    $campaigns = civicrm_api3('Campaign',
      'get',
      $params
    );
  }

  // Translate custom fields
  if (!empty($campaigns)) {
    $custom_field_mapping_reverse =
      array_flip(Cache::getInstance()->getCustomFieldMapping());

    foreach ($campaigns['values'] as $campaign) {
      foreach ($campaign as $key => $value) {
        if (array_key_exists($key, $custom_field_mapping_reverse)) {
          $returnValues[$campaign['id']][$custom_field_mapping_reverse[$key]]
            = $value;
        }
        else {
          $returnValues[$campaign['id']][$key] = $value;
        }
      }
      foreach($returnValues[$campaign['id']] as $key => $value) {
        if ($key != 'twingle_campaign_id' && strpos($key, 'twingle_campaign_') === 0) {
          $returnValues[$campaign['id']][str_replace('twingle_campaign_', '', $key)]
            = $value;
          unset($returnValues[$campaign['id']][$key]);
        }
      }
    }

    return civicrm_api3_create_success($returnValues, $params, 'TwingleCampaign', 'Get');
  }

  return civicrm_api3_create_success($returnValues, $params, 'TwingleCampaign', 'Get');
}
