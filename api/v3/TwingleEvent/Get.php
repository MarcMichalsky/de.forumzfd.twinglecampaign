<?php
use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;
use CRM_TwingleCampaign_BAO_TwingleEvent as TwingleEvent;

/**
 * TwingleEvent.Get API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_event_Get_spec(array &$spec) {
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
  $spec['campaign_type_id'] = [
    'name'         => 'campaign_type_id',
    'title'        => E::ts('Campaign Type'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('Campaign Type ID. Implicit FK to 
    cicicrm_option_value where option_group = campaign_type'),
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
 * TwingleEvent.Get API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_twingle_event_Get($params) {
  $custom_field_mapping = Cache::getInstance()->getCustomFieldMapping();
  $custom_field_mapping_reverse = array_flip($custom_field_mapping);

  $query = ['sequential' => 1, 'campaign_type_id' => 'twingle_event'];

  foreach ($params as $key => $value) {
    if ( $key != 'id' &&
      key_exists('twingle_event_' . $key, $custom_field_mapping)
    ) {
      $query[$custom_field_mapping['twingle_event_' . $key]] = $value;
    }
    elseif ($key == 'event_id') {
      $query[$custom_field_mapping['twingle_event_id']] = $value;
    }
    else {
      $query[$key] = $value;
    }
  }

  $result = civicrm_api3('Campaign', 'get', $query);

  if ($result['is_error'] == 0) {
    $returnValues = [];
    foreach ($result['values'] as $event) {
      foreach ($event as $key => $value) {
        if (key_exists($key, $custom_field_mapping_reverse)) {
          $returnValues[$event['id']][$custom_field_mapping_reverse[$key]]
            = $value;
        }
        else {
          $returnValues[$event['id']][$key] = $value;
        }
      }
      foreach($returnValues[$event['id']] as $key => $value) {
        if ($key != 'twingle_event_id' && strpos($key, 'twingle_event_') === 0) {
          $returnValues[$event['id']][str_replace('twingle_event_', '', $key)]
            = $value;
          unset($returnValues[$event['id']][$key]);
        } elseif($key == 'twingle_event_id'){
          $returnValues[$event['id']]['event_id'] = $value;
          unset($returnValues[$event['id']]['twingle_event_id']);
        }
      }
      try {
        TwingleEvent::translateKeys($returnValues[$event['id']], TwingleEvent::OUT);
        TwingleEvent::formatValues($returnValues[$event['id']], TwingleEvent::OUT);
      }
      catch (Exception $e) {
        throw new API_Exception($e->getMessage());
      }
    }

    return civicrm_api3_create_success($returnValues, $params, 'TwingleProject', 'Get');
  }

  else {
    return $result;
  }
}
