<?php


namespace CRM\TwingleCampaign\BAO;

use Civi;

class Configuration {

  private static $settingsKeys = [
    'twingle_api_key',
    'twinglecampaign_xcm_profile',
    'twinglecampaign_start_case',
  ];


  /**
   * Stores a setting in Civi::settings
   *
   * @param array $settings
   * Expects an array with key => value for the setting
   */
  public static function set(array $settings) {
      Civi::settings()->add($settings);
  }


  /**
   * Returns a specific value of a setting if the key is passed as parameter.
   * Else all settings will be returned als associative array.
   *
   * @param null $key
   * The name of the setting or NULL
   *
   * @return array|mixed|null
   */
  public static function get($key = NULL) {
    if ($key) {
      return Civi::settings()->get($key);
    }
    else {
      $settings = [];
      foreach (self::$settingsKeys as $key) {
        $settings[$key] = Civi::settings()->get($key);
      }
      return $settings;
    }
  }


  /**
   * Delete all settings of the TwingleCampaign extension
   */
  public static function deleteAll() {
    foreach (self::$settingsKeys as $key) {
      Civi::settings()->set($key, NULL);
    }
  }

}