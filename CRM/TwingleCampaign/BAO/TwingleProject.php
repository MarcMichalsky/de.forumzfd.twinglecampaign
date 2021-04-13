<?php

use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;
use CRM_TwingleCampaign_BAO_Campaign as Campaign;
use CRM_TwingleCampaign_Utils_StringOperations as StringOperations;
use CRM_TwingleCampaign_ExtensionUtil as E;


class CRM_TwingleCampaign_BAO_TwingleProject extends Campaign {

  // All available contact fields in Twingle project forms
  const contactFields = [
    'salutation',
    'firstname',
    'lastname',
    'company',
    'birthday',
    'street',
    'postal_code',
    'city',
    'country',
    'telephone',
  ];

  // All available donation rhythms in Twingle project forms
  const donationRhythm = [
    'yearly',
    'halfyearly',
    'quarterly',
    'monthly',
    'one_time',
  ];

  /**
   * ## TwingleProject constructor
   *
   * @param array $values
   * Project values
   *
   * @param int|null $id
   * CiviCRM Campaign id
   */
  public function __construct(array $values = [], int $id = NULL) {

    // If the $values originally come from the TwingleProject.get API, they
    // contain the internal CiviCRM id as 'id' and the external Twingle id as
    // 'project_id'. In this case 'id' gets replaced with 'project_id'
    if (isset($values['project_id'])) {
      $values['id'] = $values['project_id'];
      unset($values['project_id']);
    }

    parent::__construct($values, $id);

    $this->prefix = 'twingle_project_';
    $this->values['campaign_type_id'] = 'twingle_project';
    $this->id_custom_field = Cache::getInstance()
      ->getCustomFieldMapping()['twingle_project_id'];

  }

  /**
   * ## Export values
   * Change all values to a format accepted by the Twingle API.
   *
   * @return array
   * Array with all values ready to send to the Twingle API
   * @throws \Exception
   */
  public function export(): array {
    // copy project values
    $values = $this->values;

    // Strings to booleans
    $this->intToBool($values);

    // Strings to integers
    $this->strToInt($values);

    // Build counter-url array
    if (isset($values['counter-url']) && is_string($values['counter-url'])) {
      $url = $values['counter-url'];
      unset($values['counter-url']);
      $values['counter-url']['url'] = $url;
    }

    // Remove campaign_type_id
    unset($values['campaign_type_id']);

    return $values;
  }

  /**
   * ## Int to bool
   * Changes all project values that are defined as CiviCRM 'Boolean' types
   * from strings to booleans.
   *
   * @param array $values
   *
   * @throws \Exception
   */
  private function intToBool(array &$values) {

    $boolArrays = [
      'payment_methods',
      'donation_rhythm',
    ];

    foreach ($values as $key => $value) {
      if (CRM_TwingleCampaign_BAO_Campaign::isBoolean(
        $key,
        CRM_TwingleCampaign_BAO_Campaign::PROJECT
      )) {
        $values[$key] = (bool) $value;
      }
      elseif (in_array($key, $boolArrays)) {
        foreach ($values[$key] as $_key => $_value) {
          if (is_bool($_value)) {
            // nothing to do here
          }
          elseif (is_numeric($_value) && $_value < 2 || empty($_value)) {
            $values[$key][$_key] = (bool) $_value;
          }
          else {
            unset($values[$key][$_key]);
          }
        }
      }
      elseif (is_array($value)) {
        $this->intToBool($values[$key]);
      }
    }
  }

  /**
   * ## Int to bool
   * Changes all project values that are strings but originally came as integers
   * back to integers.
   *
   * @param array $values
   *
   * @throws \Exception
   */
  private function strToInt(array &$values) {
    foreach ($values as $key => $value) {
      if (ctype_digit($value)) {
        $values[$key] = intval($value);
      }
      elseif (is_array($value)) {
        $this->strToInt($values[$key]);
      }
    }
  }

  /**
   * ## Create this TwingleProject as a campaign in CiviCRM
   *
   * Returns _TRUE_ if creation was successful or _FALSE_ if it creation failed.
   *
   * @param bool $no_hook
   * Do not trigger postSave hook to prevent recursion
   *
   * @return bool
   * @throws \Exception
   */
  public function create(bool $no_hook = FALSE): bool {

    $result = parent::create($no_hook);

    // Check if campaign was created successfully
    if ($result['is_error'] == 0) {
      return TRUE;
    }
    else {
      throw new Exception($result['error_message']);
    }
  }

  /**
   * ## Update instance values
   *
   * @param array $values
   *
   * @override CRM_TwingleCampaign_BAO_Campaign::update()
   */
  public function update(array $values) {

    // Remove old values
    unset($this->values);

    // Get allowed values
    $projectOptionsKeys = Cache::getInstance()
      ->getTemplates()['TwingleProject']['project_options'];
    $projectEmbedDataKeys = Cache::getInstance()
      ->getTemplates()['TwingleProject']['project_embed_data'];
    $projectPaymentMethodsKeys = Cache::getInstance()
      ->getTemplates()['TwingleProject']['payment_methods'];

    // Sort allowed values into arrays
    foreach ($values as $key => $value) {
      if ($key == 'project_options') {
        foreach ($value as $optionKey => $optionValue) {
          if (in_array($optionKey, $projectOptionsKeys)) {
            $this->values['project_options'][$optionKey] = $optionValue;
          }
        }
      }
      elseif ($key == 'embed') {
        foreach ($value as $embedKey => $embedValue) {
          if (in_array($embedKey, $projectEmbedDataKeys)) {
            $this->values['embed'][$embedKey] = $embedValue;
          }
        }
      }
      elseif ($key == 'payment_methods') {
        foreach ($value as $paymentMethodKey => $paymentMethodValue) {
          if (in_array($paymentMethodKey, $projectPaymentMethodsKeys)) {
            $this->values['payment_methods'][$paymentMethodKey] =
              $paymentMethodValue;
          }
        }
      }
      elseif ($key == 'counter-url' && is_array($value)) {
        $this->values['counter-url'] = $value['url'];
      }
      else {
        parent::update([$key => $value]);
      }
    }
  }

  /**
   * ## Complement campaign values
   * Complement existing campaign values with new ones
   *
   * @param array $arrayToComplement
   *
   * @overrides CRM_TwingleCampaign_BAO_Campaign
   */
  public function complement(array $arrayToComplement) {

    // Set all contact fields to false
    if (
      isset($arrayToComplement['values']['project_options']['contact_fields'])
    ) {
    foreach (
      $arrayToComplement['values']['project_options']['contact_fields']
      as $key => $value
    ) {
      $arrayToComplement['values']['project_options']['contact_fields'][$key]
        = FALSE;
    }
  }

    parent::complement($arrayToComplement);
  }

  /**
   * ## Clone this TwingleProject
   * This method removes the id and the identifier from this instance and in
   * the next step it pushes the clone as a new project with the same values to
   * Twingle.
   *
   * @throws \Exception
   */
  public function clone() {
    $this->values['id'] = '';
    $this->values['identifier'] = '';
    $this->create(); // this will also trigger the postSave hook
  }

  /**
   * ## Validate
   * Validates project values and returns an array containing the result and
   * another array with eventual error messages.
   *
   * @return array ['valid' => bool, 'messages' => array]
   */
  public function validate(): array {
    $valid = TRUE;
    $messages = [];

    // Validate email address
    if (
      !filter_var(
        $this->values['project_options']['bcc_email_address'],
        FILTER_VALIDATE_EMAIL
      )
      && !empty($this->values['project_options']['bcc_email_address'])
    ) {
      $valid = FALSE;
      $messages[] = E::ts("BCC email invalid");
    }

    // Validate hexadecimal color fields
    $colorFields =
      [
        'design_background_color',
        'design_primary_color',
        'design_font_color',
      ];
    foreach ($colorFields as $colorField) {
      if (
        !empty($this->values['project_options'][$colorField]) &&
        (
          !(
            ctype_xdigit($this->values['project_options'][$colorField]) ||
            is_integer($this->values['project_options'][$colorField])
          ) ||
          strlen((string) $this->values['project_options'][$colorField]) > 6
        )
      ) {
        $valid = FALSE;
        $messages[] =
          E::ts("Invalid hexadecimal value in color field: %1",
            [1 => $colorField]);
      }
    }

    // Check if donation values are integers and if proposed donation value
    // lies between max and min values
    if (
      // Is integer and >= 0 or empty
      (
        empty($this->values['project_options']['donation_value_default']) ||
        (
          is_integer($this->values['project_options']['donation_value_default']) ||
          ctype_digit($this->values['project_options']['donation_value_default'])
        ) && (
          $this->values['project_options']['donation_value_default'] >= 0
        )
      ) && (
        empty($this->values['project_options']['donation_value_min']) ||
        (
          is_integer($this->values['project_options']['donation_value_min']) ||
          ctype_digit($this->values['project_options']['donation_value_min'])
        ) && (
          $this->values['project_options']['donation_value_max'] >= 0
        )
      ) && (
        empty($this->values['project_options']['donation_value_max']) ||
        (
          is_integer($this->values['project_options']['donation_value_max']) ||
          ctype_digit($this->values['project_options']['donation_value_max'])
        ) && (
          $this->values['project_options']['donation_value_max'] >= 0
        )
      )
    ) {
      if (
        // all empty
        empty($this->values['project_options']['donation_value_default']) &&
        empty($this->values['project_options']['donation_value_min']) &&
        empty($this->values['project_options']['donation_value_max'])
      ) {
        // nothing to validate
      }
      elseif (
        // Max empty, min not empty
        (!empty($this->values['project_options']['donation_value_min']) &&
          empty($this->values['project_options']['donation_value_max'])) ||
        // Max empty, default not empty
        (!empty($this->values['project_options']['donation_value_default']) &&
          empty($this->values['project_options']['donation_value_max']))
      ) {
        $valid = FALSE;
        $messages[] =
          E::ts("Missing maximum donation value");
      }
      else {
        if (
          // Min >= Max
          $this->values['project_options']['donation_value_min'] >=
          $this->values['project_options']['donation_value_max']
        ) {
          $valid = FALSE;
          $messages[] =
            E::ts("Maximum donation value must be higher than the minimum");
        }
        elseif (
          // Default < min or default > max
          $this->values['project_options']['donation_value_default'] <
          $this->values['project_options']['donation_value_min'] ||
          $this->values['project_options']['donation_value_default'] >
          $this->values['project_options']['donation_value_max']
        ) {
          $valid = FALSE;
          $messages[] =
            E::ts("Default donation value must lie in between maximum and minimum values");
        }
      }
    }
    else {
      $valid = FALSE;
      $messages[] =
        E::ts("Donation values (Min, Max, Default) must be positive integers");
    }

    // Validate sharing url
    $urlFields =
      [
        'custom_css',
        'share_url',
      ];

    foreach ($urlFields as $urlField) {
      if (!empty($this->values['project_options'][$urlField])) {
        if (
          !filter_var(
            $this->values['project_options'][$urlField],
            FILTER_VALIDATE_URL
          ) || empty($this->values['project_options'][$urlField])
        ) {
          $valid = FALSE;
          $messages[] =
            E::ts("Invalid URL: %1", [1 => $urlField]);
        }
      }
    }

    return ['valid' => $valid, 'messages' => $messages];
  }


  /**
   * ## Translate values between CiviCRM Campaigns and Twingle formats
   *
   * Constants for **$direction**:<br>
   * **TwingleProject::IN** translate array values from Twingle to CiviCRM
   * format<br>
   * **TwingleProject::OUT** translate array values from CiviCRM to Twingle
   * format
   *
   * @param array $values
   * array of values to translate
   *
   * @param string $direction
   * const: TwingleProject::IN or TwingleProject::OUT
   *
   * @throws Exception
   */
  public
  static function formatValues(array &$values, string $direction) {

    if ($direction == self::IN) {

      // Change timestamp into DateTime string
      if (isset($values['last_update'])) {
        $values['last_update'] =
          self::getDateTime($values['last_update']);
      }

      // empty project_type to 'default'
      if (empty($values['type'])) {
        $values['type'] = 'default';
      }

      // Flatten project options array
      foreach ($values['project_options'] as $key => $value) {
        $values[$key] = $value;
      }
      unset($values['project_options']);

      // Flatten embed codes array
      foreach ($values['embed'] as $key => $value) {
        $values[$key] = $value;
      }
      unset($values['embed']);

      // Flatten button array
      if (isset($values['buttons'])) {
        foreach (
          $values['buttons'] as $button_key => $button
        ) {
          $values[$button_key] = $button['amount'];
        }
        unset($values['buttons']);
      }

      // Invert and explode exclude_contact_fields
      if (isset($values['exclude_contact_fields'])) {
        $values['contact_fields'] =
          array_diff(
            self::contactFields,
            explode(',', $values['exclude_contact_fields'])
          );
        unset($values['exclude_contact_fields']);
      }

      // Explode mandatory_contact_fields
      if (isset($values['mandatory_contact_fields'])) {
        $values['mandatory_contact_fields'] =
          explode(
            ',',
            $values['mandatory_contact_fields']
          );
        unset($values['mandatory_contact_fields']);
      }

      // Explode languages
      if (isset($values['languages'])) {
        $values['languages'] =
          explode(',', $values['languages']);
      }

      // Divide payment methods array into one time and recurring payment
      // methods arrays containing only TRUE payment methods
      foreach ($values['payment_methods'] as $key => $value) {
        if ($value) {
          if (StringOperations::endsWith($key, 'recurring')) {
            $values['payment_methods_recurring'][] = $key;
          }
          else {
            $values['payment_methods'][] = $key;
          }
        }
        unset($values['payment_methods'][$key]);
      }

      // Transform donation rhythm array to contain only TRUE elements
      foreach ($values['donation_rhythm'] as $key => $value) {
        if ($value) {
          $values['donation_rhythm'][] = $key;
        }
        unset($values['donation_rhythm'][$key]);
      }
    }
    elseif ($direction == self::OUT) {

      $projectOptionsKeys = Cache::getInstance()
        ->getTemplates()['TwingleProject']['project_options'];
      $projectEmbedDataKeys = Cache::getInstance()
        ->getTemplates()['TwingleProject']['project_embed_data'];

      // Merge payment_methods and payment_methods_recurring arrays and change
      // keys to values and values to TRUE
      if (isset($values['payment_methods'])) {
        foreach ($values['payment_methods'] as $key => $value) {
          unset($values['payment_methods'][$key]);
          $values['payment_methods'][$value] = TRUE;
        }
      }
      if (isset($values['payment_methods_recurring'])) {
        foreach ($values['payment_methods_recurring'] as $value) {
          $values['payment_methods'][$value] = TRUE;
        }
        unset($values['payment_methods_recurring']);
      }

      // Move options, embed data and payment methods into own arrays
      foreach ($values as $key => $value) {
        if (in_array($key, $projectOptionsKeys)) {
          $values['project_options'][$key]
            = $value;
          unset($values[$key]);
        }
        elseif (in_array($key, $projectEmbedDataKeys)) {
          $values['embed_data'][$key]
            = $value;
          unset($values[$key]);
        }
      }

      // Change DateTime string into timestamp
      $values['last_update'] =
        self::getTimestamp($values['last_update']);

      // Default project_type to ''
      $values['type'] = $values['type'] == 'default'
        ? ''
        : $values['type'];

      // Cast project target to integer
      if (isset($values['project_target'])) {
        $values['project_target'] = (int) $values['project_target'];
      }

      // Set default for 'allow_more'
      $values['allow_more'] = !empty($values['allow_more']);

      // Invert and concatenate contact fields
      if (isset($values['project_options']['contact_fields'])) {
        // Invert contact_fields to exclude_contact_fields
        $values['project_options']['exclude_contact_fields'] =
          array_diff(
            self::contactFields,
            $values['project_options']['contact_fields']
          );
        unset($values['project_options']['contact_fields']);
        // Concatenate contact_fields array
        $values['project_options']['exclude_contact_fields'] =
          implode(
            ',',
            $values['project_options']['exclude_contact_fields']
          );
      }

      // Concatenate mandatory project contact fields
      if (isset($values['project_options']['mandatory_contact_fields'])) {
        $values['project_options']['mandatory_contact_fields'] =
          implode(
            ',',
            $values['project_options']['mandatory_contact_fields']
          );
      }

      // Concatenate project languages
      if (isset($values['project_options']['languages'])) {
        $values['project_options']['languages'] =
          implode(',', $values['project_options']['languages']);
      }

      // Build donation_rhythm array
      if (isset($values['project_options']['donation_rhythm'])) {
        $tmp_array = [];
        foreach (self::donationRhythm as $donationRhythm) {
          $tmp_array[$donationRhythm] =
            in_array(
              $donationRhythm,
              $values['project_options']['donation_rhythm']
            );
        }
        $values['project_options']['donation_rhythm'] = $tmp_array;
      }

      // Build payment_methods_array
      if (isset($values['payment_methods'])) {
        $payment_methods = array_fill_keys(Cache::getInstance()
          ->getTemplates()['TwingleProject']['payment_methods'],
          FALSE);
        $values['payment_methods'] =
          array_merge($payment_methods, $values['payment_methods']);
      }

      // Build buttons array
      for ($i = 1; $i <= 4; $i++) {
        if (isset($values['button' . $i])) {
          $values['project_options']['buttons']['button' . $i] =
            ['amount' => $values['button' . $i]];
          unset($values['button' . $i]);
        }
      }
    }
    else {
      throw new Exception(
        "Invalid Parameter $direction for formatValues()"
      );
    }
  }


  /**
   * ## Get a response
   * Get a response that describes the status of this TwingleProject instance
   * Returns an array that contains **title**, **id**, **project_id** and
   * **status** (if provided)
   *
   * @param string|null $status
   * status of the TwingleProject you want to give back along with the response
   *
   * @return array
   *
   */
  public
  function getResponse(string $status = NULL): array {
    $project_type = empty($this->values['type']) ? 'default' : $this->values['type'];
    $response =
      [
        'title'        => $this->values['name'],
        'id'           => (int) $this->id,
        'project_id'   => (int) $this->values['id'],
        'project_type' => $project_type,
      ];
    if ($status) {
      $response['status'] = $status;
    }
    return $response;
  }

  /**
   * ## Last update
   * Returns a timestamp of the last update of the TwingleProject campaign.
   *
   * @return int|null
   */
  public function lastUpdate(): ?int {
    return self::getTimestamp($this->values['last_update']);
  }

  /**
   * ## Get project id
   * Returns the **project_id** of this TwingleProject.
   *
   * @return int
   */
  public
  function getProjectId(): int {
    return (int) $this->values['id'];
  }

  /**
   * ## Get the payment methods array of this project
   *
   * @return array
   */
  public function getValues(): array {
    if (isset($this->values)) {
      return $this->values;
    }
    else {
      return [];
    }
  }

  /**
   * ## Get the project options array of this project
   *
   * @return array
   */
  public function getOptions(): array {
    if (isset($this->values['project_options'])) {
      return $this->values['project_options'];
    }
    else {
      return [];
    }
  }

  /**
   * ## Get the payment methods array of this project
   *
   * @return array
   */
  public function getPaymentMethods(): array {
    if (isset($this->values['payment_methods'])) {
      return $this->values['payment_methods'];
    }
    else {
      return [];
    }
  }

  /**
   * ## Get the payment methods array of this project
   */
  public function deleteValues(): void {
    unset ($this->values);
  }

  /**
   * ## Get the project options array of this project
   */
  public function deleteOptions(): void {
    unset($this->values['project_options']);
  }

  /**
   * ## Get the payment methods array of this project
   */
  public function deletePaymentMethods(): void {
    unset($this->values['payment_methods']);
  }

}
