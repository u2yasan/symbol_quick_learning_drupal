<?php

namespace Drupal\qls_ss5\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Facade\SymbolFacade;

use SymbolSdk\Symbol\Models\MosaicSupplyRevocationTransactionV1;
use SymbolSdk\Symbol\Models\MosaicFlags;
use SymbolSdk\Symbol\Models\MosaicNonce;
use SymbolSdk\Symbol\Models\BlockDuration;
use SymbolSdk\Symbol\Models\Amount;
use SymbolSdk\Symbol\Models\UnresolvedMosaicId;
use SymbolSdk\Symbol\Models\UnresolvedMosaic;
use SymbolSdk\Symbol\Models\MosaicSupplyChangeAction;
use SymbolSdk\Symbol\IdGenerator;
use SymbolSdk\Symbol\Models\EmbeddedMosaicDefinitionTransactionV1;
use SymbolSdk\Symbol\Models\EmbeddedMosaicSupplyChangeTransactionV1;
use SymbolSdk\Symbol\Models\EmbeddedTransferTransactionV1;
use SymbolSdk\Symbol\Models\MosaicId;
use SymbolSdk\Symbol\Models\AggregateCompleteTransactionV2;
use SymbolSdk\Symbol\Models\NetworkType;
use SymbolSdk\Symbol\Models\Timestamp;
use SymbolSdk\Symbol\Models\UnresolvedAddress;
use SymbolRestClient\Configuration;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\TransactionStatusRoutesApi;
use SymbolRestClient\Api\AccountRoutesApi;
use SymbolRestClient\Api\MosaicRoutesApi;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class CreateMosaicForm extends FormBase {

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
      '#markup' => $this->t('モザイク生成には作成するモザイクを定義します。'),
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

    $form['account_pvtKey'] = [
      '#type' => 'password',
      '#title' => $this->t('Account Owning Mosaics Private Key'),
      '#description' => $this->t('Enter the private key of the Account.'),
      '#required' => TRUE,
      // '#ajax' => [
      //   'callback' => '::updateSymbolAddress', // Ajaxコールバック関数
      //   'event' => 'blur', // フォーカスが外れたときにトリガー
      //   'wrapper' => 'symbol-address-wrapper', // 書き換え対象の要素ID
      // ],
    ];
    // $form['symbol_address'] = [
    //   // '#title' => $this->t('Account Symbol Address'),
    //   '#markup' => '<div id="symbol-address-wrapper">'.$this->t('Symbol address from private key').'</div>',
    // ];

    $form['supply_units'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Supply Units'),
      '#description' => $this->t('Max: 8,999,999,999'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateSupplyUnits',
        'event' => 'change', // 値が変更されたときにトリガー
        'wrapper' => 'unit-wrapper', // 書き換える要素のID
      ],
    ];

    $form['divisibility'] = [
      '#type' => 'select',
      '#title' => $this->t('Divisibility'),
      '#description' => $this->t('Select a number between 0 and 6.'),
      '#options' => [
        0 => $this->t('0'),
        1 => $this->t('1'),
        2 => $this->t('2'),
        3 => $this->t('3'),
        4 => $this->t('4'),
        5 => $this->t('5'),
        6 => $this->t('6'),
      ],
      '#default_value' => 0, // 初期選択値
      '#required' => TRUE, // 必須フィールド
      '#ajax' => [
        'callback' => '::updateDivisibilityOptions',
        'event' => 'change', // 値が変更されたときにトリガー
        'wrapper' => 'unit-wrapper', // 書き換える要素のID
      ],
    ];

    $form['unit_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'unit-wrapper'],
    ];

    $form['duration'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Duration'),
      '#description' => $this->t('The Duration must be mainnet:10512000/testnet:315360000 or less. 0 means unlimited.'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateDuration',
        'event' => 'change', // 値が変更されたときにトリガー
        'wrapper' => 'duration-wrapper', // 書き換える要素のID
      ],
    ];
    
    $form['duration_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'duration-wrapper'],
    ];

    $form['mosaic_flags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select Flags'),
      '#options' => [
        'supplymutable' => $this->t('Supply Mutable'),
        'transferable' => $this->t('Transferable'),
        'restrectable' => $this->t('Restrectable'),
        'revocable' => $this->t('Revocable'),
      ],
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
      '#value' => $this->t('Greate Mosaic'),
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
    return 'create_mosaic_form';
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
  // public function validateForm(array &$form, FormStateInterface $form_state) {
  //   $title = $form_state->getValue('network_type');
  //   if (strlen($title) < 5) {
  //     // Set an error for the form element with a key of "title".
  //     $form_state->setErrorByName('title', $this->t('The title must be at least 5 characters long.'));
  //   }
  // }
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $pvtKey = $form_state->getValue('account_pvtKey');
    if (strlen($pvtKey) !=  64) {
      // Set an error for the form element with a key of "title".
      $form_state->setErrorByName('account_pvtKey', $this->t('The private key must be 64 characters long.'));
    }
  }
  /**
   * Ajaxコールバック関数
   */
  // public function updateSymbolAddress(array &$form, FormStateInterface $form_state) {
    
  //   // 入力されたプライベートキーを取得
  //   $pvtKey = $form_state->getValue('account_pvtKey');
  //   if (!$pvtKey || strlen($pvtKey) !== 64) {
  //     // エラーメッセージをフォームに追加
  //     $form['symbol_address']['#markup'] = '<div id="symbol-address-wrapper" style="color: red;">'
  //         . $this->t('The private key must be 64 characters long.') . '</div>';

  //   }
  //   else{
  //     $network_type = $form_state->getValue('network_type');
  //     $facade = new SymbolFacade($network_type);
  //     try {
  //       $accountKey = $facade->createAccount(new PrivateKey($pvtKey));
  //       $accountRawAddress = $accountKey->address;
        
        
  //     } catch (\Exception $e) {
  //       \Drupal::logger('qls_ss5')->error('Failed to create account: ' . $e->getMessage());
  //       $accountRawAddress = "Error: Unable to generate address.";
  //     }
  //     // $this->messenger()->addMessage($this->t('RawAddress: @rawAddress', ['@rawAddress' => $accountRawAddress]));
  //     //\Drupal::logger('qls_ss5')->notice('<pre>@object</pre>', ['@object' => print_r($accountRawAddress, TRUE)]);
      
  //     // 動的に更新するフィールドの値を設定
  //     $form['symbol_address']['#markup'] = '<div id="symbol-address-wrapper">' . 'test' . '</div>';

  //   }
  //   return $form['symbol_address'];
    
  // }

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
    // ノードURLを設定
    if ($network_type === 'testnet') {
      $networkType = new NetworkType(NetworkType::TESTNET);
      $node_url = 'http://sym-test-03.opening-line.jp:3000';
    } elseif ($network_type === 'mainnet') {
      $networkType = new NetworkType(NetworkType::MAINNET);
      $node_url = 'http://sym-main-03.opening-line.jp:3000';
    }
    // SymbolFacadeを使って新しいアカウントを作成
    $facade = new SymbolFacade($network_type);

    $account_privatekey = $form_state->getValue('account_pvtKey');
    
    $supply_units = $form_state->getValue('supply_units');
    $divisibility = $form_state->getValue('divisibility');

    $duration = $form_state->getValue('duration');
    $mosaic_flags = $form_state->getValue('mosaic_flags');
    \Drupal::logger('qls_ss5')->notice('<pre>@object</pre>', ['@object' => print_r($mosaic_flags, TRUE)]);  
    $pvtKey = $form_state->getValue('account_pvtKey');
    $accountKey = $facade->createAccount(new PrivateKey($pvtKey));
   
    $blockDuration = new BlockDuration($duration);

    // MosaicFlags の初期化
    $f = MosaicFlags::NONE;
    // 条件に基づいてフラグを加算
    if (!empty($mosaic_flags['supplymutable'])) {
      $f += MosaicFlags::SUPPLY_MUTABLE; // 「供給量変更可能」が選択された場合
    }
    if (!empty($mosaic_flags['transferable'])) {
      $f += MosaicFlags::TRANSFERABLE; // 「譲渡可能」が選択された場合
    }
    if (!empty($mosaic_flags['restrectable'])) {
      $f += MosaicFlags::RESTRICTABLE; // 「制限可能」が選択された場合
    }
    if (!empty($mosaic_flags['revocable'])) {
      $f += MosaicFlags::REVOKABLE; // 「還収可能」が選択された場合
    }
    // MosaicFlagsオブジェクトを作成
    $mosaicFlags = new MosaicFlags($f);
    \Drupal::logger('qls_ss5')->notice('<pre>@object</pre>', ['@object' => print_r($mosaicFlags, TRUE)]); 
        
    $mosaicId = IdGenerator::generateMosaicId($accountKey->address);
    // 桁数のチェック（15桁なら先頭に0を付ける）
    $hexMosaicId = strtoupper(dechex($mosaicId['id']));
    if (strlen($hexMosaicId) === 15) {
      $hexMosaicId ='0' . $hexMosaicId;
    }

    // モザイク定義
    $mosaicDefTx = new EmbeddedMosaicDefinitionTransactionV1(
      network: $networkType,
      signerPublicKey: $accountKey->publicKey, // 署名者公開鍵
      id: new MosaicId($mosaicId['id']), // モザイクID
      divisibility: $divisibility, // 分割可能性
      duration: $blockDuration, //duration:有効期限
      nonce: new MosaicNonce($mosaicId['nonce']),
      flags: $mosaicFlags,
    );

    //モザイク変更
    $mosaicChangeTx = new EmbeddedMosaicSupplyChangeTransactionV1(
      network: $networkType,
      signerPublicKey: $accountKey->publicKey, // 署名者公開鍵
      mosaicId: new UnresolvedMosaicId($mosaicId['id']),
      delta: new Amount($supply_units),
      action: new MosaicSupplyChangeAction(MosaicSupplyChangeAction::INCREASE),
    );
    // ※AggregateTransaction のInner Transaction クラスは全てEmbedded がつきます。
    // supplyMutable:false の場合、全モザイクが発行者にある場合だけ数量の変更が可
    // 能です。divisibility > 0 の場合は、最小単位を1 として整数値で定義してください。
    // （divisibility:2 で1.00 作成したい場合は100 と指定）
    // MosaicSupplyChangeAction は以下の通りです。
    // {0:'DECREASE', 1:'INCREASE'}
    // 増やしたい場合はIncrease を指定します。上記2 つのトランザクションをまとめてア
    // グリゲートトランザクションを作成します。

    // マークルハッシュの算出
    $embeddedTransactions = [$mosaicDefTx, $mosaicChangeTx];
    $merkleHash = $facade->hashEmbeddedTransactions($embeddedTransactions);
    // アグリゲートTx作成
    $aggregateTx = new AggregateCompleteTransactionV2(
      network: $networkType,
      signerPublicKey: $accountKey->publicKey,
      deadline: new Timestamp($facade->now()->addHours(2)),
      transactionsHash: $merkleHash,
      transactions: $embeddedTransactions
    );
    $facade->setMaxFee($aggregateTx, 100); // 手数料
    // 署名
    $sig = $accountKey->signTransaction($aggregateTx);
    $payload = $facade->attachSignature($aggregateTx, $sig);

    // トランザクションの送信
    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();
    $apiInstance = new TransactionRoutesApi($client, $config);

    try {
      $result = $apiInstance->announceTransaction($payload);
      // return $result;
      $this->messenger()->addMessage($this->t('Transaction successfully announced: @result', ['@result' => $result]));
    } catch (\Exception $e) {
      \Drupal::logger('qls_ss5')->error('トランザクションの発行中にエラーが発生しました: @message', ['@message' => $e->getMessage()]);
      // throw $e;
    }
    $this->messenger()->addMessage($this->t('You specified a network_type of %network_type.', ['%network_type' => $network_type]));

  }

}
