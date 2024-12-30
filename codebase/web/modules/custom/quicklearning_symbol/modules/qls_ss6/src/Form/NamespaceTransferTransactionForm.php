<?php

namespace Drupal\qls_ss6\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Symbol\Models\TransferTransactionV1;
use SymbolSdk\Symbol\Models\NetworkType;
use SymbolSdk\Symbol\Models\Timestamp;
use SymbolSdk\Symbol\Models\UnresolvedMosaic;
use SymbolSdk\Symbol\Models\UnresolvedMosaicId;
use SymbolSdk\Symbol\Models\Amount;
use SymbolSdk\Symbol\Models\UnresolvedAddress;
use SymbolSdk\Symbol\Models\NamespaceId;
use SymbolSdk\Symbol\Address;
use SymbolSdk\Symbol\IdGenerator;

use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\TransactionStatusRoutesApi;
use SymbolRestClient\Configuration;
use SymbolSdk\Facade\SymbolFacade;

// use Drupal\qls_ss6\Service\SymbolAccountService;
// use Drupal\qls_ss6\Service\TransactionService;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class NamespaceTransferTransactionForm extends FormBase {

  /**
   * SymbolAccountServiceのインスタンス
   *
   * @var \Drupal\qls_ss6\Service\SymbolAccountService
   */
  // protected $symbolAccountService;

  /**
   * TransactionServiceのインスタンス
   *
   * @var \Drupal\qls_ss6\Service\TransactionService
   */
  // protected $transactionService;

  /**
   * コンストラクタでSymbolAccountServiceを注入
   */
  // public function __construct(TransactionService $transaction_service, SymbolAccountService $symbol_account_service) {
  // public function __construct(SymbolAccountService $symbol_account_service) {
  //   // $this->transactionService = $transaction_service;
  //   $this->symbolAccountService = $symbol_account_service;
  // }

  /**
   * createメソッドでサービスコンテナから依存性を注入
   */
  // public static function create(ContainerInterface $container) {
  //   return new static(
  //     // $container->get('qls_ss6.transaction_service'),         // TransactionService
  //     $container->get('qls_ss6.symbol_account_service')       // SymbolAccountService
  //   );
  // }
  
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
      '#markup' => $this->t('6.4 未解決で使用'),
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

    $form['sender_pvtKey'] = [
      '#type' => 'password',
      '#title' => $this->t('Sender Private Key'),
      '#description' => $this->t('Enter the private key of the sender.'),
      '#required' => TRUE,
    ];

    $form['recipient_namespace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recipient Namespace'),
      '#description' => $this->t('Namespace of the recipient address.'),
      '#required' => TRUE,
    ];
    
    $form['mosaic_namespace'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Mosaic Namespace'),
        '#required' => TRUE,
        '#description' => $this->t('Enter the Mosaic Namespace.e.g., symbol.xym'),
    ];
    $form['mosaic_amount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Amount'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the amount of the mosaic (e.g., 1000000).'),
      '#default_value' => '1000000',
    ];

    // ボタンを追加
    $form['actions'] = [
      '#type' => 'actions',
    ];
    
     // 送信ボタン
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
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
    return 'namespace_transfer_transaction_form';
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
    // $values = $form_state->getValues();
  // \Drupal::logger('form_debug')->notice('L.376 values:<pre>@values</pre>', ['@values' => print_r($values, TRUE)]);
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
    
 
    $recipient_namespace = $form_state->getValue('recipient_namespace');
    // \Drupal::logger('qls_ss6')->notice('recipient_namespace:<pre>@object</pre>', ['@object' => print_r($recipient_namespace, TRUE)]); 
    // UnresolvedAccount 導出
    $namespaceId = IdGenerator::generateNamespaceId($recipient_namespace); // ルートネームスペースのIDを取得
    // \Drupal::logger('qls_ss6')->notice('namespaceId:<pre>@object</pre>', ['@object' => print_r($namespaceId, TRUE)]); 

    $address = Address::fromNamespaceId(
      new NamespaceId($namespaceId),
      $facade->network->identifier
    );

    $sender_pvtKey = $form_state->getValue('sender_pvtKey');
    // 秘密鍵からアカウント生成
    $senderKey = $facade->createAccount(new PrivateKey($sender_pvtKey));


    $mosaic_namespace = $form_state->getValue(['mosaic_namespace']);
    $mosaicnamespaceIds = IdGenerator::generateNamespacePath($mosaic_namespace); // ルートネームスペースのIDを取得
    $mosaicnamespaceId = new NamespaceId($mosaicnamespaceIds[count($mosaicnamespaceIds) - 1]);

    
    $mosaic_amount = $form_state->getValue(['mosaic_amount']);
    // トランザクション
    // Tx作成
    $tx = new TransferTransactionV1(
      signerPublicKey: $senderKey->publicKey,
      network: $networkType,
      deadline: new Timestamp($facade->now()->addHours(2)),
      recipientAddress: new UnresolvedAddress($address),
      message: '',
      mosaics: [
          new UnresolvedMosaic(
            mosaicId: new UnresolvedMosaicId($mosaicnamespaceId),
            amount: new Amount($mosaic_amount)
          ),
        ],
      );
    $facade->setMaxFee($tx, 100);

    // //署名
    $sig = $senderKey->signTransaction($tx);
    $payload = $facade->attachSignature($tx, $sig);
    \Drupal::logger('qls_ss6')->notice('payload:<pre>@object</pre>', ['@object' => print_r($payload, TRUE)]); 

    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();
    $apiInstance = new TransactionRoutesApi($client, $config);

    try {
      $result = $apiInstance->announceTransaction($payload);
      // return $result;
      $this->messenger()->addMessage($this->t('Transaction successfully announced: @result', ['@result' => $result]));
    } catch (\Exception $e) {
      \Drupal::logger('qls_ss6')->error('トランザクションの発行中にエラーが発生しました: @message', ['@message' => $e->getMessage()]);
      // throw $e;
    }

    // try {
    //   // Drupal Serviceを使う方法
    //   // TransactionServiceを使ってトランザクションを発行
    //   $result = $this->transactionService->announceTransaction($node_url, $payload);
    //   $this->messenger()->addMessage($this->t('Transaction successfully announced: @result', ['@result' => $result]));
 
    // } catch (\Exception $e) {
    //   $this->messenger()->addError($this->t('Error: @message', ['@message' => $e->getMessage()]));
    // }

  }

}
