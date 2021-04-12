<?php

use CRM_TwingleCampaign_BAO_CampaignType as CampaignType;
use CRM_TwingleCampaign_BAO_CustomField as CustomField;
use CRM_TwingleCampaign_BAO_CustomGroup as CustomGroup;
use CRM_TwingleCampaign_BAO_Configuration as Configuration;
use CRM_TwingleCampaign_BAO_OptionValue as OptionValue;
use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;
use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_TwingleCampaign_Upgrader extends CRM_TwingleCampaign_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * @throws \CiviCRM_API3_Exception
   */
  public function upgrade_01() {

    $campaign_info = require E::path() .
      '/CRM/TwingleCampaign/resources/campaigns.php';
    $option_values = require E::path() .
      '/CRM/TwingleCampaign/resources/option_values.php';

    // Create campaign types
    foreach ($campaign_info['campaign_types'] as $campaign_type) {
      new CampaignType($campaign_type);
    }
    foreach (CampaignType::getCampaignTypes() as $campaign_type) {
      $campaign_type->create(true);
    }

    // Create custom groups
    foreach ($campaign_info['custom_groups'] as $custom_group) {
      foreach (CampaignType::getCampaignTypes() as $campaign_type) {
        if ($campaign_type->getName() == $custom_group['campaign_type']) {
          $custom_group['extends_entity_column_value'] = $campaign_type->getValue();
        }
      }
      $cg = new CustomGroup($custom_group);
      $cg->create(true);
    }

    // Create custom fields
    foreach ($campaign_info['custom_fields'] as $custom_field) {
      $cf = new CustomField($custom_field);
      $cf->create(true);
    }

    // Create option values
    foreach ($option_values as $option_value) {
      $ov = new OptionValue($option_value);
      $ov->create();
    }

    return TRUE;
  }

  /**
   * @throws \Exception
   */
  public function install() {
    // Create campaign types, custom fields and custom groups by the contents
    // of the json file "campaigns.json"

    $campaign_info = Cache::getInstance()->getCampaigns();
    $option_values = Cache::getInstance()->getOptionValues();

    // Create campaign types
    foreach ($campaign_info['campaign_types'] as $campaign_type) {
      new CampaignType($campaign_type);
    }
    foreach (CampaignType::getCampaignTypes() as $campaign_type) {
      $campaign_type->create();
    }

    // Create custom groups
    foreach ($campaign_info['custom_groups'] as $custom_group) {
      foreach (CampaignType::getCampaignTypes() as $campaign_type) {
        if ($campaign_type->getName() == $custom_group['campaign_type']) {
          $custom_group['extends_entity_column_value'] = $campaign_type->getValue();
        }
      }
      $cg = new CustomGroup($custom_group);
      $cg->create();
    }

    // Create custom fields
    foreach ($campaign_info['custom_fields'] as $custom_field) {
      $cf = new CustomField($custom_field);
      $cf->create();
    }

    // Create option values
    foreach ($option_values as $option_value) {
      $ov = new OptionValue($option_value);
      $ov->create();
    }

    // setup cron job to trigger synchronization
    try {
      civicrm_api3('Job', 'create', [
        'run_frequency' => "Hourly",
        'name'          => "TwingleSync",
        'api_entity'    => "TwingleSync",
        'api_action'    => "sync",
        'description'   => E::ts("Syncronizes all TwingleProjects an TwingleEvents between CiviCRM and Twingle"),
        'is_active'     => 1,
      ]);
    } catch (CiviCRM_API3_Exception $e) {
      Civi::log()->error(
        E::LONG_NAME .
        ' could not create scheduled job on extension installation: ' .
        $e->getMessage()
      );
      CRM_Core_Session::setStatus(
        E::ts('Could not create scheduled job "TwingleSync".'),
        E::ts('Scheduled Job'),
        error
      );
      CRM_Utils_System::setUFMessage(E::ts('Could not create scheduled job "TwingleSync". Your Campaigns will not get synchronized to Twingle.'));
    }
  }

  /**
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public function uninstall() {

    $campaign_info = Cache::getInstance()->getCampaigns();
    $option_values = Cache::getInstance()->getOptionValues();

    // Delete campaign types
    foreach ($campaign_info['campaign_types'] as $campaign_type) {
      $result = CampaignType::fetch($campaign_type['name']);
      if ($result) {
        $result->delete();
      }
    }

    // Delete custom groups
    foreach ($campaign_info['custom_groups'] as $custom_group) {
      $result = CustomGroup::fetch($custom_group['name']);
      if ($result) {
        $result->delete();
      }
    }

    // Delete option values
    foreach ($option_values as $option_value) {
      $result = OptionValue::fetch($option_value['name']);
      if ($result) {
        $result->delete();
      }
    }

    // Delete all settings for this extension
    Configuration::deleteAll();

    // Delete cron job
    try {
      $jobId = civicrm_api3('Job', 'getsingle', [
        'name' => "TwingleSync",
      ])['id'];
      civicrm_api3('Job', 'delete', [
        'id' => $jobId,
      ]);
    } catch (CiviCRM_API3_Exception $e) {
      Civi::log()->error(
        E::LONG_NAME .
        ' could not delete scheduled job on extension uninstallation: ' .
        $e->getMessage()
      );
      CRM_Core_Session::setStatus(
        E::ts('Could not delete scheduled job "TwingleSync".'),
        E::ts('Scheduled Job'),
        error
      );
    }

  }

  /**
   * @throws \Exception
   */
  public function enable() {
    // Enable cron job
    try {
      $jobId = civicrm_api3('Job', 'getsingle', [
        'name' => "TwingleSync",
      ])['id'];
      civicrm_api3('Job', 'create', [
        'id'        => $jobId,
        'is_active' => 1,
      ]);
    } catch (CiviCRM_API3_Exception $e) {
      Civi::log()->error(
        E::LONG_NAME .
        ' could not activate scheduled job on extension activation: ' .
        $e->getMessage()
      );
      CRM_Core_Session::setStatus(
        E::ts('Could not activate scheduled job "TwingleSync". Your Campaigns will not get synchronized to Twingle.'),
        E::ts('Scheduled Job'),
        error
      );
    }
  }

  /**
   * @throws \Exception
   */
  public function disable() {

    // Disable cron job
    try {
      $jobId = civicrm_api3('Job', 'getsingle', [
        'name' => 'TwingleSync',
      ])['id'];
      civicrm_api3('Job', 'create', [
        'id'        => $jobId,
        'is_active' => 0,
      ]);
    } catch (CiviCRM_API3_Exception $e) {
      Civi::log()->error(
        E::LONG_NAME .
        ' could not disable scheduled job on extension deactivation: ' .
        $e->getMessage()
      );
      CRM_Core_Session::setStatus(
        E::ts('Could not disable scheduled job "TwingleSync".'),
        E::ts('Scheduled Job'),
        error
      );
    }

    // Remove Twingle api key from settings
    Civi::settings()->revert('twingle_api_key');

  }

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   *
   * public function upgrade_4200() {
   * $this->ctx->log->info('Applying update 4200');
   * CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
   * CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
   * return TRUE;
   * } // */


  /**
   * Example: Run an external SQL script.
   *
   * @return TRUE on success
   * @throws Exception
   * public function upgrade_4201() {
   * $this->ctx->log->info('Applying update 4201');
   * // this path is relative to the extension base dir
   * $this->executeSqlFile('sql/upgrade_4201.sql');
   * return TRUE;
   * } // */


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk.
   *
   * @return TRUE on success
   * @throws Exception
   * public function upgrade_4202() {
   * $this->ctx->log->info('Planning update 4202'); // PEAR Log interface
   *
   * $this->addTask(E::ts('Process first step'), 'processPart1', $arg1, $arg2);
   * $this->addTask(E::ts('Process second step'), 'processPart2', $arg3, $arg4);
   * $this->addTask(E::ts('Process second step'), 'processPart3', $arg5);
   * return TRUE;
   * }
   * public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
   * public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
   * public function processPart3($arg5) { sleep(10); return TRUE; }
   * // */


  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
   * public function upgrade_4203() {
   * $this->ctx->log->info('Planning update 4203'); // PEAR Log interface
   *
   * $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM
   *   civicrm_contribution');
   * $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM
   *   civicrm_contribution'); for ($startId = $minId; $startId <= $maxId;
   *   $startId += self::BATCH_SIZE) {
   * $endId = $startId + self::BATCH_SIZE - 1;
   * $title = E::ts('Upgrade Batch (%1 => %2)', array(
   * 1 => $startId,
   * 2 => $endId,
   * ));
   * $sql = '
   * UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
   * WHERE id BETWEEN %1 and %2
   * ';
   * $params = array(
   * 1 => array($startId, 'Integer'),
   * 2 => array($endId, 'Integer'),
   * );
   * $this->addTask($title, 'executeSql', $sql, $params);
   * }
   * return TRUE;
   * } // */

}
