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
use SymbolSdk\Symbol\Models\MosaicRestrictionType;
use SymbolSdk\Symbol\Models\MosaicGlobalRestrictionTransactionV1;
use SymbolSdk\Symbol\Models\MosaicAddressRestrictionTransactionV1;
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
use SymbolSdk\Symbol\Metadata;

use Drupal\quicklearning_symbol\Service\AccountService;

/**
 * Provides a form with two steps.
 *
 * This example demonstrates a multistep form with text input elements. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class MosaicAddressRestrictionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mosaic_address_restriction_form';
  }

  // /**
  //  * The AccountService instance.
  //  *
  //  * @var \Drupal\quicklearing_symbol\Service\AccountService
  //  */
  // protected $accountService;

  // /**
  //  * Constructs the form.
  //  *
  //  * @param \Drupal\quicklearing_symbol\Service\AccountService $account_service
  //  *   The account service.
  //  */
  // public function __construct(AccountService $account_service) {
  //   $this->accountService = $account_service;
  // }

  // public static function create(ContainerInterface $container) {
  //   // AccountService をコンストラクタで注入
  //   $form = new static(
  //       $container->get('quicklearning_symbol.account_service')
  //   );
  //   return $form;
  // }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // $form['#attached']['library'][] = 'qls_ss11/account_restriction';
    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('11.2.2 アカウントへのモザイク制限適用'),
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
      '#description' => $this->t('Enter the private key of the mosaic owner account.'),
      '#required' => TRUE,
    ];

    $form['symbol_address'] = [
      '#markup' => '<div id="symbol_address">Symbol Address</div>',
    ];

    $form['target_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target Address'),
      '#required' => TRUE,
    ];

    $form['mosaic_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mosaic ID'),
      '#required' => TRUE,
    ];

    $form['restriction_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Restriction Key'),
      '#required' => TRUE,
    ];
    
    $form['new_restriction_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New Restriction Value'),
      '#required' => TRUE,
      '#description' => $this->t('Integer value'),
    ];
    $form['previous_restriction_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Previous Restriction Value'),
      '#required' => TRUE,
      '#description' => $this->t('Integer value. -1 if not set.'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
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
    // $namespaceIds = IdGenerator::generateNamespacePath('symbol.xym');
    // $namespaceId = new NamespaceId($namespaceIds[count($namespaceIds) - 1]);

    $account_pvtKey = $form_state->getValue('account_pvtKey');
    // \Drupal::logger('qls_ss11')->info('account_pvtKey: @account_pvtKey', ['@account_pvtKey' => $account_pvtKey]);
    $accountKey = $facade->createAccount(new PrivateKey($account_pvtKey));
    // $accountPubKey = $accountKey->publicKey;
    // \Drupal::logger('qls_ss11')->info('accountKey_pubKey: @accountKey', ['@accountKey' => $accountKey->publicKey]);

    $target_address = $form_state->getValue('target_address');
    $targetAddress = new UnresolvedAddress($target_address);

    $mosaic_id = "0x".$form_state->getValue('mosaic_id');
    $mosaicID = new UnresolvedMosaicId($mosaic_id);

    // キーの値と設定
    $restrictionKey = $form_state->getValue('restriction_key');
    $keyId = Metadata::metadataGenerateKey($restrictionKey);

    $previousRestrictionValue = $form_state->getValue('previous_restriction_value');

    $newRestrictionValue = $form_state->getValue('new_restriction_value');

    // グローバルモザイク制限
    $mosaicAddressResTx = new MosaicAddressRestrictionTransactionV1(
      network: $networkType,
      signerPublicKey: $accountKey->publicKey,
      deadline: new Timestamp($facade->now()->addHours(2)),
      mosaicId: $mosaicID,
      restrictionKey: $keyId,
      previousRestrictionValue: $previousRestrictionValue,
      newRestrictionValue: $newRestrictionValue,
      targetAddress: $targetAddress,
    );

    $facade->setMaxFee($mosaicAddressResTx, 100);
    // \Drupal::logger('qls_ss11')->info('tx: @tx', ['@tx' => $tx]);
    // 署名
    $sig = $accountKey->signTransaction($mosaicAddressResTx);
    // \Drupal::logger('qls_ss11')->info('sig: @sig', ['@sig' => $sig]);
    $jsonPayload = $facade->attachSignature($mosaicAddressResTx, $sig);
    // \Drupal::logger('qls_ss11')->info('jsonPayload: @jsonPayload', ['@jsonPayload' => print_r($jsonPayload, TRUE)]);
    try {
      $result = $apiInstance->announceTransaction($jsonPayload);
      $this->messenger()->addMessage($this->t('AccountAddressRestriction Transaction successfully announced: @result', ['@result' => $result]));
    } catch (Exception $e) {
      \Drupal::logger('qls_ss11')->error('Transaction Failed: @message', ['@message' => $e->getMessage()]);
    } 
  }
}