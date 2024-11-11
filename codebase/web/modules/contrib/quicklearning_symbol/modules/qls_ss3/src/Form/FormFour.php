<?php

namespace Drupal\qls_ss3\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

use Drupal\qls_ss3\Service\SymbolAccountService;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class FormFour extends FormBase {

  /**
   * SymbolAccountServiceのインスタンス
   *
   * @var \Drupal\qls_ss3\Service\SymbolAccountService
   */
  protected $symbolAccountService;

  /**
   * コンストラクタでSymbolAccountServiceを注入
   */
  public function __construct(SymbolAccountService $symbol_account_service) {
    $this->symbolAccountService = $symbol_account_service;
  }

  /**
   * createメソッドでサービスコンテナから依存性を注入
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('qls_ss3.symbol_account_service')
    );
  }

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
      '#markup' => $this->t('3.3.1 所有モザイク一覧の取得'),
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

    $form['address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address'),
      '#description' => $this->t('39文字（16進数）'),
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
      '#value' => $this->t('Get Account Info'),
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
    return 'form_four';
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
    $address = $form_state->getValue('address');
    if (strlen($address) !=  39) {
      // Set an error for the form element with a key of "public_key".
      $form_state->setErrorByName('address', $this->t('The address must be 39 characters long.'));
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
    $address = $form_state->getValue('address');
    $address = new Address($address);

    // ノードURLを設定
    if ($network_type === 'testnet') {
      $node_url = 'http://sym-test-03.opening-line.jp:3000';
    } elseif ($network_type === 'mainnet') {
      $node_url = 'http://sym-main-03.opening-line.jp:3000';
    }
    // SymbolAccountServiceを使ってアカウント情報を取得
    $account_info = $this->symbolAccountService->getAccountInfo($node_url, $address);

    if ($account_info) {
      // JSON形式でアカウント情報を表示
      $json_data = json_encode($account_info, JSON_PRETTY_PRINT);
      \Drupal::messenger()->addMessage($this->t('Account information: <pre>@data</pre>', ['@data' => $json_data]));
    }
    else {
      \Drupal::messenger()->addMessage($this->t('Failed to retrieve account information.'), 'error');
    }
    
  }

}
