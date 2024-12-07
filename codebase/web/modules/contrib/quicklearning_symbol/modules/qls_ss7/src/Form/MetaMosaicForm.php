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
// use SymbolSdk\Symbol\Models\Address;

use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\TransactionStatusRoutesApi;
use SymbolRestClient\Api\AccountRoutesApi;
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
class MetaMosaicForm extends FormBase {

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
    return 'meta_mosaic_form';
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
    $form['#attached']['library'][] = 'qls_ss7/metadata_mosaic';

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('7.2 モザイクに登録'),
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
      // '#ajax' => [
      //   'callback' => '::promptCallback', // Ajax コールバック関数
      //   'event' => 'blur',                   // blur イベントで発火
      //   'wrapper' => 'mosaic-fieldset-wrapper', // 更新対象の要素 ID
      // ],
      // '#limit_validation_errors' => [], // バリデーションをスキップ
    ];
    $form['source_symbol_address'] = [
      '#markup' => '<div id="source-symbol-address">Source Symbol Address</div>',
    ];
    // $form['symbol_address_hidden'] = [
    //   '#type' => 'hidden',
    //   '#attributes' => [
    //     'id' => 'symbol-address-hidden', 
    //   ],
    // ];
    // This fieldset just serves as a container for the part of the form
    // that gets rebuilt. It has a nice line around it so you can see it.
    $form['mosaic_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mosaic'),
      // '#open' => TRUE,
      // We set the ID of this fieldset to fieldset-wrapper so the
      // AJAX command can replace it.
      '#attributes' => [
        'id' => 'mosaic-fieldset-wrapper',
        // 'class' => ['mosaic-wrapper'],
      ]
    ];
    $form['mosaic_fieldset']['mosaic'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mosaic'),
    ];
    // $form['mosaic_fieldset']['mosaic'] = [
    //   '#type' => 'select',
    //   '#title' => $this->t('Mosaic'),
    //   '#options' => [],
    //   '#required' => TRUE,
    // ];
    // $source_pvtKey = $form_state->getValue('source_pvtKey');
    // \Drupal::logger('qls_ss7')->notice('source_pvtKey:<pre>@object</pre>', ['@object' => print_r($source_pvtKey, TRUE)]);
    // if($source_pvtKey) {
    //   $form['mosaic_fieldset']['mosaic'] = [
    //     '#type' => 'select',
    //     '#title' => $this->t('Mosaic'),
    //     '#options' => $this->getOwnedMosaicOptions($form_state) ?? [],
    //     '#required' => TRUE,
    //   ];
    //   return $form;
    // }

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
    //  // メタデータ値を取得
    // $metadata_value = $form_state->getValue('metadata_value');
    
    // // バイト数を取得
    // $byte_length = strlen($metadata_value);

    // // 1024バイトを超える場合はエラーを設定
    // if ($byte_length > 1024) {
    //   $form_state->setErrorByName('metadata_value', $this->t('The metadata value must not exceed 1024 bytes. It is currently %length bytes.', [
    //     '%length' => $byte_length,
    //   ]));
    // }
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

    // mosaicの選択値を取得
    $targetMosaic = $form_state->getValue('mosaic');
    // \Drupal::logger('qls_ss7')->notice('targetMosaic:<pre>@object</pre>', ['@object' => print_r($targetMosaic, TRUE)]);
    // キーと値の設定
    $metadata_key = $form_state->getValue('metadata_key');
    $metadata_value = $form_state->getValue('metadata_value');

    $keyId = Metadata::metadataGenerateKey($metadata_key);
    \Drupal::logger('qls_ss7')->notice('keyId:<pre>@object</pre>', ['@object' => print_r($keyId, TRUE)]);
    $newValue = $metadata_value;

    // 同じキーのメタデータが登録されているか確認
    $metadataInfo = $metaApiInstance->searchMetadataEntries(
      target_id: $targetMosaic,
      // source_address: new UnresolvedAddress($sourceAddress),
      source_address: $sourceAddress,
      scoped_metadata_key: strtoupper(dechex($keyId)), // 16進数の大文字の文字列に変換
      metadata_type: 1,
    );
  

    // $oldValue = hex2bin($metadataInfo['data'][0]['metadata_entry']['value']); //16進エンコードされたバイナリ文字列をデコード
    if (!empty($metadataInfo['data'][0]['metadata_entry']['value'])) {
      $oldValue = hex2bin($metadataInfo['data'][0]['metadata_entry']['value']);
    } else {
        $oldValue = ''; // デフォルト値を設定
    }
    // \Drupal::logger('qls_ss7')->notice('oldValue:<pre>@object</pre>', ['@object' => print_r($oldValue, TRUE)]);
    $updateValue = Metadata::metadataUpdateValue($oldValue, $newValue, true);
    // \Drupal::logger('qls_ss7')->notice('updateValue:<pre>@object</pre>', ['@object' => print_r($updateValue, TRUE)]);
    $targetMosaicID = new UnresolvedMosaicId(hexdec($targetMosaic)); 
    \Drupal::logger('qls_ss7')->notice('targetMosaicID:<pre>@object</pre>', ['@object' => print_r($targetMosaicID, TRUE)]);
    // $tx = new EmbeddedAccountMetadataTransactionV1(
    //   network: $networkType,
    //   signerPublicKey: $sourceKey->publicKey,  // 署名者公開鍵
    //   targetMosaicId: new UnresolvedMosaicId(hexdec($targetMosaic)), // モザイクID
    //   targetAddress: $sourceAddress, // メタデータの対象アドレス
    //   scopedMetadataKey: $keyId,
    //   valueSizeDelta: strlen($newValue) - strlen($oldValue),
    //   value: $updateValue,
    // );
    $tx = new EmbeddedMosaicMetadataTransactionV1(
      network: $networkType,
      signerPublicKey: $sourceKey->publicKey,  // 署名者公開鍵
      targetMosaicId: new UnresolvedMosaicId(hexdec($targetMosaic)),
      targetAddress: $sourceAddress,
      scopedMetadataKey: $keyId,
      valueSizeDelta: strlen($newValue) - strlen($oldValue),
      value: $updateValue,
    );
    // \Drupal::logger('qls_ss7')->notice('tx:<pre>@object</pre>', ['@object' => print_r($tx, TRUE)]);
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
    

   
    // 手数料
    $facade->setMaxFee($aggregateTx, 100);
    // トランザクションの署名
    $sig = $sourceKey->signTransaction($aggregateTx);
    $payload = $facade->attachSignature($aggregateTx, $sig);
  
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
    * 承認
  //  \Drupal::logger('qls_ss7')->notice('tx:<pre>@object</pre>', ['@object' => print_r($tx, TRUE)]);
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
    

   
    // 手数料
    $facade->setMaxFee($aggregateTx, 100);
    // トランザクションの署名
    $sig = $sourceKey->signTransaction($aggregateTx);
    $payload = $facade->attachSignature($aggregateTx, $sig);
  
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

  private function getOwnedMosaicOptions(FormStateInterface $form_state) {
    $source_pvtKey = $form_state->getValue('source_pvtKey');
    if(empty($source_pvtKey) || strlen($source_pvtKey) !== 64) {
      return [];
    }
    // \Drupal::logger('qls_ss7')->notice('384:source_pvtKey:<pre>@object</pre>', ['@object' => print_r($source_pvtKey, TRUE)]);
    $options = [];
    $network_type = $form_state->getValue(['network_type']);
    if($network_type === 'testnet') {
      $node_url = 'http://sym-test-03.opening-line.jp:3000';
    } elseif($network_type === 'mainnet') {
      $node_url = 'http://sym-main-03.opening-line.jp:3000';
    }
    $facade = new SymbolFacade($network_type);
    // $symbol_address = $form_state->getValue(['symbol_address_hidden']);
    $sourceKey = $facade->createAccount(
      new PrivateKey($form_state->getValue('source_pvtKey'))
    );
    $sourceAddress = $sourceKey->address->__tostring();
    // \Drupal::logger('qls_ss7')->notice('393:<pre>@object</pre>', ['@object' => print_r($sourceKey->address->__tostring(), TRUE)]);

    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();

    $accountApiInstance = new AccountRoutesApi($client, $config);
    $account = $accountApiInstance->getAccountInfo($sourceAddress);
    $json_data = json_encode($account, JSON_PRETTY_PRINT);
    $array_data = json_decode($json_data, true);
    // 
    // \Drupal::logger('qls_ss7')->notice('406:<pre>@object</pre>', ['@object' => print_r($json_data, TRUE)]); 
    if ($array_data['account']['mosaics']) {
      foreach ($array_data['account']['mosaics'] as $mosaic) {
        if($mosaic['id']!='72C0212E67A08BCE'){ // testnetのsymbol.xym
          $options[$mosaic['id']] = $mosaic['id'];
        }
      }
    }
    // $options = [
    //   '038D8FA7E70B9725' => '038D8FA7E70B9725',
    //   '072181030CE66350' => '072181030CE66350',
    //   '14282E7F67E84FFA' => '14282E7F67E84FFA',
    //   '26A7B9ED28189EF2' => '26A7B9ED28189EF2',
    //   '2CA841D3265DAFBE' => '2CA841D3265DAFBE',
    //   '36AD93030D234194' => '36AD93030D234194',
    //   '384D6370DA4898A9' => '384D6370DA4898A9',
    //   '45F31337EE87422A' => '45F31337EE87422A',
    //   '5601E87AB77B1F80' => '5601E87AB77B1F80',
    //   '5E37B62006CD4B31' => '5E37B62006CD4B31',
    // ];
    // \Drupal::logger('qls_ss7')->notice('415:<pre>@object</pre>', ['@object' => print_r($options, TRUE)]); 
    return $options;
  }

  /**
   * Callback for the select element.
   *
   * Since the questions_fieldset part of the form has already been built during
   * the AJAX request, we can return only that part of the form to the AJAX
   * request, and it will insert that part into questions-fieldset-wrapper.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function promptCallback(array $form, FormStateInterface $form_state) {
    // まず、出力バッファリングを終了
    if (ob_get_level()) {
      ob_end_clean();
    }

    if (!isset($form['mosaic_fieldset'])) {
      throw new \Exception('mosaic_fieldset is not set in the form.');
    }
     // Log the options returned by getOwnedMosaicOptions
    $options = $this->getOwnedMosaicOptions($form_state);
    // \Drupal::logger('qls_ss7')->notice('Mosaic options: <pre>@data</pre>', ['@data' => print_r($options, TRUE)]);
    \Drupal::logger('qls_ss7')->notice('Mosaic options after AJAX: <pre>@data</pre>', ['@data' => print_r($options, TRUE)]);

    // AJAX リクエスト時に `getOwnedMosaicOptions` を呼び出し
    // $form['mosaic_fieldset']['mosaic'] = [
    //   '#type' => 'select',
    //   '#title' => $this->t('Mosaic'),
    //   '#options' => $options,
    //   '#required' => TRUE,
    //   '#attributes' => [
    //     'id' => 'edit-mosaic',
    //     'name' => 'mosaic',
    //   ],
    // ];
    $form['mosaic_fieldset']['mosaic']['#options'] = $options ?? [];
  
    // 出力デバッグ
  // $response = $form['mosaic_fieldset'];
  // \Drupal::logger('qls_ss7')->notice('Response: <pre>@response</pre>', ['@response' => print_r($response, TRUE)]);

  // return $response;

    return $form['mosaic_fieldset'];
    // if (!isset($form['mosaic_fieldset'])) {
    //   throw new \Exception('mosaic_fieldset is not set in the form.');
    // }
    // try {
    //   // \Drupal::logger('qls_ss7')->notice('436:<pre>@data</pre>', ['@data' => print_r($form['mosaic_fieldset'], TRUE)]);
    //   return $form['mosaic_fieldset'];
    // } catch (\Exception $e) {
    //     \Drupal::logger('qls_ss7')->error('Error in promptCallback: @message', ['@message' => $e->getMessage()]);
    //     throw $e;
    // }
  }
}