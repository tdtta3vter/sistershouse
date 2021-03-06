<?php
/*
 * Copyright (C) 2017-present, Facebook, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Integration;

defined('ABSPATH') or die('Direct access not allowed');

use FacebookPixelPlugin\Core\FacebookPixel;
use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\FacebookWordPressOptions;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;

class FacebookWordpressWPForms extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'wpforms-lite/wpforms.php';
  const TRACKING_NAME = 'wpforms-lite';

  public static function injectPixelCode() {
    add_action(
      'wpforms_process_before',
      array(__CLASS__, 'trackEvent'),
      20,
      2
    );
  }

  public static function trackEvent($entry, $form_data) {
    if (FacebookPluginUtils::isAdmin()) {
      return;
    }

    $server_event = ServerEventFactory::safeCreateEvent(
      'Lead',
      array(__CLASS__, 'readFormData'),
      array($entry, $form_data)
    );
    FacebookServerSideEvent::getInstance()->track($server_event);

    add_action(
      'wp_footer',
       array(__CLASS__, 'injectLeadEvent'),
       20
    );
  }

  public static function injectLeadEvent() {
    if (FacebookPluginUtils::isAdmin()) {
      return;
    }

    $events = FacebookServerSideEvent::getInstance()->getTrackedEvents();
    $pixel_code = PixelRenderer::render($events, self::TRACKING_NAME);

    printf("
<!-- Facebook Pixel Event Code -->
%s
<!-- End Facebook Pixel Event Code -->
      ",
      $pixel_code);
  }

  public static function readFormData($entry, $form_data) {
    if (empty($entry) || empty($form_data)) {
      return array();
    }

    return array(
      'email' => self::getEmail($entry, $form_data),
      'first_name' => self::getFirstName($entry, $form_data),
      'last_name' => self::getLastName($entry, $form_data)
    );
  }

  private static function getEmail($entry, $form_data) {
    return self::getField($entry, $form_data, 'email');
  }

  private static function getFirstName($entry, $form_data) {
    return self::getField($entry, $form_data, 'name', 'first');
  }

  private static function getLastName($entry, $form_data) {
    return self::getField($entry, $form_data, 'name', 'last');
  }

  private static function getField($entry, $form_data, $type, $label = null) {
    if (empty($form_data['fields'])) {
      return null;
    }

    foreach ($form_data['fields'] as $field) {
      if ($field['type'] == $type) {
        if (!empty($entry['fields'])) {
          if (!empty($label)) {
            return $entry['fields'][$field['id']][$label];
          } else {
            return $entry['fields'][$field['id']];
          }
        }
      }
    }

    return null;
  }
}
