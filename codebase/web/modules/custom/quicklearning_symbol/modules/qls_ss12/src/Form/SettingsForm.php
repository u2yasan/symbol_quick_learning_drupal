<?php

namespace Drupal\qls_ss12\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example module settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['qls_ss12.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qls_ss12_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'qls_ss12/settings';

    $config = $this->config('qls_ss12.settings');

    $form['network_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Network Type'),
      '#description' => $this->t('Select either testnet or mainnet'),
      '#options' => [
        'testnet' => $this->t('Testnet'),
        'mainnet' => $this->t('Mainnet'),
      ],
      '#default_value' => $config->get('network_type') ?? 'testnet',
      '#required' => TRUE,
    ];
    // NOTE: do NOT save private key in database especially in plain text.

    $form['restrict_account_pvtKey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account Private Key to be restricted'),
      '#default_value' => $config->get('restrict_account_pvtKey'),
      '#description' => $this->t('Restrict account'),
    ];
    $form['symbol_address'] = [
      '#markup' => '<div id="symbol_address">Symbol Address</div>',
    ];

    for ($i = 1; $i <= 5; $i++) {
      $form["cosignatory{$i}_pvtKey"] = [
        '#type' => 'textfield',
        '#title' => $this->t("Cosignatory{$i}"),
        '#default_value' => $config->get("cosignatory{$i}_pvtKey"),
        '#description' => $this->t("Cosignatory {$i} of multi-signature"),
      ];
    }
    

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    
    $this->config('qls_ss12.settings')
      ->set('network_type', $form_state->getValue('network_type'))
      ->set('restrict_account_pvtKey', $form_state->getValue('restrict_account_pvtKey'))
      ->set('cosignatory1_pvtKey', $form_state->getValue('cosignatory1_pvtKey'))
      ->set('cosignatory2_pvtKey', $form_state->getValue('cosignatory2_pvtKey'))
      ->set('cosignatory3_pvtKey', $form_state->getValue('cosignatory3_pvtKey'))
      ->set('cosignatory4_pvtKey', $form_state->getValue('cosignatory4_pvtKey'))
      ->set('cosignatory5_pvtKey', $form_state->getValue('cosignatory5_pvtKey'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}