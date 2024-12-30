<?php

namespace Drupal\qls_ss11\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Facade\SymbolFacade;

use SymbolRestClient\Configuration;
use SymbolRestClient\Api\RestrictionMosaicRoutesApi;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\MultisigRoutesApi;
use SymbolRestClient\Api\NetworkRoutesApi;
use SymbolSdk\Symbol\Models\AccountAddressRestrictionTransactionV1;
use SymbolSdk\Symbol\Models\AccountRestrictionFlags;
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
class AccountAddressRestrictionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'account_address_restriction_form';
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
    // $form['#attached']['library'][] = 'qls_ss11/account_restriction';
    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('11.1.1 指定アドレスからの受信制限・指定アドレスへの送信制限'),
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

    $form['account_pvtKey'] = [
      '#type' => 'password',
      '#title' => $this->t('Account Private Key'),
      '#description' => $this->t('Enter the private key of the account.'),
      '#required' => TRUE,
    ];

    $form['symbol_address'] = [
      '#markup' => '<div id="symbol_address">Symbol Address</div>',
    ];

    $form['restriction_flag'] = [
      '#type' => 'select',
      '#title' => $this->t('Restriction Flag'),
      '#options' => [
        '0' => $this->t('Allow Incoming Address'),
        '1' => $this->t('Allow Outgoing Address'),
        '2' => $this->t('Block Incoming Address'),
        '3' => $this->t('Block Outgoing Address'),
        ],
      '#required' => TRUE,
    ];
    
    // $form['restrictionAdditions'] = [
    //   '#type' => 'fieldset',
    //   '#title' => $this->t('Restriction Additions'),
    //   '#prefix' => '<div id="restrictionAdditions-fieldset-wrapper">',
    //   '#suffix' => '</div>',
    // ];

    // Gather the number of cosigners in the form already.
    $num_additions = $form_state->get('num_additions');
    // We have to ensure that there is at least one cosigner field.
    if ($num_additions === NULL) {
      $addition_field = $form_state->set('num_additions', 1);
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
        '#title' => $this->t('Addition Address'),
        '#description' => $this->t('Enter the address of the addition.')
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
    if ($num_additions > 1) {
      $form['additions_fieldset']['actions']['remove_addition'] = [
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
        '#title' => $this->t('Deletion Address'),
        '#description' => $this->t('Enter the address of the deletion.')
      ];
    }
    $form['deletions_fieldset']['actions'] = [
      '#type' => 'actions',
    ];

    $form['deletions_fieldset']['actions']['add_deletion'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more deletion'),
      '#submit' => ['::addOneDeletion'],
      '#ajax' => [
        'callback' => '::addMoreDeletionCallback',
        'wrapper' => 'deletions-fieldset-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];
    if ($num_deletions > 1) {
      $form['deletions_fieldset']['actions']['remove_deletion'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove one deletion'),
        '#submit' => ['::removeDeletionCallback'],
        '#ajax' => [
          'callback' => '::addMoreDeletionCallback',
          'wrapper' => 'deletions-fieldset-wrapper',
        ],
        '#limit_validation_errors' => [],
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  
  public function addMoreAdditionCallback(array &$form, FormStateInterface $form_state) {
    return $form['additions_fieldset'];
  }
  public function addOneAddition(array &$form, FormStateInterface $form_state) {
    $addition_field = $form_state->get('num_additions');
    $add_button = $addition_field + 1;
    $form_state->set('num_additions', $add_button);
    $form_state->setRebuild();
  }
  public function removeAdditionCallback(array &$form, FormStateInterface $form_state) {
    $addition_field = $form_state->get('num_additions');
    if ($addition_field > 1) {
      $remove_button = $addition_field - 1;
      $form_state->set('num_additions', $remove_button);
    }
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
    // $namespaceIds = IdGenerator::generateNamespacePath('symbol.xym');
    // $namespaceId = new NamespaceId($namespaceIds[count($namespaceIds) - 1]);

    $account_pvtKey = $form_state->getValue('account_pvtKey');
    // \Drupal::logger('qls_ss11')->info('account_pvtKey: @account_pvtKey', ['@account_pvtKey' => $account_pvtKey]);
    $accountKey = $facade->createAccount(new PrivateKey($account_pvtKey));
    $accountPubKey = $accountKey->publicKey;
    // \Drupal::logger('qls_ss11')->info('accountKey_pubKey: @accountKey', ['@accountKey' => $accountKey->publicKey]);

    $restriction_flag = $form_state->getValue('restriction_flag');
    \Drupal::logger('qls_ss11')->info('restriction_flag: @restriction_flag', ['@restriction_flag' => $restriction_flag]);
    switch ($restriction_flag) {
      case 0: // AllowIncomingAddress：指定アドレスからのみ受信許可
        $f = AccountRestrictionFlags::ADDRESS;
        break;
      case 1: // AllowOutgoingAddress：指定アドレス宛のみ送信許可
        $f = AccountRestrictionFlags::ADDRESS;
        $f += AccountRestrictionFlags::OUTGOING;
        break;
      case 2: // BlockIncomingAddress：指定アドレスからの受信受拒否
        $f = AccountRestrictionFlags::ADDRESS;
        $f += AccountRestrictionFlags::BLOCK;
        break;
      case 3: // BlockOutgoingAddress：指定アドレス宛への送信禁止
        $f = AccountRestrictionFlags::ADDRESS;
        $f += AccountRestrictionFlags::BLOCK;
        $f += AccountRestrictionFlags::OUTGOING;
        break;
    }
    $flags = new AccountRestrictionFlags($f);
    // \Drupal::logger('qls_ss11')->info('flags: @flags', ['@flags' => $flags]);
    
    $additions = $form_state->getValue(['additions_fieldset', 'addition']);
    // \Drupal::logger('qls_ss11')->info('additions: @additions', ['@additions' => $additions]);
    $addition_addresses = [];
    if (is_array($additions)) {
        foreach ($additions as $addition) {
          // \Drupal::logger('qls_ss11')->info('addition: @addition', ['@addition' => $addition]);
          if($addition !== '') {
            $addition_addresses[] = new UnresolvedAddress($addition);
          }
        }
    }
    $deletions = $form_state->getValue(['deletions_fieldset', 'deletion']);
    // \Drupal::logger('qls_ss11')->info('deletions: @deletions', ['@deletions' => $deletions]);
    $deletion_addresses = [];
    if (is_array($deletions)) {
        foreach ($deletions as $deletion) {
          // \Drupal::logger('qls_ss11')->info('deletion: @deletion', ['@deletion' => $deletion]);
          if($deletion !== '') {
            $deletion_addresses[] = new UnresolvedAddress($deletion);
          }
        }
    }
    // $drupal_config = \Drupal::config('qls_ss11.settings');
    // $restrictAccountKey = new PrivateKey($drupal_config->get('restrict_account_pvtKey'));
    // \Drupal::logger('qls_ss11')->info('deletion_addresses: @deletion_addresses', ['@deletion_addresses' => $deletion_addresses]);
    // アドレス制限設定Tx作成
    $tx = new AccountAddressRestrictionTransactionV1(
      network: $networkType,
      signerPublicKey: $accountPubKey,
      deadline: new Timestamp($facade->now()->addHours(2)),
      restrictionFlags: $flags, // 制限フラグ
      restrictionAdditions:$addition_addresses, // 設定アドレス
      restrictionDeletions:$deletion_addresses // 削除アドレス
    );
    $facade->setMaxFee($tx, 100);
    // \Drupal::logger('qls_ss11')->info('tx: @tx', ['@tx' => $tx]);
    // 署名
    $sig = $accountKey->signTransaction($tx);
    // \Drupal::logger('qls_ss11')->info('sig: @sig', ['@sig' => $sig]);
    $jsonPayload = $facade->attachSignature($tx, $sig);
    try {
      $result = $apiInstance->announceTransaction($jsonPayload);
      $this->messenger()->addMessage($this->t('AccountAddressRestriction Transaction successfully announced: @result', ['@result' => $result]));
    } catch (Exception $e) {
      \Drupal::logger('qls_ss11')->error('Transaction Failed: @message', ['@message' => $e->getMessage()]);
    } 
  }
}