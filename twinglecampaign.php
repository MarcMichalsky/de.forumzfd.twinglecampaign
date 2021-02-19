<?php

use CRM_TwingleCampaign_Utils_ExtensionCache as ExtensionCache;
use CRM_TwingleCampaign_ExtensionUtil as E;

require_once 'twinglecampaign.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 * @param $config
 */
function twinglecampaign_civicrm_config(&$config) {
  _twinglecampaign_civix_civicrm_config($config);

  // This dispatcher adds an event listener to TwingleDonation.submit
  // (de.systopia.twingle) and calls an API-Wrapper which maps incoming Twingle
  // donations to TwingleCampaigns.
  Civi::dispatcher()->addListener(
    'civi.api.prepare',
    ['CRM_TwingleCampaign_Utils_APIWrapper', 'PREPARE'],
    -100
  );
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


    // If the db transaction is still running, add a function to it that will
    // be called afterwards
    if (CRM_Core_Transaction::isActive()) {
      CRM_Core_Transaction::addCallback(
        CRM_Core_Transaction::PHASE_POST_COMMIT,
        'twinglecampaign_postSave_campaign_callback',
        [$dao->id, $dao->campaign_type_id]
      );
    }
    // If the transaction is already finished, call the function directly
    else {
      twinglecampaign_postSave_campaign_callback($dao->id, $dao->campaign_type_id);
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
function twinglecampaign_postSave_campaign_callback (
  int $campaign_id,
  int $campaign_type_id
) {

  // Get campaign type id for TwingleProject
  $twingle_project_campaign_type_id =
    ExtensionCache::getInstance()
      ->getCampaigns()['campaign_types']['twingle_project']['id'];

  // Get campaign type id for TwingleCampaign
  $twingle_campaign_campaign_type_id =
    ExtensionCache::getInstance()
      ->getCampaigns()['campaign_types']['twingle_campaign']['id'];


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
      if ($_POST['action'] == 'clone') {
        unset($_POST['action']);
        $result = civicrm_api3($entity, 'getsingle',
          ['id' => $campaign_id]
        )['values'][$campaign_id];
        $className = 'CRM_TwingleCampaign_BAO_' . $entity;
        $id = $result['id'];
        unset($result['id']);
        $project = new $className($result, $id);
        try {
          $project->clone();
        } catch (Exception $e) {
          Civi::log()->error(
            E::LONG_NAME .
            ' could not clone ' . $entity . ': ' . $e->getMessage()
          );
          CRM_Utils_System::setUFMessage($entity . ' could not get cloned.');
        }
      }
      elseif ($entity == 'TwingleProject') {
        try {
          civicrm_api3('TwingleProject', 'sync', ['id' => $campaign_id]);
          CRM_Utils_System::setUFMessage('TwingleProject was saved.');
        } catch (CiviCRM_API3_Exception $e) {
          Civi::log()->error(
            'twinglecampaign_postSave_callback ' . $e->getMessage()
          );
        }
      }
      else {
        try {
          civicrm_api3('TwingleCampaign', 'create', ['id' => $campaign_id]);
          CRM_Utils_System::setUFMessage('TwingleCampaign was saved.');
        } catch (CiviCRM_API3_Exception $e) {
          Civi::log()->error(
            'twinglecampaign_postSave_callback ' . $e->getMessage()
          );
        }
      }
    }
    elseif ($entity == 'TwingleProject') {
      // Also synchronize all child TwingleCampaign campaigns
      try {
        civicrm_api3('TwingleCampaign', 'sync', ['project_id' => $campaign_id]);
      } catch (CiviCRM_API3_Exception $e) {
        Civi::log()->error(
          'twinglecampaign_postSave_callback ' . $e->getMessage()
        );
      }
      try {
        civicrm_api3('TwingleProject', 'sync', ['id' => $campaign_id]);
        CRM_Utils_System::setUFMessage('TwingleProject was saved.');
      } catch (CiviCRM_API3_Exception $e) {
        Civi::log()->error(
          'twinglecampaign_postSave_callback ' . $e->getMessage()
        );
      }
    } else {
      try {
        civicrm_api3('TwingleCampaign', 'create', ['id' => $campaign_id]);
        CRM_Utils_System::setUFMessage('TwingleCampaign was saved.');
      } catch (CiviCRM_API3_Exception $e) {
        Civi::log()->error(
          'twinglecampaign_postSave_callback ' . $e->getMessage()
        );
      }
    }
  }
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
