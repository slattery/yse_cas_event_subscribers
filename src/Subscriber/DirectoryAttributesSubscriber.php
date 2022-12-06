<?php

namespace Drupal\yse_cas_event_subscribers\Subscriber;

use Drupal\cas\CasPropertyBag;
use Drupal\cas\Event\CasPostLoginEvent;
use Drupal\cas\Event\CasPreLoginEvent;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas_attributes\Form\CasAttributesSettings;
use Drupal\Component\Serialization\Json;
use Drupal\cas\Service\CasHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
//use Drupal\yse_cas_event_subscribers\Service\CasBaggagehandler;

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
  protected $cassvcsettings;
  protected $usrdirsettings;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory to get module settings.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->cassvcsettings = $config_factory->get('cas.settings');
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
    $events[CasHelper::EVENT_PRE_REGISTER][] = ['onPreRegister', 1];
    $events[CasHelper::EVENT_PRE_LOGIN][] = ['onPreLogin', 21];
    return $events;
  }

  /**
   * Subscribe to the CasPreRegisterEvent.
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The CasPreAuthEvent containing property information.
   */
  public function onPreRegister(CasPreRegisterEvent $event) {
    $cas_bag_handler        = \Drupal::service('yse.cas_baggagehandler');

    if ($this->casattsettings->get('field.sync_frequency') !== CasAttributesSettings::SYNC_FREQUENCY_NEVER) {
      // Perform lookup and setAttributes in bag
      // next Subscriber will deal with fields and roles
      $email_hostname = $this->cassvcsettings->get('user_accounts.email_hostname');
      $cas_username   = $event->getCasPropertyBag()->getOriginalUsername();
      $event->getCasPropertyBag()->setAttribute('uid', $cas_username);
      $record = $cas_bag_handler->getDirectoryData($cas_username);
      $atts = array_merge($event->getCasPropertyBag()->getAttributes(), $record);
      $event->getCasPropertyBag()->setAttributes($atts);
      $cas_friendly_name = $event->getCasPropertyBag()->getAttribute('name');
      if ($cas_friendly_name){
        $event->setDrupalUsername($cas_friendly_name);
      }
    }
  }

  /**
   * Subscribe to the CasPreLoginEvent.
   *
   * @param \Drupal\cas\Event\CasPreLoginEvent $event
   *   The CasPreAuthEvent containing account and property information.
   */
  public function onPreLogin(CasPreLoginEvent $event) {
    $cas_bag_handler        = \Drupal::service('yse.cas_baggagehandler');
    $account = $event->getAccount();
    $email_hostname = $this->cassvcsettings->get('user_accounts.email_hostname');
    $cas_username   = $event->getCasPropertyBag()->getOriginalUsername();
    // Map fields.
    if ($this->casattsettings->get('field.sync_frequency') === CasAttributesSettings::SYNC_FREQUENCY_EVERY_LOGIN) {
      // Perform lookup and setAttributes in bag
      // next Subscriber will deal with fields and roles
      $event->getCasPropertyBag()->setAttribute('mail', $cas_username . '@' . $email_hostname);
      $data = $cas_bag_handler->retrieveDirectoryRecord($cas_username);
      $atts = $cas_bag_handler->gatherAttributes($data);
      $atts = array_merge($event->getCasPropertyBag()->getAttributes(), $atts);
      $event->getCasPropertyBag()->setAttributes($atts);
    }
  }


  /**
  * onPostLogin is not needed, as the bag should be filled already
  */

}
