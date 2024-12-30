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
class SecretLockForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'secret_lock_form';
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
    $form['#attached']['library'][] = 'qls_ss8/secret_lock';

  
    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('8.2 シークレットロック・シークレットプルーフ'),
    ];

    $form['step1'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('8.2.1 シークレットロック'),
    ];

    $form['step1']['proof'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Proof'),
      '#description' => $this->t('解除用キーワード'),
      '#required' => TRUE,
      '#prefix' => '<div id="edit-proof-wrapper">', // ラッパー追加
      '#suffix' => '</div>',
    ];

    $form['step1']['generate_proof'] = [
      '#type' => 'button',
      '#value' => $this->t('解除用キーワード生成'),
      '#ajax' => [
        'callback' => '::generateProofCallback',
        'wrapper' => 'edit-proof-wrapper', // 更新対象
      ],
      '#limit_validation_errors' => [], // すべてのバリデーションをスキップ
    ];
    
    $form['step1']['secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret'),
      '#description' => $this->t('ロック用キーワード'),
      '#required' => TRUE,
      '#prefix' => '<div id="edit-secret-wrapper">', // ラッパー追加
      '#suffix' => '</div>',
    ];
    
    $form['step1']['generate_secret'] = [
      '#type' => 'button',
      '#value' => $this->t('ロック用キーワード生成'),
      '#ajax' => [
        'callback' => '::generateSecretCallback',
        'wrapper' => 'edit-secret-wrapper', // 更新対象
      ],
      '#limit_validation_errors' => [], // すべてのバリデーションをスキップ
    ];

    

    $form['step2'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('トランザクション作成・署名・アナウンス'),
    ];
    $form['step2']['network_type'] = [
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

    $form['step2']['originator_pvtKey'] = [
      '#type' => 'password',
      '#title' => $this->t('Originator Private Key'),
      '#description' => $this->t('Enter the private key of the originator.'),
      '#required' => TRUE,
    ];

    $form['step2']['symbol_address'] = [
      '#markup' => '<div id="symbol_address">Symbol Address</div>',
    ];
    $form['step2']['symbol_address_hidden'] = [
      '#type' => 'hidden',
      // '#value' => '', // 初期値は空
      '#attributes' => [
        'id' => 'symbol-address-hidden', // カスタム ID を指定
      ],
    ];

    $form['step2']['recipientAddress'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recipient Address'),
      '#description' => $this->t('Enter the address of the recipient. TESTNET: Start with T / MAINNET: Start with N'),
      '#required' => TRUE,
      '#default_value' => 'TAJZXDFDOCVYVID4S45BLPGSPLPFUQIAUO5PBIA',
    ];

    $form['step2']['mosaicid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mosaic ID'),
      '#description' => $this->t('TESTNET XYM:0x72C0212E67A08BCE / MAINNET XYM:0x6BED913FA20223F8'),
      '#required' => TRUE,
      '#default_value' => '0x72C0212E67A08BCE',
    ];

    $form['step2']['amount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Amount'),
      '#description' => $this->t('Enter the amount of the mosaic. (1 XYM = 1000000)'),
      '#required' => TRUE,
      '#default_value' => '1000000',
    ];

    $form['step2']['blockDuration'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Block Duration'),
      '#description' => $this->t('Enter the Block duration.'),
      '#required' => TRUE,
      '#default_value' => '480',
    ];


    // Group submit handlers in an actions element with a key of "actions" so
    // that it gets styled correctly, and so that other modules may add actions
    // to the form. This is not required, but is convention.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t("Make Secret Lock Transaction"),
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
    // $receiptApiInstance = new ReceiptRoutesApi($client, $config);
    // $secretAipInstance = new SecretLockRoutesApi($client, $config);

    $originator_pvtKey = $form_state->getValue('originator_pvtKey');
    $originatorKey = $facade->createAccount(new PrivateKey($originator_pvtKey));

    $recipientAddStr = $form_state->getValue('recipientAddress');
    $account_info = $this->accountService->getAccountInfo($node_url, $recipientAddStr);
    // \Drupal::logger('qls_ss8')->notice('Account Info: @account_info', ['@account_info' => $account_info]);
    $recipientAddress = $account_info['address'];
    // \Drupal::logger('qls_ss8')->notice('Recipient Address: @recipientAddress', ['@recipientAddress' => $recipientAddress]);

    $secret = $form_state->getValue('secret');
    // $proof = $form_state->getValue('proof');
    $mosaicid = $form_state->getValue('mosaicid');
    $amount = $form_state->getValue('amount');
    $blockDuration = $form_state->getValue('blockDuration'); // ロック期間

    // シークレットロックTx作成
    $lockTx = new SecretLockTransactionV1(
      signerPublicKey: $originatorKey->publicKey,  // 署名者公開鍵
      deadline: new Timestamp($facade->now()->addHours(2)), // 有効期限
      network: $networkType,
      mosaic: new UnresolvedMosaic(
        mosaicId: new UnresolvedMosaicId($mosaicid), // モザイクID
        amount: new Amount($amount) // ロックするモザイク
      ),
      duration: new BlockDuration($blockDuration), //ロック期間
      hashAlgorithm: new LockHashAlgorithm(LockHashAlgorithm::SHA3_256), // ハッシュアルゴリズム
      secret: new Hash256($secret), // ロック用キーワード
      recipientAddress: new UnresolvedAddress($recipientAddress), // 解除時の転送先：
    );
    $facade->setMaxFee($lockTx, 100);  // 手数料

    // 署名
    $lockSig = $originatorKey->signTransaction($lockTx);
    $payload = $facade->attachSignature($lockTx, $lockSig);
    \Drupal::logger('qls_ss8')->notice('Secret Lock Payload: @payload', ['@payload' => $payload]);
    try {
      $result = $apiInstance->announceTransaction($payload);
      $this->messenger()->addMessage($this->t('Lock Transaction successfully announced: @result', ['@result' => $result]));
      // echo $result . PHP_EOL;
    } catch (Exception $e) {
      echo 'Exception when calling TransactionRoutesApi->announceTransaction: ', $e->getMessage(), PHP_EOL;
    }
    // echo 'シークレットロックTxHash' . PHP_EOL;
    // echo $facade->hashTransaction($lockTx) . PHP_EOL;
    
    \Drupal::logger('qls_ss8')->notice('Secret Lock TxHash: @hash', ['@hash' => $facade->hashTransaction($lockTx)]);

    // sleep(1);

    // // シークレットプルーフTx作成
    // $proofTx = new SecretProofTransactionV1(
    //   signerPublicKey: $originatorKey->publicKey,  // 署名者公開鍵
    //   deadline: new Timestamp($facade->now()->addHours(2)), // 有効期限
    //   network: $networkType,
    //   hashAlgorithm: new LockHashAlgorithm(LockHashAlgorithm::SHA3_256), // ハッシュアルゴリズム
    //   secret: new Hash256($secret), // ロック用キーワード
    //   recipientAddress: $recipientAddress, // 解除時の転送先：
    //   proof: $proof, // 解除用キーワード
    // );
    // $facade->setMaxFee($proofTx, 100);  // 手数料

    // // 署名
    // $proofSig = $bobKey->signTransaction($proofTx);
    // $payload = $facade->attachSignature($proofTx, $proofSig);

    // try {
    //   $result = $apiInstance->announceTransaction($payload);
    //   echo $result . PHP_EOL;
    // } catch (Exception $e) {
    //   echo 'Exception when calling TransactionRoutesApi->announceTransaction: ', $e->getMessage(), PHP_EOL;
    // }
    // echo 'シークレットプルーフTxHash' . PHP_EOL;
    // echo $facade->hashTransaction($proofTx) . PHP_EOL;

    // sleep(30);

    /**
     * 結果の確認
     */
    // $txInfo = $apiInstance->getConfirmedTransaction($facade->hashTransaction($proofTx));
    // echo '承認確認' . PHP_EOL;
    // echo $txInfo . PHP_EOL;

  }

  /**
   * Provides custom validation handler for page 1.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function SecretLockFormValidate(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Ajax callback for generating a random keyword.
   */
  public function generateProofCallback(array &$form, FormStateInterface $form_state) {
    // ランダムなキーワードを生成
    $proof = random_bytes(20);
  
    $form_state->setValue(['proof'], bin2hex($proof));

    // 更新対象フィールドの値を設定
    $form['step1']['proof']['#value'] = bin2hex($proof);

    return $form['step1']['proof']; // 必要な部分だけ返す
  }

  public function generateSecretCallback(array &$form, FormStateInterface $form_state) {
    // ランダムなキーワードを生成
    $proof = $form_state->getValue(['proof']);
    $secret = hash('sha3-256', $proof, true); // ロック用キーワード
    
    // 更新対象フィールドの値を設定
    $form['step1']['secret']['#value'] = bin2hex($secret);

    return $form['step1']['secret']; // 必要な部分だけ返す
  }

  


}
