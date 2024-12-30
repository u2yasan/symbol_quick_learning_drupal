<?php
namespace Drupal\qls_ss7\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use SymbolSdk\Symbol\MessageEncoder;
use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Symbol\Models\TransferTransactionV1;
use SymbolSdk\Symbol\Models\NetworkType;
use SymbolSdk\Symbol\Models\Timestamp;
use SymbolSdk\Symbol\Models\UnresolvedMosaic;
use SymbolSdk\Symbol\Models\UnresolvedMosaicId;
use SymbolSdk\Symbol\Models\Amount;
use SymbolSdk\Symbol\Models\UnresolvedAddress;
use SymbolSdk\Symbol\Address;

use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\TransactionStatusRoutesApi;
use SymbolRestClient\Configuration;
use SymbolSdk\Facade\SymbolFacade;

use SymbolRestClient\Api\MosaicRoutesApi;
use SymbolRestClient\Api\MetadataRoutesApi;
use SymbolSdk\Symbol\Models\EmbeddedAccountMetadataTransactionV1;
use SymbolSdk\Symbol\Models\EmbeddedMosaicMetadataTransactionV1;
use SymbolSdk\Symbol\Models\AggregateCompleteTransactionV2;
use SymbolSdk\Symbol\Models\EmbeddedNamespaceMetadataTransactionV1;
use SymbolSdk\Symbol\Metadata;
use SymbolSdk\Symbol\IdGenerator;
use SymbolRestClient\Api\NamespaceRoutesApi;
use SymbolSdk\Symbol\Models\NamespaceId;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class MetaAccountForm extends FormBase {

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
    return 'meta_account_form';
  }
  // /**
  //  * SymbolAccountServiceのインスタンス
  //  *
  //  * @var \Drupal\qls_ss7\Service\SymbolAccountService
  //  */
  // protected $symbolAccountService;

  // /**
  //  * TransactionServiceのインスタンス
  //  *
  //  * @var \Drupal\qls_ss7\Service\TransactionService
  //  */
  // protected $transactionService;

  // /**
  //  * コンストラクタでSymbolAccountServiceを注入
  //  */
  // public function __construct(TransactionService $transaction_service, SymbolAccountService $symbol_account_service) {
  //   $this->transactionService = $transaction_service;
  //   $this->symbolAccountService = $symbol_account_service;
  // }

  // /**
  //  * createメソッドでサービスコンテナから依存性を注入
  //  */
  // public static function create(ContainerInterface $container) {
  //   return new static(
  //     $container->get('qls_ss7.transaction_service'),         // TransactionService
  //     $container->get('qls_ss7.symbol_account_service')       // SymbolAccountService
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
    $form['#attached']['library'][] = 'qls_ss7/metadata_account';

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('7.1 アカウントに登録'),
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

    $form['source_pvtKey'] = [
      '#type' => 'password',
      '#title' => $this->t('Source Private Key'),
      '#description' => $this->t('Metadata Source Owner Private Key.'),
      '#required' => TRUE,
    ];
    $form['source_symbol_address'] = [
      '#markup' => '<div id="source-symbol-address">Source Symbol Address</div>',
    ];

    $form['target_pvtKey'] = [
      '#type' => 'password',
      '#title' => $this->t('Target Private Key'),
      '#description' => $this->t('Metadata Target Owner Private Key.'),
      '#required' => TRUE,
    ];
    $form['target_symbol_address'] = [
      '#markup' => '<div id="target-symbol-address">Target Symbol Address</div>',
    ];

    $form['metadata_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Metadata Key'),
      '#description' => $this->t('Metadata Key.'),
      '#required' => TRUE,
    ];

    $form['metadata_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Metadata Value'),
      '#description' => $this->t('Metadata Value. (Max 1024 bytes)'),
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
      '#value' => $this->t('Make Metadata'),
    ];

    return $form;
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
     // メタデータ値を取得
    $metadata_value = $form_state->getValue('metadata_value');
    
    // バイト数を取得
    $byte_length = strlen($metadata_value);

    // 1024バイトを超える場合はエラーを設定
    if ($byte_length > 1024) {
      $form_state->setErrorByName('metadata_value', $this->t('The metadata value must not exceed 1024 bytes. It is currently %length bytes.', [
        '%length' => $byte_length,
      ]));
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
    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();
    $metaApiInstance = new MetadataRoutesApi($client, $config);
    
    $source_pvtKey = $form_state->getValue('source_pvtKey');
    $sourceKey = $facade->createAccount(new PrivateKey($source_pvtKey));
    $sourceAddress = $sourceKey->address; // メタデータ作成者アドレス

    $target_pvtKey = $form_state->getValue('target_pvtKey');
    $targetKey = $facade->createAccount(new PrivateKey($target_pvtKey));
    $targetAddress = $targetKey->address; // メタデータ記録先アドレス

    // キーと値の設定
    $metadata_key = $form_state->getValue('metadata_key');
    $metadata_value = $form_state->getValue('metadata_value');

    $keyId = Metadata::metadataGenerateKey($metadata_key);
    $newValue = $metadata_value;

    // 同じキーのメタデータが登録されているか確認
    if($source_pvtKey === $target_pvtKey) {
      $metadataInfo = $metaApiInstance->searchMetadataEntries(
        source_address: $sourceAddress,
        scoped_metadata_key: strtoupper(dechex($keyId)), // 16進数の大文字の文字列に変換
      );
    } else {
      $metadataInfo = $metaApiInstance->searchMetadataEntries(
        source_address: $sourceAddress,
        target_address: $targetAddress,
        scoped_metadata_key: strtoupper(dechex($keyId)), // 16進数の大文字の文字列に変換
      );
    }

    // $oldValue = hex2bin($metadataInfo['data'][0]['metadata_entry']['value']); //16進エンコードされたバイナリ文字列をデコード
    if (!empty($metadataInfo['data'][0]['metadata_entry']['value'])) {
      $oldValue = hex2bin($metadataInfo['data'][0]['metadata_entry']['value']);
    } else {
        $oldValue = ''; // デフォルト値を設定
    }
    $updateValue = Metadata::metadataUpdateValue($oldValue, $newValue, true);

    $tx = new EmbeddedAccountMetadataTransactionV1(
      network: $networkType,
      signerPublicKey: $sourceKey->publicKey,  // 署名者公開鍵
      targetAddress: new UnresolvedAddress($targetAddress),  // メタデータ記録先アドレス
      scopedMetadataKey: $keyId,
      valueSizeDelta: strlen($newValue) - strlen($oldValue),
      value: $updateValue,
    );
   
    // マークルハッシュの算出
    $embeddedTransactions = [$tx];
    $merkleHash = $facade->hashEmbeddedTransactions($embeddedTransactions);
    
    // アグリゲートTx作成
    $aggregateTx = new AggregateCompleteTransactionV2(
      network: $networkType,
      signerPublicKey: $sourceKey->publicKey,
      deadline: new Timestamp($facade->now()->addHours(2)),
      transactionsHash: $merkleHash,
      transactions: $embeddedTransactions,
    );
    

    if($source_pvtKey === $target_pvtKey) {
      // 手数料
      $facade->setMaxFee($aggregateTx, 100);
      // トランザクションの署名
      $sig = $sourceKey->signTransaction($aggregateTx);
      $payload = $facade->attachSignature($aggregateTx, $sig);
    } else {
      $facade->setMaxFee($aggregateTx, 100, 1);
      // 作成者による署名
      $sig = $sourceKey->signTransaction($aggregateTx);
      $facade->attachSignature($aggregateTx, $sig);
      // 記録先アカウントによる連署
      $coSig = $targetKey->cosignTransaction($aggregateTx);
      array_push($aggregateTx->cosignatures, $coSig);
      $payload = ['payload' => strtoupper(bin2hex($aggregateTx->serialize()))];
    }

    $apiInstance = new TransactionRoutesApi($client, $config);

    try {
      $result = $apiInstance->announceTransaction($payload);
      // return $result;
      $this->messenger()->addMessage($this->t('Transaction successfully announced: @result', ['@result' => $result]));
    } catch (\Exception $e) {
      \Drupal::logger('qls_ss7')->error('トランザクションの発行中にエラーが発生しました: @message', ['@message' => $e->getMessage()]);
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

  
    
    /**
    * 承認確認
    */
    // after 30 seconds
    // try {
    //   $apiInstance = new TransactionRoutesApi($client, $config);
    //   $result = $apiInstance->getConfirmedTransaction($hash);
    //   $this->messenger()->addMessage($this->t('Confirmed Transaction: @result', ['@result' => $result]));
    // } catch (Exception $e) {
    //   // echo 'Exception when calling TransactionRoutesApi->announceTransaction:'
    //   $this->messenger()->addError($this->t('Error: @message', ['@message' => $e->getMessage()])); 
    // }

    // // $this->messenger()->addMessage($this->t('You specified a network_type of %network_type.', ['%network_type' => $network_type]));
    // $this->messenger()->addMessage($this->t('payload: %payload', ['%payload' => $payload['payload']]));
  
  }

}