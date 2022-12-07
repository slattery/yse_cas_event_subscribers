<?php

/**
 * @file
 * Simple config form.
 */

namespace Drupal\yse_cas_event_subscribers\Form;


use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


class YseCasEventSubscribersForm extends ConfigFormBase {


  /**
   * Constructs an YseCasEventSubscribersForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'yse_cas_event_subscribers';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['yse_cas_event_subscribers.settings'];
  }


 /**
   * {@inheritdoc}
   *  Should show a note here and link about role mapping over at CAS Attributes for bulk form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    
    $gateway = $this->configFactory->get('yse_cas_event_subscribers.settings')->get('gateway_url');
    $secrets = $this->configFactory->get('yse_cas_event_subscribers.settings')->get('secrets_path');
    $profile = $this->configFactory->get('yse_cas_event_subscribers.settings')->get('make_profile');

    $form['gateway_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gateway URL for lookups'),
      '#default_value' => $gateway,
      '#min' => 1,
      '#required' => TRUE,
      '#description' => $this->t('The gateway URL for YSE user data lookups.'),
    ];

    $form['secrets_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filesystem location of secrets file'),
      '#default_value' => $secrets,
      '#min' => 1,
      '#required' => TRUE,
      '#description' => $this->t('The filesystem location of secrets file. The SERVER HOME env setting is prepended to this path.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('yse_cas_event_subscribers.settings');
    $config->set('gateway_url', $form_state->getValue('gateway_url'))->save();
    $config->set('secrets_path', $form_state->getValue('secrets_path'))->save();
    //\Drupal::logger('yse_cas_event_subscribers')->notice('Are we being called @yeah?', ['@yeah' => $yeah]);
    parent::submitForm($form, $form_state);
  }

}