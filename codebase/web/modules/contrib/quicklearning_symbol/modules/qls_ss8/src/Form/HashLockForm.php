<?php

namespace Drupal\qls_ss8\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Facade\SymbolFacade;

use SymbolRestClient\Configuration;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\NetworkRoutesApi;
use SymbolSdk\Symbol\Models\EmbeddedTransferTransactionV1;
use SymbolSdk\Symbol\Models\NamespaceRegistrationTransactionV1;
use SymbolSdk\Symbol\Models\AggregateBondedTransactionV2;
use SymbolSdk\Symbol\Models\Hash256;
use SymbolSdk\Symbol\Models\HashLockTransactionV1;
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
class HashLockForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hash_lock_form';
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
    $form['#attached']['library'][] = 'qls_ss8/hash_lock';

    if ($form_state->has('page_num') && $form_state->get('page_num') == 2) {
      return $this->hashLockPageTwo($form, $form_state);
    }

    $form_state->set('page_num', 1);

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('8.1 ハッシュロック'),
    ];

    $form['step1'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Step 1: アグリゲートボンデッドトランザクションの作成'),
    ];
    $form['step1']['network_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Network Type'),
      '#description' => $this->t('Select either testnet or mainnet'),
      '#options' => [
        'testnet' => $this->t('Testnet'),
        'mainnet' => $this->t('Mainnet'),
      ],
      '#default_value' => $form_state->hasValue(['step1', 'network_type']) ? $form_state->getValue(['step1', 'network_type']) : 'testnet',
      '#required' => TRUE,
    ];

    $form['step1']['originator_pvtKey'] = [
      '#type' => 'password',
      '#title' => $this->t('Originator Private Key'),
      '#description' => $this->t('Enter the private key of the originator.'),
      '#required' => TRUE,
    ];

    $form['step1']['symbol_address'] = [
      '#markup' => '<div id="symbol_address">Symbol Address</div>',
    ];
    $form['step1']['symbol_address_hidden'] = [
      '#type' => 'hidden',
      // '#value' => '', // 初期値は空
      '#attributes' => [
        'id' => 'symbol-address-hidden', // カスタム ID を指定
      ],
    ];

    $form['step1']['recipientAddress'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recipient Address'),
      '#description' => $this->t('Enter the address of the recipient. TESTNET: Start with T / MAINNET: Start with N'),
      '#required' => TRUE,
      '#default_value' => 'TAJZXDFDOCVYVID4S45BLPGSPLPFUQIAUO5PBIA',
    ];

    $form['step1']['mosaicid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mosaic ID'),
      '#description' => $this->t('TESTNET XYM:0x72C0212E67A08BCE / MAINNET XYM:0x6BED913FA20223F8'),
      '#required' => TRUE,
      '#default_value' => '0x72C0212E67A08BCE',
    ];

    $form['step1']['amount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Amount'),
      '#description' => $this->t('Enter the amount of the mosaic. (1 XYM = 1000000)'),
      '#required' => TRUE,
      '#default_value' => '1000000',
    ];


    // Group submit handlers in an actions element with a key of "actions" so
    // that it gets styled correctly, and so that other modules may add actions
    // to the form. This is not required, but is convention.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['next'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t("Make Cosignature Transaction"),
      // Custom submission handler for page 1.
      '#submit' => ['::hashLockFormNextSubmit'],
      // Custom validation handler for page 1.
      // '#validate' => ['::hashLockFormNextValidate'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $page_values = $form_state->get('page_values');
    \Drupal::logger('qls_ss8')->debug('submitForm: @form_state', ['@form_state' => print_r($page_values, TRUE)]);
   

    // $this->messenger()->addMessage($this->t('The form has been submitted. name="@first @last", year of birth=@year_of_birth', [
    //   '@first' => $page_values['first_name'],
    //   '@last' => $page_values['last_name'],
    //   '@year_of_birth' => $page_values['birth_year'],
    // ]));

    // $this->messenger()->addMessage($this->t('And the favorite color is @color', ['@color' => $form_state->getValue('color')]));
    /**
     * 連署
     */
    // トランザクションの取得
    // $aggregateTxHash = $page_values['aggregateTxHash'];
    $aggregateTxHash = $page_values['aggregateTxHash'];
   
    $network_type = $page_values['network_type'];
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

    $txInfo = $apiInstance->getPartialTransaction($aggregateTxHash);

    // $txInfo = $apiInstance->getPartialTransaction($facade->hashTransaction($aggregateTx));

    $cosigner_pvtKey = $form_state->getValue('cosigner_pvtKey');
    // $cosigner_pvtKey = $page_values['cosigner_pvtKey'];
    $cosignerKey = $facade->createAccount(new PrivateKey($cosigner_pvtKey));

    // // 連署者の連署
    $signTxHash = new Hash256($txInfo->getMeta()->getHash());
    $signature = new Signature($cosignerKey->keyPair->sign($signTxHash->binaryData));
    $body = [
        'parentHash' => $signTxHash->__toString(),
        'signature' => $signature->__toString(),
        'signerPublicKey' => $cosignerKey->publicKey->__toString(),
        'version' => '0'
    ];

    // print_r($body);
    \Drupal::logger('qls_ss8')->debug('cosignature body: @body', ['@body' => print_r($body, TRUE)]);

    //アナウンス
    try {
      $result = $apiInstance->announceCosignatureTransaction($body);
      // echo $result . PHP_EOL;
      $this->messenger()->addMessage($this->t('Cosignature successfully announced: @result', ['@result' => $result]));

    } catch (Exception $e) {
      echo 'Exception when calling TransactionRoutesApi->announceTransaction: ', $e->getMessage(), PHP_EOL;
    }
  }

  /**
   * Provides custom validation handler for page 1.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function hashLockFormNextValidate(array &$form, FormStateInterface $form_state) {
    // $birth_year = $form_state->getValue('birth_year');

    // if ($birth_year != '' && ($birth_year < 1900 || $birth_year > 2000)) {
    //   // Set an error for the form element with a key of "birth_year".
    //   $form_state->setErrorByName('birth_year', $this->t('Enter a year between 1900 and 2000.'));
    // }
  }

  /**
   * Provides custom submission handler for page 1.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function hashLockFormNextSubmit(array &$form, FormStateInterface $form_state) {
    
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

    $originator_pvtKey = $form_state->getValue('originator_pvtKey');
    $originatorKey = $facade->createAccount(new PrivateKey($originator_pvtKey));

    $recipientAddStr = $form_state->getValue('recipientAddress');
    // $recipientAddress = new UnresolvedAddress($recipientAddStr);
    // AccountServiceを使ってアカウント情報を取得
    $account_info = $this->accountService->getAccountInfo($node_url, $recipientAddStr);
    // \Drupal::logger('qls_ss8')->debug('account_info: @account_info', ['@account_info' => print_r($account_info, TRUE)]); 
    $account = $account_info->getAccount(); // AccountDTO を取得
    $address = $account->getAddress(); // address を取得
    $recipentPublicKeyStr = $account->getPublicKey();
    $recipientAddress = new UnresolvedAddress($address);
    $recipentPublicKey = new PublicKey($recipentPublicKeyStr);

    $mosaicid = $form_state->getValue('mosaicid');
    $amount = $form_state->getValue('amount');

    // // アグリゲートTxに含めるTxを作成
    $tx1 = new EmbeddedTransferTransactionV1(
      network: $networkType,
      signerPublicKey: $originatorKey->publicKey,
      recipientAddress: $recipientAddress,
      mosaics: [
        new UnresolvedMosaic(
          mosaicId: new UnresolvedMosaicId($mosaicid),
          amount: new Amount($amount)
        )
      ],
      message: "",  //メッセージなし
    );

    $tx2 = new EmbeddedTransferTransactionV1(
      network: $networkType,
      signerPublicKey: $recipentPublicKey,
      recipientAddress: $originatorKey->address,
      message: "\0thank you!",
    );
        
    // マークルハッシュの算出
    $embeddedTransactions = [$tx1, $tx2];
    $merkleHash = $facade->hashEmbeddedTransactions($embeddedTransactions); 
    
    // アグリゲートボンデットTx作成
    $aggregateTx = new AggregateBondedTransactionV2(
      network: $networkType,
      signerPublicKey: $originatorKey->publicKey,
      deadline: new Timestamp($facade->now()->addHours(2)),
      transactionsHash: $merkleHash,
      transactions: $embeddedTransactions
    );
    $facade->setMaxFee($aggregateTx, 100, 1); 

    // 署名
    $sig = $originatorKey->signTransaction($aggregateTx);
    $payload = $facade->attachSignature($aggregateTx, $sig);

    /**
     * ハッシュロック
     */
    $xym_namespaceIds = IdGenerator::generateNamespacePath('symbol.xym');
    $xym_namespaceId = new NamespaceId($xym_namespaceIds[count($xym_namespaceIds) - 1]);

    $hashLockTx = new HashLockTransactionV1(
      signerPublicKey: $originatorKey->publicKey,
      network: $networkType,
      deadline: new Timestamp($facade->now()->addHours(2)), // 有効期限
      duration: new BlockDuration(480), // 有効期限
      hash: new Hash256($facade->hashTransaction($aggregateTx)), // ペイロードのハッシュ
      mosaic: new UnresolvedMosaic(
        mosaicId: new UnresolvedMosaicId($xym_namespaceId), // モザイクID
        amount: new Amount(10 * 1000000) // 金額(10XYM)
      )
    );
    $facade->setMaxFee($hashLockTx, 100);  // 手数料

    // 署名
    $hashLockSig = $originatorKey->signTransaction($hashLockTx);
    $hashLockJsonPayload = $facade->attachSignature($hashLockTx, $hashLockSig);

    /**
     * ハッシュロックをアナウンス
     */
    $apiInstance = new TransactionRoutesApi($client, $config);

    try {
      $result = $apiInstance->announceTransaction($hashLockJsonPayload);
      $this->messenger()->addMessage($this->t('hashLockTx successfully announced: @result', ['@result' => $result]));

    } catch (Exception $e) {
      \Drupal::logger('qls_ss8')->error('Exception when calling TransactionRoutesApi->announceTransaction: @message', ['@message' => $e->getMessage()]);
    }
    
    \Drupal::logger('qls_ss8')->debug('hashLockTx: @tx', ['@tx' => $facade->hashTransaction($hashLockTx)]);
    sleep(35); // トランザクションの処理待ち

    /**
     * アグリゲートボンデットTxをアナウンス
     */
    try {
      $result = $apiInstance->announcePartialTransaction($payload);
      $this->messenger()->addMessage($this->t('Partial Transaction successfully announced: @result', ['@result' => $result]));

    } catch (Exception $e) {
      echo 'Exception when calling TransactionRoutesApi->announcePartialTransaction: ', $e->getMessage(), PHP_EOL;
    }

    $aggregateTxHashObj = $facade->hashTransaction($aggregateTx);
    $aggregateTxHash = bin2hex($aggregateTxHashObj->binaryData);
    \Drupal::logger('qls_ss8')->debug('aggregateTx: @tx', ['@tx' => $aggregateTxHash]);

    $form_state
      ->set('page_values', [
        // Keep only first step values to minimize stored data.
        'network_type' => $form_state->getValue('network_type'),
        // 'originator_pvtKey' => $form_state->getValue('originator_pvtKey'),
        // 'symbol_address_hidden' => $form_state->getValue('symbol_address_hidden'),
        // 'recipientAddress' => $form_state->getValue('recipientAddress'),
        // 'mosaicid' => $form_state->getValue('mosaicid'),
        // 'amount' => $form_state->getValue('amount'),
        'aggregateTxHash' => $aggregateTxHash,
      ])
      ->set('page_num', 2)
      // Since we have logic in our buildForm() method, we have to tell the form
      // builder to rebuild the form. Otherwise, even though we set 'page_num'
      // to 2, the AJAX-rendered form will still show page 1.
      ->setRebuild(TRUE);
  }

  /**
   * Builds the second step form (page 2).
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function hashLockPageTwo(array &$form, FormStateInterface $form_state) {
    \Drupal::logger('qls_ss8')->debug('hashLockPageTwo: @form_state', ['@form_state' => print_r($form_state->get('page_values'), TRUE)]);
    $aggregateTxHash = $form_state->get('page_values')['aggregateTxHash'];

    $form['description'] = [
      '#type' => 'item',
      '#title' => $this->t('ロックされたトランザクションを指定されたアカウントで連署します'),
    ];

    $form['step2'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Step 2:連署'),
    ];
    $form['step2']['cosigner_pvtKey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Co-signer Private Key'),
      '#description' => $this->t('Enter the private key of the co-signer.'),
      '#required' => TRUE,
    ];

    $form['step2']['aggregateTxHash'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Aggregate Transaction Hash'),
      '#description' => $this->t('Enter the hash of the aggregate transaction hash.'),
      '#required' => TRUE,
      '#default_value' => $aggregateTxHash,
    ];

    $form['expires_in'] = [
      '#type' => 'item',
      '#title' => $this->t('Expires In'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    // $form['actions']['back'] = [
    //   '#type' => 'submit',
    //   '#value' => $this->t('Back'),
    //   // Custom submission handler for 'Back' button.
    //   '#submit' => ['::hashLockFormPageTwoBack'],
    //   // We won't bother validating the required 'color' field, since they
    //   // have to come back to this page to submit anyway.
    //   '#limit_validation_errors' => [],
    // ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Co-sign'),
    ];

    return $form;
  }

  /**
   * Provides custom submission handler for 'Back' button (page 2).
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  // public function hashLockFromPageTwoBack(array &$form, FormStateInterface $form_state) {
  //   $form_state
  //     // Restore values for the first step.
  //     ->setValues($form_state->get('page_values'))
  //     ->set('page_num', 1)
  //     // Since we have logic in our buildForm() method, we have to tell the form
  //     // builder to rebuild the form. Otherwise, even though we set 'page_num'
  //     // to 1, the AJAX-rendered form will still show page 2.
  //     ->setRebuild(TRUE);
  // }

}
