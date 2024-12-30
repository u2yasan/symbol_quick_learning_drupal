<?php

namespace Drupal\qls_ss9\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Facade\SymbolFacade;

use SymbolRestClient\Configuration;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\MultisigRoutesApi;
use SymbolRestClient\Api\NetworkRoutesApi;
use SymbolSdk\Symbol\Models\EmbeddedTransferTransactionV1;
use SymbolSdk\Symbol\Models\NamespaceRegistrationTransactionV1;
use SymbolSdk\Symbol\Models\AggregateBondedTransactionV2;
use SymbolSdk\Symbol\Models\AggregateCompleteTransactionV2;
use SymbolSdk\Symbol\Models\Hash256;
use SymbolSdk\Symbol\Models\HashLockTransactionV1;
use SymbolSdk\Symbol\Models\PublicKey;
use SymbolSdk\Symbol\Models\Signature;
use SymbolSdk\Symbol\Models\Cosignature;
use SymbolSdk\Symbol\Models\DetachedCosignature;
use SymbolSdk\Symbol\Models\NetworkType;
use SymbolSdk\Symbol\Models\EmbeddedMultisigAccountModificationTransactionV1;
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
class MultiSigForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'multi_sig_form';
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
    $form['#attached']['library'][] = 'qls_ss9/multi_sig';


    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('9.1 マルチシグの登録'),
    ];

    $form['network_type'] = [
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

    $form['multisig_account_pvtKey'] = [
      '#type' => 'password',
      '#title' => $this->t('Account To Be Converted Private Key'),
      '#description' => $this->t('Enter the private key of the account to be converted.'),
      '#required' => TRUE,
    ];

    $form['symbol_address'] = [
      '#markup' => '<div id="symbol_address">Symbol Address</div>',
    ];

    // Gather the number of cosigners in the form already.
    $num_cosigners = $form_state->get('num_cosigners');
    // We have to ensure that there is at least one cosigner field.
    if ($num_cosigners === NULL) {
      $cosigner_field = $form_state->set('num_cosigners', 1);
      $num_cosigners = 1;
    }

    $form['#tree'] = TRUE;
    $form['cosigners_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cosigners'),
      '#prefix' => '<div id="cosigners-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $num_cosigners; $i++) {
      $form['cosigners_fieldset']['cosigner'][$i] = [
        '#type' => 'textfield',
        '#title' => $this->t('Cosigner Address'),
        '#description' => $this->t('Enter the address of the cosigner.')
      ];
    }
    $form['cosigners_fieldset']['actions'] = [
      '#type' => 'actions',
    ];

    $form['cosigners_fieldset']['actions']['add_cosigner'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more cosigner'),
      '#submit' => ['::addOneCosigner'],
      '#ajax' => [
        'callback' => '::addMoreCosignerCallback',
        'wrapper' => 'cosigners-fieldset-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];
    // If there is more than one cosigner, add the remove button.
    if ($num_cosigners > 1) {
      $form['cosigners_fieldset']['actions']['remove_cosigner'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove one cosigner'),
        '#submit' => ['::removeCosignerCallback'],
        '#ajax' => [
          'callback' => '::addMoreCosignerCallback',
          'wrapper' => 'cosigners-fieldset-wrapper',
        ],
        '#limit_validation_errors' => [],
      ];
    }

    $form['mini_approval'] = [
      '#type' => 'textfield',
      '#title' => $this->t('News Min. Approval'),
      '#description' => $this->t('Minimum signatures to sign a transaction or to add a cosigner'),
      '#required' => TRUE,
      '#attributes' => [
        'type' => 'number', // HTML5 の number 属性
        'min' => 0,         // 最小値
        'max' => 25,       // 最大値
        'step' => 1,        // ステップ値
      ],
    ];

    $form['mini_removal'] = [
      '#type' => 'textfield',
      '#title' => $this->t('News Min. Removal'),
      '#description' => $this->t('Minimum signatures required to remove a cosigner'),
      '#required' => TRUE,
      '#attributes' => [
        'type' => 'number', // HTML5 の number 属性
        'min' => 0,         // 最小値
        'max' => 25,       // 最大値
        'step' => 1,        // ステップ値
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function addMoreCosignerCallback(array &$form, FormStateInterface $form_state) {
    return $form['cosigners_fieldset'];
  }

  /**
   * Submit handler for the "add-one-more" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public function addOneCosigner(array &$form, FormStateInterface $form_state) {
    $cosigner_field = $form_state->get('num_cosigners');
    $add_button = $cosigner_field + 1;
    $form_state->set('num_cosigners', $add_button);
    // Since our buildForm() method relies on the value of 'num_cosigners' to
    // generate 'cosigner' form elements, we have to tell the form to rebuild. If we
    // don't do this, the form builder will not call buildForm().
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove one" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeCosignerCallback(array &$form, FormStateInterface $form_state) {
    $cosigner_field = $form_state->get('num_cosigners');
    if ($cosigner_field > 1) {
      $remove_button = $cosigner_field - 1;
      $form_state->set('num_cosigners', $remove_button);
    }
    // Since our buildForm() method relies on the value of 'num_names' to
    // generate 'name' form elements, we have to tell the form to rebuild. If we
    // don't do this, the form builder will not call buildForm().
    $form_state->setRebuild();
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
    $multisigApiInstance = new MultisigRoutesApi($client, $config);

    $multisig_account_pvtKey = $form_state->getValue('multisig_account_pvtKey');
    $multisigKey = $facade->createAccount(new PrivateKey($multisig_account_pvtKey));

    
    $mini_approval = $form_state->getValue('mini_approval');
    $mini_removal = $form_state->getValue('mini_removal');

    // コサイナーの個数を判別し取得
    $cosigners = $form_state->getValue(['cosigners_fieldset', 'cosigner']);
    \Drupal::logger('qls_ss9')->info('cosigners: @cosigners', ['@cosigners' => $cosigners]);
    $cosigner_addresses = [];
    if (is_array($cosigners)) {
        foreach ($cosigners as $cosigner) {
          \Drupal::logger('qls_ss9')->info('cosigner: @cosigner', ['@cosigner' => $cosigner]);

          $cosigner_addresses[] = new UnresolvedAddress($cosigner);
          // \Drupal::logger('qls_ss9')->info('node_url: @node_url', ['@node_url' => $node_url]);
          // $account_info = $this->accountService->getAccountInfo($node_url, $cosigner);
          // \Drupal::logger('qls_ss9')->info('account_info: @account_info', ['@account_info' => $account_info]);
          // if ($account_info) {
          //   // AccountDTOオブジェクトを取得
          //   $accountDTO = $account_info->getAccount();
          //   \Drupal::logger('qls_ss9')->info('accountDTO: @accountDTO', ['@accountDTO' => $accountDTO]);
          //   // 公開鍵を取得
          //   $account_pubKeyStr = $accountDTO->getPublicKey();
          //   \Drupal::logger('qls_ss9')->info('account_pubKeyStr: @account_pubKeyStr', ['@account_pubKeyStr' => $account_pubKeyStr]);
          //   // $recipent_publicKey = new CryptoPublicKey($recipent_publicKey_str);
          //   // $recipent_publicKey = new PublicKey($recipent_publicKey_str);
          //   $account_address = Address::fromRawAddress($account_pubKeyStr, $networkType);
          //   \Drupal::logger('qls_ss9')->info('account_address: @account_address', ['@account_address' => $account_address]);
          // }
          // else {
          //   \Drupal::messenger()->addMessage($this->t('Failed to retrieve account information.'), 'error');
  
          // }
          // $cosigner_addresses[] = $account_address;//公開鍵
        }
    } 

    // \Drupal::logger('qls_ss9')->info('cosigner_addresses: @cosigner_addresses', ['@cosigner_addresses' => $cosigner_addresses]);
    // \Drupal::logger('qls_ss9')->info('cosigner_addresses: @cosigner_addresses', ['@cosigner_addresses' => print_r($cosigner_addresses, true)]);
    // 配列を文字列に変換
    // $addressAdditions = '[' . implode(',', $cosigner_addresses) . ']';
    /**
     * マルチシグの登録
     */
    $multisigTx =  new EmbeddedMultisigAccountModificationTransactionV1(
      network: $networkType,
      signerPublicKey: $multisigKey->publicKey,  // マルチシグ化したいアカウントの公開鍵を指定
      minApprovalDelta: $mini_approval, // minApproval:承認のために必要な最小署名者数増分
      minRemovalDelta: $mini_removal, // minRemoval:除名のために必要な最小署名者数増分
      addressAdditions: $cosigner_addresses,
      addressDeletions: [] // 除名対象アドレスリスト
    );

    // マークルハッシュの算出
    $embeddedTransactions = [$multisigTx];
    $merkleHash = $facade->hashEmbeddedTransactions($embeddedTransactions);

    // アグリゲートトランザクションの作成
    $aggregateTx = new AggregateCompleteTransactionV2(
      network: $networkType,
      signerPublicKey: $multisigKey->publicKey,  // マルチシグ化したいアカウントの公開鍵を指定
      deadline: new Timestamp($facade->now()->addHours(2)),
      transactionsHash: $merkleHash,
      transactions: $embeddedTransactions
    );
    $facade->setMaxFee($aggregateTx, 100, 3);  // 手数料 第二引数に連署者の数:4

    // マルチシグ化したいアカウントによる署名
    $sig = $multisigKey->signTransaction($aggregateTx);
    $payload = $facade->attachSignature($aggregateTx, $sig);

    // このような秘密鍵の扱いはしないように
    // Configサービスからモジュール設定をロード
    // $config = \Drupal::config('qls_ss9.settings');
    // 特定の設定値を取得
    // $coSigPvtKey1 = new PrivateKey($config->get('cosignatory1_pvtKey'));
    // $coSigKey1 = $facade->createAccount($coSigPvtKey1);
    // $coSigPvtKey2 = new PrivateKey($config->get('cosignatory2_pvtKey'));
    // $coSigKey2 = $facade->createAccount($coSigPvtKey2);
    // $coSigPvtKey3 = new PrivateKey($config->get('cosignatory3_pvtKey'));
    // $coSigKey3 = $facade->createAccount($coSigPvtKey3);
    //TAEF3VF4OYCKPSSJQAAN4FS2WAZLC6IKKCE3UIQ
    $coSigPvtKey1 = new PrivateKey('0ABF4B7CA4250A5B741C78058717BA872A4A29297048F3DA55E54A42A28FE07F');
    $coSigKey1 = $facade->createAccount($coSigPvtKey1); 
    //TDT5NHDLPIIE3A7QN7VQYSJNNH7UXO74GS6HJ4Y
    $coSigPvtKey2 = new PrivateKey('13C00A6E532F757BE4575F6F6E5965C2BFD401961B644E11EE7BF36834662048');
    $coSigKey2 = $facade->createAccount($coSigPvtKey2);
    //TA6PXWRS7ELMZM2EL4S64NZPF6RW7EJVH2XAW2Q
    $coSigPvtKey3 = new PrivateKey('EC559FA3FD54DBABACD5F293E6324F53E03EF5B60B1DEB6FAF5F22A7651C8BB3');
    $coSigKey3 = $facade->createAccount($coSigPvtKey3);

    // $coSigPvtKey4 = $config->get('Cosignatory4');
    // $coSigKey4 = $facade->createAccount($coSigPvtKey4);
    // $coSigPvtKey5 = $config->get('Cosignatory5');
    // $coSigKey5 = $facade->createAccount($coSigPvtKey5);

    // 追加・除外対象として指定したアカウントによる連署
    $coSig1 = $facade->cosignTransaction($coSigKey1->keyPair, $aggregateTx);
    array_push($aggregateTx->cosignatures, $coSig1);
    $coSig2 = $facade->cosignTransaction($coSigKey2->keyPair, $aggregateTx);
    array_push($aggregateTx->cosignatures, $coSig2);
    $coSig3 = $facade->cosignTransaction($coSigKey3->keyPair, $aggregateTx);
    array_push($aggregateTx->cosignatures, $coSig3);
    // $coSig4 = $facade->cosignTransaction($coSigKey4->keyPair, $aggregateTx);
    // array_push($aggregateTx->cosignatures, $coSig4);
    // $coSig5 = $facade->cosignTransaction($coSigKey5->keyPair, $aggregateTx);
    // array_push($aggregateTx->cosignatures, $coSig4);

    // アナウンス
    $payload = ["payload" => strtoupper(bin2hex($aggregateTx->serialize()))];
    // \Drupal::logger('qls_ss9')->info('payload: @payload', ['@payload' => $payload]);
    \Drupal::logger('qls_ss9')->info('payload: @payload', ['@payload' => print_r($payload, true)]);
    try {
      $result = $apiInstance->announceTransaction($payload);
      $this->messenger()->addMessage($this->t('Aggregate Transaction successfully announced: @result', ['@result' => $result]));
    } catch (Exception $e) {
      \Drupal::logger('qls_ss9')->error('Transaction Failed: @message', ['@message' => $e->getMessage()]);
      // echo 'Exception when calling TransactionRoutesApi->announceTransaction: ', $e->getMessage(), PHP_EOL;
    }
    // echo 'TxHash' . PHP_EOL;
    // echo $facade->hashTransaction($aggregateTx) . PHP_EOL;
    \Drupal::logger('qls_ss9')->info('TxHash: @TxHash', ['@TxHash' => $facade->hashTransaction($aggregateTx)]);
   
    sleep(35);
    /**
     * 確認
     */
    
    $multisigInfo = $multisigApiInstance->getAccountMultisig($multisigKey->address);
    // echo "===マルチシグ情報===" . PHP_EOL;
    // echo $multisigInfo . PHP_EOL;
    \Drupal::logger('qls_ss9')->info('multisigInfo: @multisigInfo', ['@multisigInfo' => print_r($multisigInfo, true)]);

    /**
     * 連署者アカウントの確認
     */
    $multisigInfo = $multisigApiInstance->getAccountMultisig($coSigKey1->address);
    // echo "===連署者1のマルチシグ情報===" . PHP_EOL;
    // echo $multisigInfo . PHP_EOL;
    \Drupal::logger('qls_ss9')->info('multisigInfo1: @multisigInfo', ['@multisigInfo' => print_r($multisigInfo, true)]);
  
  }


}
