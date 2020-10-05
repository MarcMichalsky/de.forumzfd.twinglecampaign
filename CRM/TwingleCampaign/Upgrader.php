<?php

use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM\TwingleCampaign\BAO as BAO;

include E::path() . '/CRM/TwingleCampaign/Upgrader/BAO/CampaignType.php';
include E::path() . '/CRM/TwingleCampaign/Upgrader/BAO/CustomField.php';
include E::path() . '/CRM/TwingleCampaign/Upgrader/BAO/CustomGroup.php';

/**
 * Collection of upgrade steps.
 */
class CRM_TwingleCampaign_Upgrader extends CRM_TwingleCampaign_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed.
   *
   * @throws \Exception
   */
  public function install() {

  }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public function uninstall() {

  }

  /**
   * Example: Run a simple query when a module is enabled.
   *
   * @throws \Exception
   */
  public function enable() {
    // Create campaign types, custom fields and custom groups by the contents
    // of the json file "campaigns.json"

    $json_file = file_get_contents(E::path() . '/CRM/TwingleCampaign/Upgrader/resources/campaigns.json');
    $campaign_info = json_decode($json_file, TRUE);

    if (!$campaign_info) {
      \Civi::log()->error("Could not read json file");
      throw new Exception('Could not read json file');
    }

    // Create campaign types
    foreach ($campaign_info['campaign_types'] as $campaign_type) {
      new BAO\CampaignType($campaign_type);
    }
    foreach (BAO\CampaignType::getCampaignTypes() as $campaign_type) {
      $campaign_type->create();
    }

    // Create custom groups
    foreach ($campaign_info['custom_groups'] as $custom_group) {
      foreach (BAO\CampaignType::getCampaignTypes() as $campaign_type) {
        if ($campaign_type->getName() == $custom_group['campaign_type']) {
          $custom_group['extends_entity_column_value'] = $campaign_type->getValue();
        }
      }
      $cg = new BAO\CustomGroup($custom_group);
      $cg->create();
    }

    // Create custom fields
    foreach ($campaign_info['custom_fields'] as $custom_field) {
      $cf = new BAO\CustomField($custom_field);
      $cf->create();
    }
  }

  /**
   * Example: Run a simple query when a module is disabled.
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
 public function disable() {

   $json_file = file_get_contents(E::path() . '/CRM/TwingleCampaign/Upgrader/resources/campaigns.json');
   $campaign_info = json_decode($json_file, TRUE);

   if (!$campaign_info) {
     \Civi::log()->error("Could not read json file");
     throw new Exception('Could not read json file');
   }

   // Delete campaign types
   foreach ($campaign_info['campaign_types'] as $campaign_type) {
     $result = BAO\CampaignType::fetch($campaign_type['name']);
     if ($result) {
       $result->delete();
     }
   }

   // Delete custom groups
   foreach ($campaign_info['custom_groups'] as $custom_group) {
     $result = BAO\CustomGroup::fetch($custom_group['name']);
     if ($result) {
       $result->delete();
     }
   }
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
