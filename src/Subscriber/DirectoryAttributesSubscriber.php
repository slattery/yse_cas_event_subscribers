<?php

namespace Drupal\yse_cas_event_subscribers\Subscriber;

use Drupal\cas\Event\CasPostLoginEvent;
use Drupal\cas\Event\CasPreLoginEvent;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas_attributes\Form\CasAttributesSettings;
use Drupal\Component\Serialization\Json;
use Drupal\cas\Service\CasHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a CasAttributesSubscriber.
 */
class DirectoryAttributesSubscriber implements EventSubscriberInterface {

  /**
   * Settings object for CAS attributes.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $casattsettings;
  protected $usrdirsettings;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory to get module settings.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->casattsettings = $config_factory->get('cas_attributes.settings');
    $this->usrdirsettings = $config_factory->get('yse_cas_event_subscribers.settings');
  }

  /**
   *  Set priorities to populate bag before normal CAS Attribute process.
   */
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[CasHelper::EVENT_PRE_REGISTER][] = ['onPreRegister', -2];
    $events[CasHelper::EVENT_PRE_LOGIN][] = ['onPreLogin', 19];
    $events[CasHelper::EVENT_POST_LOGIN][] = ['onPostLogin', -2];
    return $events;
  }

  /**
   * Subscribe to the CasPreRegisterEvent.
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The CasPreAuthEvent containing property information.
   */
  public function onPreRegister(CasPreRegisterEvent $event) {
    if ($this->casattsettings->get('field.sync_frequency') !== CasAttributesSettings::SYNC_FREQUENCY_NEVER) {
      // Perform lookup and setAttributes in bag
      // next Subscriber will deal with fields and roles
      $data = $this->retrieveDirectoryRecord($event->getCasPropertyBag()->getOriginalUsername());
      $atts = $this->gatherAttributes($data);
      $atts = array_merge($event->getCasPropertyBag()->getAttributes(), $atts);
      $event->getCasPropertyBag()->setAttributes($atts);
    }
  }

  /**
   * Subscribe to the CasPreLoginEvent.
   *
   * @param \Drupal\cas\Event\CasPreLoginEvent $event
   *   The CasPreAuthEvent containing account and property information.
   */
  public function onPreLogin(CasPreLoginEvent $event) {
    $account = $event->getAccount();
    // Map fields.
    if ($this->casattsettings->get('field.sync_frequency') === CasAttributesSettings::SYNC_FREQUENCY_EVERY_LOGIN) {
      // Perform lookup and setAttributes in bag
      // next Subscriber will deal with fields and roles
      $data = $this->retrieveDirectoryRecord($event->getCasPropertyBag()->getOriginalUsername());
      $atts = $this->gatherAttributes($data);
      $atts = array_merge($event->getCasPropertyBag()->getAttributes(), $atts);
      $event->getCasPropertyBag()->setAttributes($atts);
    }
  }


  /**
   * THIS MAY NOT BE NECESSARY IF BAG IS POPULATED PRELOGIN
   * Save attributes to user session if sitewide token support is enabled.
   *
   * @param \Drupal\cas\Event\CasPostLoginEvent $casPostLoginEvent
   *   The post login event from CAS.
   *
   *  public function onPostLogin(CasPostLoginEvent $casPostLoginEvent) {
   *     if ($this->casattsettings->get('sitewide_token_support')) {
   *         // Perform Lookup.
   *         $key_id = $event->getCasPropertyBag()->getOriginalUsername();
   *         // query and setAttributes in bag
   *         // next Subscriber will deal with fields and roles
   *     }
   *   }
   */

/**
   * gatherAttributes
   * Perform Directory Lookup and setAttributes in the bag
   *
   * @param string $lookupId
   *   The attribute value to compare against.
   */
  protected function gatherAttributes(array $data) {
      
      if (is_object($data)) {
        $service_response = $data->ServiceResponse;
      }
      else {
        $type = gettype($data);
        \Drupal::logger('yse_cas_eventsub')->notice('json_decode failed with data of type %type for user %netid', ['%type' => $type, '%netid' => $lookupId]);
      }

      if (is_object($service_response)) {
        $rec = $service_response->Record;
      }
      else {
        \Drupal::logger('yse_cas_eventsub')->notice('Web service response is missing "Record" element for user %netid', ['%netid' => $lookupId]);
      }

      if (isset($rec->FirstName)) {
        $user_data['firstname'] = $rec->FirstName;
      }
      if (isset($rec->LastName)) {
        $user_data['lastname'] = $rec->LastName;
      }
      if (isset($rec->FirstName) && isset($rec->LastName)) {
        $user_data['name'] = $rec->FirstName . ' ' . $rec->LastName;
      }
      if (isset($rec->EmailAddress)) {
        $user_data['email'] = $rec->EmailAddress;
      }
      if (isset($rec->WorkPhone)) {
        $user_data['phone'] = $rec->WorkPhone;
      }
      if (isset($rec->DirectoryTitle)) {
        $user_data['title'] = $rec->DirectoryTitle;
      }
      if (isset($rec->DepartmentName)) {
        $user_data['department'] = $rec->DepartmentName;
      }
      elseif (isset($rec->PrimaryDepartmentName)) {
        $user_data['department'] = $rec->PrimaryDepartmentName;
      }
      if (isset($rec->PlanningUnitName)) {
        $user_data['division'] = $rec->PlanningUnitName;
      }
      elseif (isset($rec->PrimaryDivisionName)) {
        $user_data['division'] = $rec->PrimaryDivisionName;
      }
      if (isset($rec->PrimaryAffiliation)) {
        $user_data['primary_affiliation'] = $rec->PrimaryAffiliation;
      }
      if (isset($rec->Upi)) {
        $user_data['upi'] = $rec->Upi;
      }
      // set up these atts for Role Mapping
      if ($rec->hasEmployeeRole == 'Y') {
        $user_data['hasEmployeeRole'] = 'Y';
      }
      if ($rec->hasStudentRole == 'Y') {
        $user_data['hasStudentRole'] = 'Y';
      }
      if ($rec->hasFacultyRole == 'Y') {
        $user_data['hasFacultyRole'] = 'Y';
      }
      if ($rec->hasStaffRole == 'Y') {
        $user_data['hasStaffRole'] = 'Y';
      }
      if ($rec->hasAlumnusRole == 'Y') {
        $user_data['hasAlumnusRole'] = 'Y';
      }
      if ($rec->hasMemberRole == 'Y') {
        $user_data['hasMemberRole'] = 'Y';
      }
      if ($rec->hasAffiliateRole == 'Y') {
        $user_data['hasAffiliateRole'] = 'Y';
      }

      return $user_data;

  }

  protected function retrieveDirectoryRecord(string $lookupId) {

    $secrets = $this->_getSecrets();
    $json_obj = [];
    $gateway = $this->usrdirsettings->get('gateway_url');

    if (!empty($secrets['l7_user']) && !empty($secrets['l7_pass'])) {
      //$request = drupal_http_request("https://${secrets['l7_user']}:${secrets['l7_pass']}@$gateway?outputformat=json&netid=${netid}");
      try {
        $response = \Drupal::httpClient()->get($gateway, [
          'auth'    => [ $secrets['l7_user'], $secrets['l7_pass'] ],
          'query'   => ['outputformat' => 'json', 'netid' => $lookupId ],
          'headers' => ['Accept'       => 'application/json' ],
        ]);
        $json_obj = Json::decode((string) $response->getBody());
      } catch (ClientException $e) {
          \Drupal::logger('yse_cas_eventsub')->notice(Psr7\Message::toString($e->getResponse()));
      }

    }
    return $json_obj;
  }

  /**
   * _getSecrets
   * read a secrets file return data structure
   */
  protected function _getSecrets() {
    $secrets_path = $this->usrdirsettings->get('secrets_path');
    $secrets_file = $_SERVER['HOME'] . $secrets_path;
  
    if (!file_exists($secrets_file)) {
      //drupal_set_message('No secrets file ['. $secrets_file .'] found.','status');
    }
    else {
      $secrets_contents = file_get_contents($secrets_file);
      $secrets = json_decode($secrets_contents, 1);
  
      if ($secrets == FALSE) {
        //drupal_set_message('Could not parse JSON in loaded file.','status');
      }
      else {
        return $secrets;
      }
    }
  }
}
