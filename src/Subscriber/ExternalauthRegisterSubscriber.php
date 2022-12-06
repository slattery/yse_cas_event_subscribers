<?php

namespace Drupal\yse_cas_event_subscribers\Subscriber;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\externalauth\Event\ExternalAuthEvents;
use Drupal\externalauth\Event\ExternalAuthRegisterEvent;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * Provides a ExternalauthRegisterSubscriber.
 */
class ExternalauthRegisterSubscriber implements EventSubscriberInterface {

  /**
   * Settings object for CAS attributes.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $usrdirsettings;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory to get module settings.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->usrdirsettings = $config_factory->get('yse_cas_event_subscribers.settings');
  }

  /**
   *  Listen for ExternalAuth registrations.
   */
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ExternalAuthEvents::REGISTER][] = ['onExtRegister', -1];
    return $events;
  }

  /**
   * Subscribe to the ExternalAuthRegisterEvent.
   *
   * @param Drupal\externalauth\Event\ExternalAuthRegisterEvent $event
   *   The CasPreAuthEvent containing property information.
   */
  public function onExtRegister(ExternalAuthRegisterEvent $event) {
    
    $make_profile = $this->usrdirsettings->get('make_profile');

    if( $make_profile ){
     $new_user = $event->getAccount();
     $new_data = $event->getData();
    
     dvm($new_user);
     dvm($new_data);
    }

  }
}
