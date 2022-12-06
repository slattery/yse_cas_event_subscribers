<?php

namespace Drupal\yse_cas_event_subscribers\Form;


use Drupal\cas\CasPropertyBag;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas\Exception\CasLoginException;
use Drupal\cas\Form\BulkAddCasUsers;
use Drupal\cas\Service\CasHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
//use Drupal\yse_cas_event_subscribers\Service\CasBaggagehandler;

/**
 * Class BulkAddCasUsers.
 *
 * A form for bulk registering CAS users.
 */
class BulkLookupAddCasUsers extends FormBase {


  /**
   * The CAS Helper.
   *
   * @var \Drupal\cas\Service\CasHelper
   */
  protected $casHelper;

  /**
   * Used to dispatch CAS login events.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bulk_lookup_add_cas_users';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}


/**
 * {@inheritdoc}
 */
public function parseLines(array &$form, FormStateInterface $form_state) {
    $roles = array_filter($form_state->getValue('roles'));
    unset($roles[RoleInterface::AUTHENTICATED_ID]);
    $roles = array_keys($roles);

    $cas_usernames = trim($form_state->getValue('cas_usernames'));
    $cas_usernames = preg_split('/[\n\r|\r|\n]+/', $cas_usernames);

    $email_hostname = trim($form_state->getValue('email_hostname'));

    $operations = [];
    foreach ($cas_usernames as $cas_username) {
      $cas_username = trim($cas_username);
      if (!empty($cas_username)) {
        $operations[] = [
          '\Drupal\yse_cas_event_subscribers\Form\BulkLookupAddCasUsers::userLookupAdd',
          [$cas_username, $roles, $email_hostname],
        ];
      }
    }

    $batch = [
      'title' => t('Creating YSE CAS users...'),
      'operations' => $operations,
      'finished' => '\Drupal\yse_cas_event_subscribers\Form\BulkLookupAddCasUsers::userLookupAddFinished',
      'progress_message' => t('Processed @current out of @total.'),
    ];

    batch_set($batch);
  }

  /**
   * Perform a single CAS user creation batch operation.
   *
   * Callback for batch_set().
   *
   * @param string $cas_username
   *   The CAS username, which will also become the Drupal username.
   * @param array $roles
   *   An array of roles to assign to the user.
   * @param string $email_hostname
   *   The hostname to combine with the username to create the email address.
   * @param array $context
   *   The batch context array, passed by reference.
   */
  public static function userLookupAdd($cas_username, array $roles, $email_hostname, array &$context) {
    $evt_dispatcher   = new EventDispatcher();
    $cas_user_manager = \Drupal::service('cas.user_manager');
    $cas_bag_handler  = \Drupal::service('yse.cas_baggagehandler');

    // Back out of an account already has this CAS username.
    $existing_uid = $cas_user_manager->getUidForCasUsername($cas_username);
    if ($existing_uid) {
      $context['results']['messages']['already_exists'][] = $cas_username;
      return;
    }
    
    //Create event with baggage for various transforms
    $cas_property_bag = new CasPropertyBag($cas_username);
    $cas_init_address = $cas_username . '@' . $email_hostname;
    $cas_property_bag->setAttributes(['roles' => $roles, 'mail' => $cas_init_address ]);
    $cas_pre_register_event = $cas_bag_handler->dispatchCasPreregisterEvent($cas_property_bag);
 
    //let CAS Attributes module do the mapping/populating
   
    try {
      /** @var \Drupal\user\UserInterface $user */
      $user = $cas_user_manager->register($cas_property_bag->getOriginalUsername(), $cas_pre_register_event->getDrupalUsername(), $cas_pre_register_event->getPropertyValues());
      $context['results']['messages']['created'][] = $user->toLink()->toString();
    }
    catch (CasLoginException $e) {
      \Drupal::logger('cas')->error('CasLoginException when registering user with name %name: %e', [
        '%name' => $cas_username,
        '%e' => $e->getMessage(),
      ]);
      $context['results']['messages']['errors'][] = $cas_username;
      return;
    }
  }

  /**
   * Complete CAS user creation batch process.
   *
   * Callback for batch_set().
   *
   * Consolidates message output.
   */
  public static function userLookupAddFinished($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      if (!empty($results['messages']['errors'])) {
        $messenger->addError(t('An error was encountered creating accounts for the following users (check logs for more details): %usernames', [
          '%usernames' => implode(', ', $results['messages']['errors']),
        ]));
      }
      if (!empty($results['messages']['already_exists'])) {
        $messenger->addError(t('The following accounts were not registered because existing accounts are already using the usernames: %usernames', [
          '%usernames' => implode(', ', $results['messages']['already_exists']),
        ]));
      }
      if (!empty($results['messages']['created'])) {
        $userLinks = Markup::create(implode(', ', $results['messages']['created']));
        $messenger->addStatus(t('Successfully created accounts for the following usernames: %usernames', [
          '%usernames' => $userLinks,
        ]));
      }
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $messenger->addError(t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]));
    }
  }

}

