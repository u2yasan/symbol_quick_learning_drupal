<?php

namespace Drupal\qls_ss8\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Polyfill\Php70\Php70;

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Facade\SymbolFacade;

use SymbolRestClient\Configuration;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\NetworkRoutesApi;
use SymbolRestClient\Api\ReceiptRoutesApi;
use SymbolRestClient\Api\SecretLockRoutesApi;
use SymbolSdk\Symbol\Models\EmbeddedTransferTransactionV1;
use SymbolSdk\Symbol\Models\NamespaceRegistrationTransactionV1;
use SymbolSdk\Symbol\Models\AggregateBondedTransactionV2;
use SymbolSdk\Symbol\Models\Hash256;
use SymbolSdk\Symbol\Models\LockHashAlgorithm;
use SymbolSdk\Symbol\Models\SecretLockTransactionV1;
use SymbolSdk\Symbol\Models\SecretProofTransactionV1;
use SymbolSdk\Symbol\Models\PublicKey;
use SymbolSdk\Symbol\Models\Signature;
use SymbolSdk\Symbol\Models\Cosignature;
use SymbolSdk\Symbol\Models\DetachedCosignature;
use SymbolSdk\Symbol\Models\NetworkType;
use SymbolSdk\Symbol\Models\Timestamp;
use SymbolSdk\Symbol\Models\Amount;
use SymbolSdk\Symbol\Models\UnresolvedAddress;
use SymbolSdk\Symbol\Models\UnresolvedMosaic;
use SymbolSdk\Symbol\Models\UnresolvedMosaicId;
use SymbolSdk\Symbol\Models\BlockDuration;
use SymbolSdk\Symbol\Models\NamespaceId;
use SymbolSdk\Symbol\Models\NamespaceRegistrationType;
use SymbolSdk\Symbol\Models\ReceiptType;
use SymbolSdk\Symbol\IdGenerator;

use Drupal\quicklearning_symbol\Service\AccountService;


/**
 * Provides a form with two steps.
 *
 * This example demonstrates a multistep form with text input elements. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class ConfirmReceiptForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'confirm_receipt_form';
  }

  /**
   * The AccountService instance.
   *
   * @var \Drupal\quicklearing_symbol\Service\AccountService
   */
  protected $accountService;

  /**
   * Constructs the form.
   *
   * @param \Drupal\quicklearing_symbol\Service\AccountService $account_service
   *   The account service.
   */
  public function __construct(AccountService $account_service) {
    $this->accountService = $account_service;
  }

  public static function create(ContainerInterface $container) {
    // AccountService をコンストラクタで注入
    $form = new static(
        $container->get('quicklearning_symbol.account_service')
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // $form['#attached']['library'][] = 'qls_ss8/confirm_receipt';

  
    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('承認結果を確認'),
    ];

    $form['recipientAddress'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recipient Address'),
      '#description' => $this->t('Enter the address of the recipient. TESTNET: Start with T / MAINNET: Start with N'),
      '#required' => TRUE,
    ];
    

    $form['network_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Network Type'),
      '#description' => $this->t('Select either testnet or mainnet'),
      '#options' => [
        'testnet' => $this->t('Testnet'),
        'mainnet' => $this->t('Mainnet'),
      ],
      '#default_value' => $form_state->hasValue(['step2', 'network_type']) ? $form_state->getValue(['step1', 'network_type']) : 'testnet',
      '#required' => TRUE,
    ];

    // Group submit handlers in an actions element with a key of "actions" so
    // that it gets styled correctly, and so that other modules may add actions
    // to the form. This is not required, but is convention.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t("Make Secret Proof Transaction"),
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
   
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
    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();
    $apiInstance = new TransactionRoutesApi($client, $config);
    $receiptApiInstance = new ReceiptRoutesApi($client, $config);
    $secretAipInstance = new SecretLockRoutesApi($client, $config);



    $recipientAddStr = $form_state->getValue('recipientAddress');
    // $account_info = $this->accountService->getAccountInfo($node_url, $recipientAddStr);
    // $recipientAddress = $account_info['address'];

    /**
     * レシート検索
     */

    $receiptApiInstance = new ReceiptRoutesApi($client, $config);
    $result = $receiptApiInstance->searchReceipts(
      receipt_type: new ReceiptType(ReceiptType::LOCK_SECRET_COMPLETED),
      target_address:$recipientAddStr
    );
    // echo 'レシート' . PHP_EOL;
    // echo $result . PHP_EOL;
    $this->messenger()->addMessage($this->t('searchReceipts: @result', ['@result' => $result]));


  }

  /**
   * Provides custom validation handler for page 1.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function ConfirmReceiptFormValidate(array &$form, FormStateInterface $form_state) {
  }

}
