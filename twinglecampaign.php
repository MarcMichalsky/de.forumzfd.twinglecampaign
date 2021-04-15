<?php

use CRM_TwingleCampaign_Utils_ExtensionCache as ExtensionCache;
use CRM_TwingleCampaign_BAO_TwingleProject as TwingleProject;
use CRM_TwingleCampaign_BAO_TwingleApiCall as TwingleApiCall;
use CRM_TwingleCampaign_ExtensionUtil as E;

require_once 'twinglecampaign.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 *
 * @param $config
 */
function twinglecampaign_civicrm_config(&$config) {
  _twinglecampaign_civix_civicrm_config($config);

  // This dispatchers add event listeners to TwingleDonation.submit
  // (de.systopia.twingle) to call an API-Wrapper which maps incoming Twingle
  // donations to TwingleCampaigns and create soft credits for event initiators.

  // Do only add listeners once
  if (!in_array(
    ["CRM_TwingleCampaign_Utils_APIWrapper", "PREPARE"],
    Civi::dispatcher()->getListeners('civi.api.prepare'))
  ) {
    Civi::dispatcher()->addListener(
      'civi.api.prepare',
      ['CRM_TwingleCampaign_Utils_APIWrapper', 'PREPARE'],
      -100
    );
  }

  // Do only add listeners once
  if (!in_array(
    ["CRM_TwingleCampaign_Utils_APIWrapper", "RESPOND"],
    Civi::dispatcher()->getListeners('civi.api.respond')
  )) {
    Civi::dispatcher()->addListener(
      'civi.api.respond',
      ['CRM_TwingleCampaign_Utils_APIWrapper', 'RESPOND'],
      -100
    );
  }
}


/**
 * Implements hook_civicrm_postSave_Campaign().
 * This function synchronizes TwingleProject campaigns between CiviCRM and
 * Twingle when they get created, edited or cloned. To prevent recursion a no
 * hook flag is getting used.
 *
 * @param $dao
 *
 * @throws \CiviCRM_API3_Exception
 */
function twinglecampaign_civicrm_postSave_civicrm_campaign($dao) {

  if (empty($_SESSION['CiviCRM']['de.forumzfd.twinglecampaign']['no_hook']) ||
    $_SESSION['CiviCRM']['de.forumzfd.twinglecampaign']['no_hook'] != TRUE) {

    // If request is not an API-Call
    if ($_GET['action'] != 'create') {

      // If the db transaction is still running, add a function to it that will
      // be called afterwards
      if (CRM_Core_Transaction::isActive()) {

        if (_validateAndSendInput($dao->id, $dao->campaign_type_id)) {

          CRM_Core_Transaction::addCallback(
            CRM_Core_Transaction::PHASE_POST_COMMIT,
            'twinglecampaign_postSave_campaign_update_callback',
            [$dao->id, $dao->campaign_type_id]
          );
        }
      }

      // If the transaction is already finished, call the function directly
      else {
        twinglecampaign_postSave_campaign_update_callback($dao->id, $dao->campaign_type_id);
      }

    }
    else {
      CRM_Core_Transaction::addCallback(
        CRM_Core_Transaction::PHASE_POST_COMMIT,
        'twinglecampaign_postSave_campaign_update_callback',
        [$dao->id, $dao->campaign_type_id]
      );
    }
  }
  // Remove no hook flag
  unset($_SESSION['CiviCRM']['de.forumzfd.twinglecampaign']['no_hook']);
}

/**
 * ## postSave callback
 * This callback function synchronizes a recently updated TwingleProject or
 * creates a TwingleCampaign
 *
 * @param int $campaign_id
 * @param int $campaign_type_id
 *
 * @throws \CiviCRM_API3_Exception
 */
function twinglecampaign_postSave_campaign_update_callback(
  int $campaign_id,
  int $campaign_type_id
) {

  $twingle_project_campaign_type_id = _get_campaign_type_id_twingle_project();
  $twingle_campaign_campaign_type_id = _get_campaign_type_id_twingle_campaign();

  // If $campaign_type_id is a TwingleProject or TwingleCampaign campaign,
  // synchronize it
  if (
    $campaign_type_id == $twingle_project_campaign_type_id ||
    $campaign_type_id == $twingle_campaign_campaign_type_id
  ) {

    // Set $entity for $campaign_type_id
    if ($campaign_type_id == $twingle_project_campaign_type_id) {
      $entity = 'TwingleProject';
    }
    else {
      $entity = 'TwingleCampaign';
    }

    if (isset($_POST['action'])) {
      if ($_POST['action'] == 'clone' && $entity == 'TwingleProject') {
        unset($_POST['action']);
        $result = civicrm_api3('TwingleProject', 'getsingle',
          ['id' => $campaign_id]
        );
        $id = $result['id'];
        unset($result['id']);
        $project = new TwingleProject($result, $id);
        try {
          $project->clone();
        } catch (Exception $e) {
          Civi::log()->error(
            E::LONG_NAME .
            ' could not clone ' . $entity . ': ' . $e->getMessage()
          );
          CRM_Core_Session::setStatus(
            $e->getMessage(),
            E::ts("Campaign cloning failed"),
            error,
            [unique => TRUE]
          );
        }
      }
    }

    // If a TwingleProject is getting saved
    elseif ($entity == 'TwingleProject') {

      // Synchronize all child TwingleCampaign campaigns
      try {
        civicrm_api3(
          'TwingleCampaign',
          'sync',
          ['parent_id' => $campaign_id]);
      } catch (CiviCRM_API3_Exception $e) {
        CRM_Core_Session::setStatus(
          $e->getMessage(),
          E::ts("TwingleCampaign update failed"),
          error, [unique => TRUE]
        );
        Civi::log()->error(
          E::SHORT_NAME .
          ' Update of TwingleCampaigns failed: ' . $e->getMessage()
        );
      }
    }
    else {
      try {
        civicrm_api3('TwingleCampaign', 'create',
          ['id' => $campaign_id, 'parent_id' => $_POST['parent_id']]);
        CRM_Utils_System::setUFMessage(E::ts('TwingleCampaign was saved.'));
      } catch (CiviCRM_API3_Exception $e) {
        Civi::log()->error(
          'twinglecampaign_postSave_callback ' . $e->getMessage()
        );
      }
    }
  }
}

function _get_campaign_type_id_twingle_project() {
  return ExtensionCache::getInstance()
    ->getCampaignIds()['campaign_types']['twingle_project']['id'];
}

function _get_campaign_type_id_twingle_campaign() {
  return ExtensionCache::getInstance()
    ->getCampaignIds()['campaign_types']['twingle_campaign']['id'];
}

/**
 * Callback to sync a project after its creation.
 * @param int $campaign_id
 */
function twinglecampaign_postSave_project_create_callback(
  int $campaign_id
) {
  try {
    civicrm_api3(
      'TwingleProject',
      'sync',
      ['id' => $campaign_id]);
  } catch (Exception $e) {
    CRM_Core_Session::setStatus(
      $e->getMessage(),
      E::ts("TwingleProject creation failed"),
      error, [unique => TRUE]
    );
    Civi::log()->error(
      E::SHORT_NAME .
      ' Update of TwingleProject creation failed: ' . $e->getMessage()
    );
  }
}

/**
 * First validate and then sends the input of this transaction to Twinge.
 * If the call to the Twingle API succeeded, this function returns TRUE;
 *
 * @param $id
 * @param $campaign_type_id
 *
 * @return bool
 * @throws \CiviCRM_API3_Exception
 */
function _validateAndSendInput($id, $campaign_type_id): bool {

  // Set callback for cloning
  if (isset($_POST['action'])) {
    CRM_Core_Transaction::addCallback(
      CRM_Core_Transaction::PHASE_POST_COMMIT,
      'twinglecampaign_postSave_campaign_update_callback',
      [$id, $campaign_type_id]
    );
    return FALSE;
  }

  if ($campaign_type_id == _get_campaign_type_id_twingle_project()) {

    // Instantiate project
    $project = new TwingleProject();

    // Translate custom fields from $_POST
    $customFields = [];
    $customFieldsKeys = preg_grep('/^custom_/', array_keys($_POST));
    foreach ($customFieldsKeys as $key) {
      $customFields[preg_replace('/_-?\d*$/', '', $key)] =
        $_POST[$key];
    }
    $project->translateCustomFields(
      $customFields,
      TwingleProject::OUT
    );
    TwingleProject::formatValues($customFields, TwingleProject::OUT);

    // Update project
    $project->update($customFields);

    // Validate project values
    $validation = $project->validate();

    // If the input is valid, send it to Twingle
    if ($validation['valid']) {

      // Try to retrieve twingleApi from cache or create a new
      $twingleApi = Civi::cache()->get('twinglecampaign_twingle_api');
      if (NULL === $twingleApi) {
        try {
          $twingleApi =
            new TwingleApiCall(Civi::settings()->get('twingle_api_key'));
        } catch (Exception $e) {

          // Roll back transaction if input validation failed
          CRM_Core_Transaction::rollbackIfFalse(FALSE);

          CRM_Core_Session::setStatus(
            $e->getMessage(),
            E::ts("Could not retrieve Twingle API key"),
            error,
            [unique => TRUE]
          );
          Civi::log()->error(
            E::SHORT_NAME .
            ' Could not retrieve Twingle API key: ' . $e->getMessage()
          );
        }
        Civi::cache('long')->set('twinglecampaign_twingle_api', $twingleApi);
      }

      try {
        // Complement project values with values from Twingle if it has a
        // project_id
        if ($project->getProjectId()) {
          $project_from_twingle = $twingleApi->getProject($project->getProjectId());
          $project->complement($project_from_twingle);
        }
        // If this campaign is just about to become created, add a callback to
        // sync it after the transaction has finished
        else {
          CRM_Core_Transaction::addCallback(
            CRM_Core_Transaction::PHASE_POST_COMMIT,
            'twinglecampaign_postSave_project_create_callback', [$id]
          );
          return FALSE;
        }

        // Push project
        require E::path() . '/api/v3/TwingleProject/Sync.php';
        $result = _pushProjectToTwingle($project, $twingleApi, [], FALSE);
        if ($result['is_error'] != 0) {
          throw new \CiviCRM_API3_Exception($result['error_message']);
        }
      } catch (Exception $e) {

        // Roll back transaction if input validation failed
        CRM_Core_Transaction::rollbackIfFalse(FALSE);

        // Display and log error message
        CRM_Core_Session::setStatus(
          $e->getMessage(),
          E::ts("TwingleProject synchronization failed: %1",
            [1 => $e->getMessage()]),
          error,
          [unique => TRUE]
        );
        Civi::log()->error(
          E::SHORT_NAME .
          ' TwingleProject synchronization failed: ' . $e->getMessage()
        );
        // Push failed
        return FALSE;
      }
      // Push succeeded
      return TRUE;
    }
    // Display error message if validation failed
    else {

      // Roll back transaction if input validation failed
      CRM_Core_Transaction::rollbackIfFalse(FALSE);

      // Build error message
      $errorMessage = '<ul>';
      foreach ($validation['messages'] as $message) {
        $errorMessage = $errorMessage . '<li>' . $message . '</li>';
      }
      $errorMessage = $errorMessage . '</ul>';

      CRM_Core_Session::setStatus(
        $errorMessage,
        E::ts("Input validation failed"),
        error,
        [unique => TRUE]
      );
      // Validation failed
      return FALSE;
    }
  }

  // TwingleCampaigns always return TRUE;
  return TRUE;
}


///**
// * ## Implements hook_civicrm_post().
// *
// * @throws CiviCRM_API3_Exception
// */
//function twinglecampaign_civicrm_post($op, $objectName, $objectId, &$objectRef) {
//  if ($op == 'delete') {
//
//    if (CRM_Core_Transaction::isActive()) {
//      CRM_Core_Transaction::addCallback(
//        CRM_Core_Transaction::PHASE_POST_COMMIT,
//        'twinglecampaign_post_callback',
//        [$hook_campaign_id]
//      );
//    }
//    // If the transaction is already finished, call the function directly
//    else {
//      twinglecampaign_post_callback($hook_campaign_id);
//    }
//  }
//}

///**
// * ## post callback
// * This callback function deletes a TwingleProject on Twingle's side
// *
// * @param $campaign_id
// */
//function twinglecampaign_post_callback($campaign_id) {
//  $result = civicrm_api('TwingleProject', 'delete', [$campaign_id]);
//  if ($result['is_error'] != 0) {
//    CRM_Utils_System::setUFMessage($result['error_message']);
//  }
//}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function twinglecampaign_civicrm_xmlMenu(&$files) {
  _twinglecampaign_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function twinglecampaign_civicrm_install() {
  _twinglecampaign_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function twinglecampaign_civicrm_postInstall() {
  _twinglecampaign_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function twinglecampaign_civicrm_uninstall() {
  _twinglecampaign_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function twinglecampaign_civicrm_enable() {
  _twinglecampaign_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function twinglecampaign_civicrm_disable() {
  _twinglecampaign_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function twinglecampaign_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _twinglecampaign_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function twinglecampaign_civicrm_managed(&$entities) {
  _twinglecampaign_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function twinglecampaign_civicrm_caseTypes(&$caseTypes) {
  _twinglecampaign_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function twinglecampaign_civicrm_angularModules(&$angularModules) {
  _twinglecampaign_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function twinglecampaign_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _twinglecampaign_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function twinglecampaign_civicrm_entityTypes(&$entityTypes) {
  _twinglecampaign_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_thems().
 */
function twinglecampaign_civicrm_themes(&$themes) {
  _twinglecampaign_civix_civicrm_themes($themes);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 *
 * function twinglecampaign_civicrm_preProcess($formName, &$form) {
 *
 * } // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 *
 * function twinglecampaign_civicrm_navigationMenu(&$menu) {
 * _twinglecampaign_civix_insert_navigation_menu($menu, 'Mailings', array(
 * 'label' => E::ts('New subliminal message'),
 * 'name' => 'mailing_subliminal_message',
 * 'url' => 'civicrm/mailing/subliminal',
 * 'permission' => 'access CiviMail',
 * 'operator' => 'OR',
 * 'separator' => 0,
 * ));
 * _twinglecampaign_civix_navigationMenu($menu);
 * } // */
