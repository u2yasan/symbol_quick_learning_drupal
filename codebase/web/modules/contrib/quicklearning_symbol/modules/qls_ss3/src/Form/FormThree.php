<?php

namespace Drupal\qls_ss3\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use SymbolSdk\Symbol\KeyPair;
use SymbolSdk\Symbol\MessageEncoder;
use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolRestClient\Api\AccountRoutesApi;
use SymbolSdk\Symbol\Models\PublicKey;
use SymbolSdk\Symbol\Address;
use SymbolSdk\Symbol\Verifier;

use SymbolRestClient\Api\NodeRoutesApi;
use SymbolRestClient\Api\NetworkRoutesApi;
use SymbolRestClient\Configuration;
use SymbolSdk\Facade\SymbolFacade;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class FormThree extends FormBase {

  /**
   * Build the simple form.
   *
   * A build form method constructs an array that defines how markup and
   * other form elements are included in an HTML form.
   *
   * @param array $form
   *   Default form array structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object containing current form state.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('3.1.5 公開鍵クラスの生成'),
    ];

    $form['network_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Network Type'),
      '#description' => $this->t('Select either testnet or mainnet'),
      '#options' => [
        'testnet' => $this->t('Testnet'),
        'mainnet' => $this->t('Mainnet'),
      ],
      '#default_value' => 'testnet', // デフォルト選択を設定
      '#required' => TRUE,
    ];

    $form['public_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publick Key'),
      '#description' => $this->t('64文字（16進数）'),
      '#required' => TRUE,
    ];

    // Group submit handlers in an actions element with a key of "actions" so
    // that it gets styled correctly, and so that other modules may add actions
    // to the form. This is not required, but is convention.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button that handles the submission of the form.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Public Account From Publick Key'),
    ];

    return $form;
  }

  /**
   * Getter method for Form ID.
   *
   * The form ID is used in implementations of hook_form_alter() to allow other
   * modules to alter the render array built by this form controller. It must be
   * unique site wide. It normally starts with the providing module's name.
   *
   * @return string
   *   The unique ID of the form defined by this class.
   */
  public function getFormId() {
    return 'form_three';
  }

  /**
   * Implements form validation.
   *
   * The validateForm method is the default method called to validate input on
   * a form.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $pubKey = $form_state->getValue('public_key');
    if (strlen($pubKey) !=  64) {
      // Set an error for the form element with a key of "public_key".
      $form_state->setErrorByName('publick_key', $this->t('The publick key must be 64 characters long.'));
    }
  }

  /**
   * Implements a form submit handler.
   *
   * The submitForm method is the default method called for any submit elements.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /*
     * This would normally be replaced by code that actually does something
     * with the title.
     */
    $network_type = $form_state->getValue('network_type');
    $pubkey = $form_state->getValue('public_key');

    // SymbolFacadeを使って新しいアカウントを作成
    $facade = new SymbolFacade($network_type);

    //3.1.5 公開鍵クラスの生成
    $alicePublicAccount = $facade->createPublicAccount(new PublicKey($pubkey));
   
    $alicePubKey = substr($alicePublicAccount->publicKey, 2, 66);

    // 出力例
    // /admin/reports/dblog でログを確認
    //\Drupal::logger('qls_ss3')->notice('<pre>@object</pre>', ['@object' => print_r($alicePublicAccount, TRUE)]);
   

    $this->messenger()->addMessage($this->t('You specified a network_type of %network_type.', ['%network_type' => $network_type]));
    $this->messenger()->addMessage($this->t('Public Key: @alicePubKey', ['@alicePubKey' => $alicePubKey]));
  }

}
