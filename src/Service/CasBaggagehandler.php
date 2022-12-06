<?php

namespace Drupal\yse_cas_event_subscribers\Service;

use Drupal\cas\CasPropertyBag;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas_attributes\Form\CasAttributesSettings;
use Drupal\Component\Serialization\Json;
use Drupal\cas\Service\CasHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a CasAttributesSubscriber.
 */
class CasBaggagehandler {

   /**
   * The CAS Helper.
   *
   * @var \Drupal\cas\Service\CasHelper
   */
  protected $casHelper;

  /**
   * Used to dispatch CAS login events.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;


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
  public function __construct(ConfigFactoryInterface $config_factory, EventDispatcherInterface $event_dispatcher, CasHelper $cas_helper) {
    $this->casattsettings = $config_factory->get('cas_attributes.settings');
    $this->usrdirsettings = $config_factory->get('yse_cas_event_subscribers.settings');
    $this->eventDispatcher = $event_dispatcher;
    $this->casHelper = $cas_helper;
  }

  /**
   * Fill to the CasPropertyBag.
   *
   * @param \Drupal\cas\CasPropertyBag $property_bag
   *   The CasPreAuthEvent containing property information.
   */
  public function fillCasPropertyBag($property_bag) {
    // Perform lookup and setAttributes in bag
    // next Subscriber will deal with fields and roles
    $atts = $this->getDirectoryData($property_bag->getOriginalUsername());
    $property_bag->setAttributes(array_merge($property_bag->getAttributes(), $atts));
    // now you have the atts, you need to map using event and bag and dispatch
    return $property_bag;
  }

  public function getDirectoryData($key){
    $data = $this->retrieveDirectoryRecord($key);
    $atts = $this->gatherAttributes($data);
    return $atts;
  }

  public function dispatchCasPreregisterEvent($property_bag){
    $cas_pre_register_event = new CasPreRegisterEvent($property_bag);
    \Drupal::logger('yse_cas_eventsub')->notice('Dispatching EVENT_PRE_REGISTER.');
    $this->eventDispatcher->dispatch($cas_pre_register_event, CasHelper::EVENT_PRE_REGISTER);
    return $cas_pre_register_event;
  }
 
/**
   * gatherAttributes
   * Perform Directory Lookup and setAttributes in the bag
   *
   * @param array $data
   *   The attribute value to compare against.
   */
  protected function gatherAttributes(array $rec) {
      
    if (is_array($rec)) {
       
      $user_data = [];

      if (isset($rec['FirstName'])) {
        $user_data['firstname'] = $rec['FirstName'];
      }
      if (isset($rec['LastName'])) {
        $user_data['lastname'] = $rec['LastName'];
      }
      if (isset($rec['FirstName']) && isset($rec['LastName'])) {
        $user_data['name'] = $rec['FirstName'] . ' ' . $rec['LastName'];
      }
      if (isset($rec['EmailAddress'])) {
        $user_data['email'] = $rec['EmailAddress'];
      }
      if (isset($rec['WorkPhone'])) {
        $user_data['phone'] = $rec['WorkPhone'];
      }
      if (isset($rec['DirectoryTitle'])) {
        $user_data['title'] = $rec['DirectoryTitle'];
      }
      if (isset($rec['DepartmentName'])) {
        $user_data['department'] = $rec['DepartmentName'];
      }
      elseif (isset($rec['PrimaryDepartmentName'])) {
        $user_data['department'] = $rec['PrimaryDepartmentName'];
      }
      if (isset($rec['PlanningUnitName'])) {
        $user_data['division'] = $rec['PlanningUnitName'];
      }
      elseif (isset($rec['PrimaryDivisionName'])) {
        $user_data['division'] = $rec['PrimaryDivisionName'];
      }
      if (isset($rec['PrimaryAffiliation'])) {
        $user_data['primary_affiliation'] = $rec['PrimaryAffiliation'];
      }
      if (isset($rec['Upi'])) {
        $user_data['upi'] = $rec['Upi'];
      }
      // set up these atts for Role Mapping
      if ($rec['hasEmployeeRole'] == 'Y') {
        $user_data['hasEmployeeRole'] = 'Y';
      }
      if ($rec['hasStudentRole'] == 'Y') {
        $user_data['hasStudentRole'] = 'Y';
      }
      if ($rec['hasFacultyRole'] == 'Y') {
        $user_data['hasFacultyRole'] = 'Y';
      }
      if ($rec['hasStaffRole'] == 'Y') {
        $user_data['hasStaffRole'] = 'Y';
      }
      if ($rec['hasAlumnusRole'] == 'Y') {
        $user_data['hasAlumnusRole'] = 'Y';
      }
      if ($rec['hasMemberRole'] == 'Y') {
        $user_data['hasMemberRole'] = 'Y';
      }
      if ($rec['hasAffiliateRole'] == 'Y') {
        $user_data['hasAffiliateRole'] = 'Y';
      }

      return $user_data;
    }
    else {
      \Drupal::logger('yse_cas_eventsub')->notice('Web service response is missing "Record" element for user %netid', ['%netid' => $lookupId]);
    }

  }

  protected function retrieveDirectoryRecord(string $lookupId) {

    $secrets = $this->_getSecrets();
    $gateway = $this->usrdirsettings->get('gateway_url');

    if (!empty($secrets['l7_user']) && !empty($secrets['l7_pass'])) {
      //$request = drupal_http_request("https://${secrets['l7_user']}:${secrets['l7_pass']}@$gateway?outputformat=json&netid=${netid}");
      try {
        $response = \Drupal::httpClient()->get($gateway, [
          'auth'    => [ $secrets['l7_user'], $secrets['l7_pass'] ],
          'query'   => ['outputformat' => 'json', 'netid' => $lookupId ],
          'headers' => ['Accept'       => 'application/json' ],
        ]);
        $json_arr = Json::decode($response->getBody(), TRUE);

        if (is_array($json_arr)) {
          $service_response = $json_arr['ServiceResponse'];
        }
        else {
          $type = gettype($json_arr);
          \Drupal::logger('yse_cas_eventsub')->notice('json_decode failed with data of type %type for user %netid', ['%type' => $type, '%netid' => $lookupId]);
        }
        if (is_array($service_response)) {
          $rec = $service_response['Record'];
          return $rec;
        }
        else {
          $type = gettype($service_response);
          \Drupal::logger('yse_cas_eventsub')->notice('json_decode failed with data of type %type for user %netid', ['%type' => $type, '%netid' => $lookupId]);
        }

      } catch (ClientException $e) {
          \Drupal::logger('yse_cas_eventsub')->notice(Psr7\Message::toString($e->getResponse()));
      }

    }
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
