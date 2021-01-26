<?php

use CRM_TwingleCampaign_BAO_CampaignType as CampaignType;
use CRM_TwingleCampaign_Utils_ExtensionCache as ExtensionCache;
use CRM_TwingleCampaign_ExtensionUtil as E;

require_once 'twinglecampaign.civix.php';


/**
 * Implements hook_civicrm_postSave_Campaigns().
 * This function synchronizes TwingleProject campaigns between CiviCRM and
 * Twingle when they get created, edited or cloned. To prevent recursion a no
 * hook flag is getting used.
 *
 * @param $dao
 *
 * @throws CiviCRM_API3_Exception
 */
function twinglecampaign_civicrm_postSave_civicrm_campaign($dao) {

  if (empty($_SESSION['CiviCRM']['de.forumzfd.twinglecampaign']['no_hook']) ||
    $_SESSION['CiviCRM']['de.forumzfd.twinglecampaign']['no_hook'] != TRUE) {

    // extract variables from $dao object
    $hook_campaign_type_id = $dao->campaign_type_id;
    $hook_campaign_id = $dao->id;

    // Get campaign type id for TwingleProject
    $twingle_project_campaign_type_id = civicrm_api3(
      'OptionValue',
      'get',
      ['sequential' => 1, 'name' => 'twingle_project']
    )['values'][0]['value'];

    // If $dao is a TwingleProject campaign, synchronize it
    if ($hook_campaign_type_id == $twingle_project_campaign_type_id) {
      // If the db transaction is still running, add a function to it that will
      // be called afterwards
      if (CRM_Core_Transaction::isActive()) {
        CRM_Core_Transaction::addCallback(
          CRM_Core_Transaction::PHASE_POST_COMMIT,
          'twinglecampaign_postSave_callback',
          [$hook_campaign_id]
        );
      }
      // If the transaction is already finished, call the function directly
      else {
        twinglecampaign_postSave_callback($hook_campaign_id);
      }
    }
  }
  // Remove no hook flag
  unset($_SESSION['CiviCRM']['de.forumzfd.twinglecampaign']['no_hook']);
}

/**
 * This callback function synchronizes a recently updated TwingleProject campaign
 * @param $campaign_id
 * @throws \CiviCRM_API3_Exception
 */
function twinglecampaign_postSave_callback($campaign_id) {
  civicrm_api3('TwingleProject', 'sync', ['id' => $campaign_id]);
}

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function twinglecampaign_civicrm_config(&$config) {
  _twinglecampaign_civix_civicrm_config($config);
}

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
