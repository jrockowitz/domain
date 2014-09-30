<?php

/**
 * @file
 * Definition of Drupal\domain\DomainValidator.
 */

namespace Drupal\domain;

use Drupal\domain\DomainValidatorInterface;
use Drupal\domain\DomainInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

class DomainValidator implements DomainValidatorInterface {

  /**
   * Validates the hostname for a domain.
   * // move to manager?
   */
  public function validate(DomainInterface $domain) {
    $hostname = $domain->hostname;
    $error_list = array();
    // Check for at least one dot or the use of 'localhost'.
    // Note that localhost can specify a port.
    $localhost_check = explode(':', $hostname);
    if (substr_count($hostname, '.') == 0 && $localhost_check[0] != 'localhost') {
      $error_list[] = t('At least one dot (.) is required, except when using <em>localhost</em>.');
    }
    // Check for one colon only.
    if (substr_count($hostname, ':') > 1) {
      $error_list[] = t('Only one colon (:) is allowed.');
    }
    // If a colon, make sure it is only followed by numbers.
    elseif (substr_count($hostname, ':') == 1) {
      $parts = explode(':', $hostname);
      $port = (int) $parts[1];
      if (strcmp($port, $parts[1])) {
        $error_list[] = t('The port protocol must be an integer.');
      }
    }
    // The domain cannot begin or end with a period.
    if (substr($hostname, 0, 1) == '.') {
      $error_list[] = t('The domain must not begin with a dot (.)');
    }
    // The domain cannot begin or end with a period.
    if (substr($hostname, -1) == '.') {
      $error_list[] = t('The domain must not end with a dot (.)');
    }
    // Check for valid characters, unless using non-ASCII domains.
    $non_ascii = \Drupal::config('domain.settings')->get('allow_non_ascii');
    if (!$non_ascii) {
      $pattern = '/^[a-z0-9\.\-:]*$/i';
      if (!preg_match($pattern, $hostname)) {
        $error_list[] = t('Only alphanumeric characters, dashes, and a colon are allowed.');
      }
    }
    // Check for lower case.
    if ($hostname != drupal_strtolower($hostname)) {
      $error_list[] = t('Only lower-case characters are allowed.');
    }
    // Check for 'www' prefix if redirection / handling is enabled under global domain settings.
    // Note that www prefix handling must be set explicitly in the UI.
    // See http://drupal.org/node/1529316 and http://drupal.org/node/1783042
    if (\Drupal::config('domain.settings')->get('www_prefix') && (substr($hostname, 0, strpos($hostname, '.')) == 'www')) {
      $error_list[] = t('WWW prefix handling: Domains must be registered without the www. prefix.');
    }

    // Check existing domains.
    $domains = entity_load_multiple_by_properties('domain', array('hostname' => $hostname));
    foreach ($domains as $domain) {
      if ($domain->id() != $domain->id()) {
        $error_list[] = t('The hostname is already registered.');
      }
    }
    // Allow modules to alter this behavior.
    \Drupal::moduleHandler()->invokeAll('domain_validate', $error_list, $hostname);

    // Return the errors, if any.
    if (!empty($error_list)) {
      return t('The domain string is invalid for %subdomain: !errors', array('%subdomain' => $hostname, '!errors' => array('#theme' => 'item_list', '#items' => $error_list)));
    }

    return array();
  }

  /**
   * Tests that a domain responds correctly.
   *
   * This is a server-level configuration test. The requested image should be
   * returned properly.
   */
  public function checkResponse(DomainInterface $domain) {
    $url = $domain->getPath() . drupal_get_path('module', 'domain') . '/tests/200.png';
    try {
      // GuzzleHttp no longer allows for bogus URL calls.
      $request = $domain->getHttpClient()->get($url);
    }
    // We cannot know which Guzzle Exception class will be returned; be generic.
    catch (RequestException $e) {
      watchdog_exception('domain', $e);
      // File a general server failure.
      $domain->response = 500;
      return;
    }
    // Expected result (i.e. no exception thrown.)
    $domain->setResponse($request->getStatusCode());
  }


}
