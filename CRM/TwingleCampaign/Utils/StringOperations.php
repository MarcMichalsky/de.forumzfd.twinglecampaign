<?php



class CRM_TwingleCampaign_Utils_StringOperations {

  /**
   * @param string $title
   *
   * @return string
   */
  public static function title_to_name(string $title) {
    $umlauts = ['/ä/', '/ö/', '/ü/'];
    $replace_umplauts = ['ae', 'oe', 'ue'];
    $title = preg_replace($umlauts, $replace_umplauts, $title);
    $title = strtolower(preg_replace('/[^A-Za-z0-9-_]/', '_', $title));
    return $title;
  }

  /**
   * Splits a single string that contains firstname and lastname into an
   * associative array with firstname and lastname
   *
   * TODO: This solution is intended to be temporary and should become included
   * (in a much more advanced way) into the XCM Extension
   *
   * @param string $string
   * @return array|string
   */
  public static function split_names(string $string) {
    $names = explode(' ', $string);

    if (is_array($names) && count($names) > 1) {
      $lastname = array_pop($names);
      $test = $names[count($names) - 1];
      $lastnamePrefixes = ['da', 'de', 'der', 'van', 'von'];
      if (in_array($test, $lastnamePrefixes)) {
        if ($test == 'der' &&
          $names[count($names) - 2] == 'van' ||
          $names[count($names) - 2] == 'von'
        ) {
          $lastname = implode(' ', array_splice($names, -2))
            . ' ' . $lastname;
        }
        else {
          array_pop($names);
          $lastname = $test . ' ' . $lastname;
        }
      }
      $firstnames = implode(" ", $names);
      return ['firstnames' => $firstnames, 'lastname' => $lastname];
    }
    return $string;
  }
}