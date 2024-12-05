<?php

namespace Drupal\qls_ss6\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Facade\SymbolFacade;

use SymbolRestClient\Configuration;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\NetworkRoutesApi;

use SymbolSdk\Symbol\Models\NamespaceRegistrationTransactionV1;
use SymbolSdk\Symbol\Models\NetworkType;
use SymbolSdk\Symbol\Models\Timestamp;
use SymbolSdk\Symbol\Models\BlockDuration;
use SymbolSdk\Symbol\Models\NamespaceId;
use SymbolSdk\Symbol\IdGenerator;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class CreateNamespaceForm extends FormBase {

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
    $form['#attached']['library'][] = 'qls_ss6/create_namespace';

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('Nemaspace'),
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

    $form['ownder_pvtKey'] = [
      '#type' => 'password',
      '#title' => $this->t('Owner Private Key'),
      '#description' => $this->t('Enter the private key of the owner.'),
      '#required' => TRUE,
    ];

    $form['symbol_address'] = [
      '#markup' => '<div id="symbol_address">Symbol Address</div>',
    ];

    $form['root_namespace_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Root Namespace Name'),
      '#description' => $this->t('Lowercase alphabet, numbers 0-9, hyphen, and underscore'),
      '#required' => TRUE,
    ];

    $form['duration'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dulation'),
      '#description' => $this->t('Min:86400. Max:5256000'),
      // '#default_value' => 'Hello, Symbol!',
    ];

    $form['epov'] = [
      '#type' => 'item',
      '#title' => $this->t('Estimated Period Of Validity'),
      '#markup' => '<div id="estimated_period_of_validity">'.$this->t('00d 00h 00m').'</div>',
    ];

    $form['estimated_rental_fee'] = [
      '#type' => 'item', 
      '#title' => $this->t('Estimated Rental Fee'),
      '#markup' => '<div id="estimated_rental_fee">0XYM</div>',
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
      '#value' => $this->t('Make Namespace'),
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
    return 'create_namespace_form';
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
    
    $root_namespace_name = $form_state->getValue('root_namespace_name');
    if (!preg_match('/^[a-z0-9_-]+$/', $root_namespace_name)) {
      $form_state->setErrorByName('root_namespace_name', $this->t('Root Namespace Name must be lowercase alphabet, numbers 0-9, hyphen, and underscore.'));
    }
    if (strlen($root_namespace_name) > 64) {
      $form_state->setErrorByName('root_namespace_name', $this->t('Root Namespace Name cannot exceed 64 characters.'));
    }
    //The Duration field must be 86400(30day) or more 
    // 期間はブロック数で指定します。1 ブロックを30 秒として計算しました。最低で30 日分はレンタルする必要があります（最大で1825 日分, 5年）。
    $duration = $form_state->getValue('duration');
    if (!is_numeric($duration) || $duration < 86400 || $duration > 5256000) {
      $form_state->setErrorByName('duration', $this->t('The duration must be between 86400 and 5256000.'));
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
    $facade = new SymbolFacade($network_type);
    // ノードURLを設定
    if ($network_type === 'testnet') {
      $networkType = new NetworkType(NetworkType::TESTNET);
      $node_url = 'http://sym-test-03.opening-line.jp:3000';
    } elseif ($network_type === 'mainnet') {
      $networkType = new NetworkType(NetworkType::MAINNET);
      $node_url = 'http://sym-main-03.opening-line.jp:3000';
    }

    $ownder_pvtKey = $form_state->getValue('ownder_pvtKey');
    $ownerKey = $facade->createAccount(new PrivateKey($ownder_pvtKey));
    $root_namespace_name = $form_state->getValue('root_namespace_name');

    // \Drupal::logger('qls_ss6')->notice('<pre>@object</pre>', ['@object' => print_r($networkType, TRUE)]); 
    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();

    $networkApiInstance = new NetworkRoutesApi($client, $config);
    $rootNsperBlock = $networkApiInstance->getRentalFees()->getEffectiveRootNamespaceRentalFeePerBlock();

    $duration = $form_state->getValue('duration');
    $blockDuration = new BlockDuration($duration);
   
    $rootNsRenatalFeeTotal = $duration * $rootNsperBlock;
    // $rentalDays = 365;
    // $rentalBlock = ($rentalDays * 24 * 60 * 60) / 30;
    // $rootNsRenatalFeeTotal = $rentalBlock * $rootNsperBlock;

    $childNamespaceRentalFee = $networkApiInstance->getRentalFees()->getEffectiveChildNamespaceRentalFee();
   
    $tx = new NamespaceRegistrationTransactionV1(
      network: $networkType,
      signerPublicKey: $ownerKey->publicKey, // 署名者公開鍵
      deadline: new Timestamp($facade->now()->addHours(2)),
      duration: $blockDuration, // 有効期限
      id: new NamespaceId(IdGenerator::generateNamespaceId($root_namespace_name)), //必須
      name: $root_namespace_name,
    );
    $facade->setMaxFee($tx, 100);

    // 署名
    $sig = $ownerKey->signTransaction($tx);
    $payload = $facade->attachSignature($tx, $sig);

    // アナウンス
    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();
    $apiInstance = new TransactionRoutesApi($client, $config);
    
    try {
      $result = $apiInstance->announceTransaction($payload);
      // echo $result . PHP_EOL;
      $this->messenger()->addMessage($this->t('Transaction successfully announced: @result', ['@result' => $result]));
    } catch (Exception $e) {
      \Drupal::logger('qls_ss6')->error('トランザクションの発行中にエラーが発生しました: @message', ['@message' => $e->getMessage()]);
    }

  }

}
