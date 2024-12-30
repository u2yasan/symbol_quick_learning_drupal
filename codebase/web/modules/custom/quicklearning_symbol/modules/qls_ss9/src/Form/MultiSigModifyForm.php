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
class MultiSigModifyForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'multi_sig_modify_form';
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
    $form['#attached']['library'][] = 'qls_ss9/multi_sig_modify';


    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('9.5 マルチシグ構成変更'),
    ];

    $form['network_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Network Type'),
      '#description' => $this->t('Select either testnet or mainnet'),
      '#options' => [
        'testnet' => $this->t('Testnet'),
        'mainnet' => $this->t('Mainnet'),
      ],
      '#default_value' => 'testnet',
      '#required' => TRUE,
    ];

    $form['multisig_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Multisig Address'),
      '#description' => $this->t('Enter the address of the multisig account.'),
      '#required' => TRUE,
    ];

    $form['originator_pvtKey'] = [
      '#type' => 'password',
      '#title' => $this->t('Originator Private Key'),
      '#description' => $this->t('Enter the private key of the originator.'),
      '#required' => TRUE,
    ];

    $form['symbol_address'] = [
      '#markup' => '<div id="symbol_address">Symbol Address</div>',
    ];

    // Gather the number of cosigners in the form already.
    $num_additions = $form_state->get('num_additions');
    // We have to ensure that there is at least one cosigner field.
    if ($num_additions === NULL) {
      $addion_field = $form_state->set('num_additions', 1);
      $num_additions = 1;
    }

    $form['#tree'] = TRUE;
    $form['additions_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Additions'),
      '#prefix' => '<div id="additions-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];
    for ($i = 0; $i < $num_additions; $i++) {
      $form['additions_fieldset']['addition'][$i] = [
        '#type' => 'textfield',
        '#title' => $this->t('additions Address @i', ['@i' => $i + 1]),
        '#description' => $this->t('Enter the Address of the addition.')
      ];
    }
    $form['additions_fieldset']['actions'] = [
      '#type' => 'actions',
    ];

    $form['additions_fieldset']['actions']['add_addition'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more addition'),
      '#submit' => ['::addOneAddition'],
      '#ajax' => [
        'callback' => '::addMoreAdditionCallback',
        'wrapper' => 'additions-fieldset-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];
    // If there is more than one cosigner, add the remove button.
    if ($num_additions > 1) {
      $form['additions_fieldset']['actions']['remove_additions'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove one addition'),
        '#submit' => ['::removeAdditionCallback'],
        '#ajax' => [
          'callback' => '::addMoreAdditionCallback',
          'wrapper' => 'additions-fieldset-wrapper',
        ],
        '#limit_validation_errors' => [],
      ];
    }


    $num_deletions = $form_state->get('num_deletions');
    if ($num_deletions === NULL) {
      $deletion_field = $form_state->set('num_deletions', 1);
      $num_deletions = 1;
    }  
    $form['deletions_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Deletions'),
      '#prefix' => '<div id="deletions-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];
    for ($i = 0; $i < $num_deletions; $i++) {
      $form['deletions_fieldset']['deletion'][$i] = [
        '#type' => 'textfield',
        '#title' => $this->t('deletions Address @i', ['@i' => $i + 1]),
        '#description' => $this->t('Enter the Address of the deletion.')
      ];
    }
    $form['deletions_fieldset']['actions'] = [
      '#type' => 'actions',
    ];
    $form['deletions_fieldset']['actions']['add_deletion'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more deletion'),
      '#submit' => ['::addOnedeletion'],
      '#ajax' => [
        'callback' => '::addMoreDeletionCallback',
        'wrapper' => 'deletions-fieldset-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];
    // If there is more than one cosigner, add the remove button.
    if ($num_deletions > 1) {
      $form['deletions_fieldset']['actions']['remove_deletions'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove one deletion'),
        '#submit' => ['::removeDeletionCallback'],
        '#ajax' => [
          'callback' => '::addMoredeletionCallback',
          'wrapper' => 'deletions-fieldset-wrapper',
        ],
        '#limit_validation_errors' => [],
      ];
    }

    $form['mini_approval'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Min. Approval'),
      '#required' => TRUE,
      '#attributes' => [
        'type' => 'number', // HTML5 の number 属性
        'min' => -25,         // 最小値
        'max' => 25,       // 最大値
        'step' => 1,        // ステップ値
      ],
    ];

    $form['mini_removal'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Min. Removal'),
      '#required' => TRUE,
      '#attributes' => [
        'type' => 'number', // HTML5 の number 属性
        'min' => -25,         // 最小値
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
  public function addMoreAdditionCallback(array &$form, FormStateInterface $form_state) {
    return $form['additions_fieldset'];
  }

  /**
   * Submit handler for the "add-one-more" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public function addOneAddition(array &$form, FormStateInterface $form_state) {
    $addition_field = $form_state->get('num_additions');
    $add_button = $addition_field + 1;
    $form_state->set('num_additions', $add_button);
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
  public function removeAdditionCallback(array &$form, FormStateInterface $form_state) {
    $addition_field = $form_state->get('num_additions');
    if ($addition_field > 1) {
      $remove_button = $addition_field - 1;
      $form_state->set('num_additions', $remove_button);
    }
    // Since our buildForm() method relies on the value of 'num_names' to
    // generate 'name' form elements, we have to tell the form to rebuild. If we
    // don't do this, the form builder will not call buildForm().
    $form_state->setRebuild();
  }

  public function addMoreDeletionCallback(array &$form, FormStateInterface $form_state) {
    return $form['deletions_fieldset'];
  }
  public function addOneDeletion(array &$form, FormStateInterface $form_state) {
    $deletion_field = $form_state->get('num_deletions');
    $add_button = $deletion_field + 1;
    $form_state->set('num_deletions', $add_button);
    $form_state->setRebuild();
  }
  public function removeDeletionCallback(array &$form, FormStateInterface $form_state) {
    $deletion_field = $form_state->get('num_deletions');
    if ($deletion_field > 1) {
      $remove_button = $deletion_field - 1;
      $form_state->set('num_deletions', $remove_button);
    }
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
    // $multisigApiInstance = new MultisigRoutesApi($client, $config);

    $multisig_address = $form_state->getValue('multisig_address');
    // \Drupal::logger('qls_ss9')->info('multisig_address: @multisig_address', ['@multisig_address' => $multisig_address]);
    $account_info = $this->accountService->getAccountInfo($node_url, $multisig_address);
    if ($account_info) {
      $accountDTO = $account_info->getAccount();
      $msaccount_pubKeyStr = $accountDTO->getPublicKey();
      $msaccount_pubKey = new PublicKey($msaccount_pubKeyStr);
    } else {
      $this->messenger()->addMessage($this->t('Multisig Account not found.'));
      return;
    }  

    $originator_pvtKey = $form_state->getValue('originator_pvtKey');
    $originatorKey = $facade->createAccount(new PrivateKey($originator_pvtKey));


    $additions = $form_state->getValue(['additions_fieldset', 'addition']);
    \Drupal::logger('qls_ss9')->info('additions: @additions', ['@additions' => print_r($additions, true)]);
    $deletions = $form_state->getValue(['deletions_fieldset', 'deletion']);
    $mini_approval = $form_state->getValue('mini_approval');
    $mini_removal = $form_state->getValue('mini_removal');

    // 空の要素を除外するために配列をフィルタリング
    $additions = array_filter($additions, function($value) {
      return !empty($value);
    });
    $deletions = array_filter($deletions, function($value) {
      return !empty($value);
    });

    $additions_addresses = [];
    if (is_array($additions) && count($additions) > 0) {
      foreach ($additions as $addition) {
        \Drupal::logger('qls_ss9')->info('addition: @addition', ['@addition' => $addition]);
        $additions_addresses[] = new UnresolvedAddress($addition);
      }
    }
    \Drupal::logger('qls_ss9')->info('additions_addresses: @additions_addresses', ['@additions_addresses' => print_r($additions_addresses, true)]); 
    $deletions_addresses = [];
    if (is_array($deletions) && count($deletions) > 0) {
      foreach ($deletions as $deletion) {
        $deletions_addresses[] = new UnresolvedAddress($deletion);
      }
    } 
    \Drupal::logger('qls_ss9')->info('deletions_addresses: @deletions_addresses', ['@deletions_addresses' => print_r($deletions_addresses, true)]);

    /**
     * マルチシグの構成の変更
     */
    $multisigTx = new EmbeddedMultisigAccountModificationTransactionV1(
      network: $networkType,
      signerPublicKey: $msaccount_pubKey,  // マルチシグアカウントの公開鍵を指定
      minApprovalDelta: $mini_approval, // minApproval:承認のために必要な最小署名者数増分
      minRemovalDelta: $mini_removal, // minRemoval:除名のために必要な最小署名者数増分
      addressAdditions: $additions_addresses, //追加対象アドレスリスト
      addressDeletions: $deletions_addresses // 除名対象アドレスリスト
    );
    // マークルハッシュの算出
    $embeddedTransactions = [$multisigTx];
    $merkleHash = $facade->hashEmbeddedTransactions($embeddedTransactions);

    // アグリゲートボンデッドTx作成
    $aggregateTx = new AggregateBondedTransactionV2(
      network: $networkType,
      signerPublicKey: $originatorKey->publicKey,  // 起案者アカウントの公開鍵
      deadline: new Timestamp($facade->now()->addHours(2)),
      transactionsHash: $merkleHash,
      transactions: $embeddedTransactions
    );
    $facade->setMaxFee($aggregateTx, 100, 3);  // 手数料の設定 ここは連署名者数？

    // 起案者アカウントによる署名
    $sig = $originatorKey->signTransaction($aggregateTx);
    $payload = $facade->attachSignature($aggregateTx, $sig);

    $namespaceIds = IdGenerator::generateNamespacePath('symbol.xym');
    $namespaceId = new NamespaceId($namespaceIds[count($namespaceIds) - 1]);
    // ハッシュロックTx作成
    $hashLockTx = new HashLockTransactionV1(
      signerPublicKey: $originatorKey->publicKey,  // 署名者公開鍵
      network: $networkType,
      deadline: new Timestamp($facade->now()->addHours(2)), // 有効期限
      duration: new BlockDuration(480), // 有効期限
      hash: new Hash256($facade->hashTransaction($aggregateTx)), // ペイロードのハッシュ
      mosaic: new UnresolvedMosaic(
        mosaicId: new UnresolvedMosaicId($namespaceId), // モザイクID
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
    try {
      $result = $apiInstance->announceTransaction($hashLockJsonPayload);
      $this->messenger()->addMessage($this->t('HashLock Transaction successfully announced: @result', ['@result' => $result]));

      // echo $result . PHP_EOL;
    } catch (Exception $e) {
      \Drupal::logger('qls_ss9')->error('Transaction Failed: @message', ['@message' => $e->getMessage()]);
      // echo 'Exception when calling TransactionRoutesApi->announceTransaction: ', $e->getMessage(), PHP_EOL;
    }
    sleep(40);

    /**
     * アグリゲートボンデットTxをアナウンス
     */
    try {
      $result = $apiInstance->announcePartialTransaction($payload);
      $this->messenger()->addMessage($this->t('Multisig Aggregate Bounded Transaction successfully announced: @result', ['@result' => $result]));
    } catch (Exception $e) {
      \Drupal::logger('qls_ss9')->error('Transaction Failed: @message', ['@message' => $e->getMessage()]);
    }
    $this->messenger()->addMessage($this->t('Aggregated Bounded TxHash: @TxHash',['@TxHash' => $facade->hashTransaction($aggregateTx)]));

  }
}
