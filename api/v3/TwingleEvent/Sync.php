<?php

use CRM_TwingleCampaign_BAO_TwingleEvent as TwingleEvent;
use CRM_TwingleCampaign_BAO_TwingleApiCall as TwingleApiCall;
use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * TwingleEvent.Sync API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_event_Sync_spec(array &$spec) {
  $spec['id'] = [
    'name'         => 'id',
    'title'        => E::ts('Campaign ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Unique Campaign ID'),
  ];
  $spec['event_id'] = [
    'name'         => 'event_id',
    'title'        => E::ts('Twingle Event ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Twingle ID for this Event'),
  ];
  $spec['is_test'] = [
    'name'         => 'is_test',
    'title'        => E::ts('Test'),
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'description'  => E::ts('If this is set true, no database change will be made'),
  ];
  $spec['twingle_api_key'] = [
    'name'         => 'twingle_api_key',
    'title'        => E::ts('Twingle API key'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('The key to access the Twingle API'),
  ];
  $spec['limit'] = [
    'name'         => 'limit',
    'title'        => E::ts('Limit'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Limit for the number of events that should get requested per call to the Twingle API'),
  ];
}

/**
 * # TwingleEvent.Sync API
 *
 * Synchronize one ore more campaigns of the type TwingleEvent between CiviCRM
 * and Twingle.
 * _NOTE:_ Changes on TwingleEvents are not meant to get pushed to Twingle, so
 * the synchronization takes place only one way
 *
 * * If you provide an **id** or **event_id** parameter, only one event will be
 * synchronized.
 *
 * * If you provide a **project_id** as parameter, all events of that project will
 * be synchronized.
 *
 * * If you provide no **id**, **event_id** or **project_id** parameter, all events
 * will be synchronized.
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 * @throws \CiviCRM_API3_Exception
 * @throws \Exception
 * @see civicrm_api3_create_success
 */
function civicrm_api3_twingle_event_Sync(array $params): array {

  // filter parameters
  $allowed_params = [];
  _civicrm_api3_twingle_event_Sync_spec($allowed_params);
  $params = array_intersect_key($params, $allowed_params);

  // If call provides an API key, use it instead of the API key set
  // on the extension settings page
  $apiKey = empty($params['twingle_api_key'])
    ? trim(Civi::settings()->get('twingle_api_key'))
    : trim($params['twingle_api_key']);

  // Try to retrieve twingleApi from cache or create a new
  $twingleApi = Civi::cache()->get('twinglecampaign_twingle_api');
  if (NULL === $twingleApi || $params['twingle_api_key'] || $params['limit']) {
    try {
      if ($params['limit']) {
        $twingleApi = new TwingleApiCall($apiKey, $params['limit']);
      }
      else {
        $twingleApi = new TwingleApiCall($apiKey);
      }
    } catch (Exception $e) {
      return civicrm_api3_create_error($e->getMessage());
    }
    Civi::cache('long')->set('twinglecampaign_twingle_api', $twingleApi);
  }

  // If an id or a project_id is given, synchronize only this one campaign
  if ($params['id'] || $params['event_id']) {

    // Get project from db via API
    $params['sequential'] = 1;
    $result = civicrm_api3('TwingleEvent', 'getsingle', $params);
    if ($result['is_error'] == 0) {

      // Get the event from Twingle
      if ($result['values'][0]['event_id']) {
        $event_from_twingle = $twingleApi->getEvent(
          $result['values'][0]['project_id'],
          $result['values'][0]['event_id']
        );

        // instantiate event from CiviCRM
        try {
          $event = _instantiateEvent($result['values'][0]);
        } catch (CiviCRM_API3_Exception $e) {
          Civi::log()->error(
            $e->getMessage(),
            $e->getExtraParams()
          );
          return civicrm_api3_create_error(
            $e->getMessage(),
            $e->getExtraParams()
          );
        }
        // Synchronize events
        if (!empty($event_from_twingle)) {
          return _eventSync($event, $event_from_twingle, $twingleApi, $params);
        }

        // If Twingle does not know an event with the given event_id, give error
        else {
          return civicrm_api3_create_error(
            "The event_id appears to be unknown to Twingle",
            $event->getResponse()
          );
        }
      }
    }

    // If the project could not get retrieved from TwingleEvent.getsingle,
    // forward API error message
    else {
      Civi::log()->error(
        E::LONG_NAME .
        ' could retrieve project from TwingleEvent.getsingle',
        $result
      );
      return $result;
    }
  }

  // If no id but an event_id and/or a project_id is given, synchronize all
  // all events or just the events of the given project

  $result_values = [];

  // Counter for sync errors
  $errors_occurred = 0;

  // Get all events for provided project from Twingle and CiviCRM
  if ($params['project_id']) {
    $events_from_twingle = $twingleApi->getEvent($params['project_id']);
    $events_from_civicrm = civicrm_api3(
      'TwingleEvent',
      'get',
      ['project_id' => $params['project_id']]
    );
  }
  // Get all events for all projects from Twingle
  else {
    $events_from_twingle = [];

    // Get all TwingleProject campaigns of type "event" from CiviCRM
    $projects_from_civicrm = civicrm_api3(
      'TwingleProject',
      'get',
      [
        'type'       => 'event',
        'sequential' => 1,
        'is_active'  => TRUE,
      ]
    );

    // Get all TwingleEvent campaigns from CiviCRM
    $events_from_civicrm = civicrm_api3(
      'TwingleEvent',
      'get',
      ['sequential' => 1]
    );

    // Get all events for the chosen project from Twingle
    foreach ($projects_from_civicrm['values'] as $project_from_civicrm) {
      $event_from_twingle = $twingleApi->getEvent($project_from_civicrm['project_id']);
      array_push(
        $events_from_twingle,
        $event_from_twingle
      );
    }
    $events_from_twingle = array_merge(... $events_from_twingle);
  }

  // Synchronize existing events or create new ones
  foreach ($events_from_twingle as $event_from_twingle) {

    // Create missing events as campaigns in CiviCRM
    if (!in_array($event_from_twingle['id'],
      array_column($events_from_civicrm['values'], 'event_id'))) {

      // Instantiate Event
      try {
        $event = _instantiateEvent($event_from_twingle);
      } catch (CiviCRM_API3_Exception $e) {
        Civi::log()->error(
          $e->getMessage(),
          $e->getExtraParams()
        );
        return civicrm_api3_create_error(
          $e->getMessage(),
          $e->getExtraParams()
        );
      }

      // If this is a test, do not make db changes
      if ($params['is_test']) {
        $result_values[$event->getId()] =
          $event->getResponse('Ready to create TwingleEvent');
      }

      try {
        $event->create();
        $result_values[$event->getId()] =
          $event->getResponse('TwingleEvent created');
      } catch (Exception $e) {
        $errors_occurred++;
        Civi::log()->error(
          E::LONG_NAME .
          ' could not create TwingleEvent: ' .
          $e->getMessage(),
          $event->getResponse()
        );
        $result_values[$event->getId()] = $event->getResponse(
          'TwingleEvent could not get created: ' . $e->getMessage()
        );
      }
    }
  }

  // Synchronize existing events
  foreach ($events_from_civicrm['values'] as $event_from_civicrm) {
    foreach ($events_from_twingle as $event_from_twingle) {
      if ($event_from_twingle['id'] == $event_from_civicrm['event_id']) {

        // instantiate project with values from TwingleEvent.Get
        $event = _instantiateEvent($event_from_civicrm, $event_from_civicrm['id']);

        // sync event
        $result = _eventSync($event, $event_from_twingle, $twingleApi, $params);
        if ($result['is_error'] != 0) {
          $errors_occurred++;
          $result_values[$event->getId()] =
            $event->getResponse($result['error_message']);
        }
        else {
          $result_values[$event->getId()] = $result['values'];
        }
        break;
      }
    }
  }

  // Give back results
  if ($errors_occurred > 0) {
    $errorMessage = ($errors_occurred > 1)
      ? "$errors_occurred synchronisation processes resulted with an error"
      : "1 synchronisation process resulted with an error";
    return civicrm_api3_create_error(
      $errorMessage,
      $result_values
    );
  }
  else {
    return civicrm_api3_create_success(
      $result_values,
      $params,
      'TwingleEvent',
      'Sync'
    );
  }
}


/**
 * # Instantiates a TwingleEvent
 *
 * @param $values
 * @param null $id
 *
 * @return \CRM_TwingleCampaign_BAO_TwingleEvent
 * @throws \CiviCRM_API3_Exception
 */
function _instantiateEvent($values, $id = NULL): CRM_TwingleCampaign_BAO_TwingleEvent {
  try {
    return new TwingleEvent($values, $id);
  } catch (Exception $e) {
    throw new CiviCRM_API3_Exception(
      $e->getMessage(),
      'instantiation_failed',
      [
        'title'      => $values['description'],
        'id'         => (int) $values['id'],
        'event_id'   => (int) $values['event_id'],
        'project_id' => (int) $values['project_id'],
      ]
    );
  }
}


/**
 * # Update TwingleEvent locally
 * Updates a TwingleEvent campaign locally.
 *
 * @param array $event_from_twingle
 * @param \CRM_TwingleCampaign_BAO_TwingleEvent $event
 * @param array $params
 * @param \CRM_TwingleCampaign_BAO_TwingleApiCall $twingleApi
 *
 * @return array
 */
function _updateEventLocally(array $event_from_twingle,
                       TwingleEvent $event,
                       array $params,
                       TwingleApiCall $twingleApi): array {

  try {
    $event->update($event_from_twingle);
    // If this is a test, do not make db changes
    if ($params['is_test']) {
      return civicrm_api3_create_success(
        $event->getResponse('TwingleEvent ready to update'),
        $params,
        'TwingleEvent',
        'Sync'
      );
    }
    // ... else, update local TwingleEvent campaign
    $event->create();
    return civicrm_api3_create_success(
      $event->getResponse('TwingleEvent updated successfully'),
      $params,
      'TwingleEvent',
      'Sync'
    );
  } catch (Exception $e) {
    Civi::log()->error(
      E::LONG_NAME .
      ' could not update TwingleEvent campaign: ' .
      $e->getMessage(),
      $event->getResponse()
    );
    return civicrm_api3_create_error(
      'Could not update TwingleEvent campaign: ' . $$e->getMessage(),
      $event->getResponse()
    );
  }
}


/**
 * ## Synchronize TwingleEvent
 * Synchronizes a TwingleEvent campaign with an event from Twingle one way.
 *
 * _NOTE:_ Changes on TwingleEvents are not meant to get pushed to Twingle, so
 * the synchronization takes place only one way
 *
 * @param \CRM_TwingleCampaign_BAO_TwingleEvent $event
 * @param array $event_from_twingle
 * @param \CRM_TwingleCampaign_BAO_TwingleApiCall $twingleApi
 * @param array $params
 *
 * @return array
 * @throws \CiviCRM_API3_Exception
 */
function _eventSync(TwingleEvent $event,
              array $event_from_twingle,
              TwingleApiCall $twingleApi,
              array $params): array {

  // If Twingle's timestamp of the event differs from the timestamp of the
  // CiviCRM TwingleEvent campaign, update the campaign on CiviCRM's side.
  // NOTE: Changes on TwingleEvents are not meant to get pushed to Twingle
  if ($event_from_twingle['updated_at'] != $event->lastUpdate()) {
    return _updateEventLocally($event_from_twingle, $event, $params, $twingleApi);
  }

  // If both versions are still synchronized
  else {
    $response = $event->getResponse('TwingleEvent up to date');
    return civicrm_api3_create_success(
      $response,
      $params,
      'TwingleEvent',
      'Sync'
    );
  }
}


