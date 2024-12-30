<?php

namespace Drupal\qls_ss12\Form;

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
use SymbolSdk\Symbol\Models\Hash256 as SymbolHash256;
use SymbolSdk\CryptoTypes\Hash256;
use SymbolSdk\Symbol\Models\HashLockTransactionV1;
use SymbolSdk\Symbol\Models\PublicKey;
// use SymbolSdk\Symbol\Models\Signature;
use SymbolSdk\CryptoTypes\Signature;
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
use SymbolSdk\Symbol\Models\TransactionFactory;
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
class OfflineCosigTxForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'offline_cosig_tx_form';
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
      '#markup' => $this->t('12.2 連署'),
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

    $form['#tree'] = TRUE;

    $form['payload_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Verify Payload'),
      '#prefix' => '<div id="payload-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['payload_fieldset']['payload'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Payload'),
      '#description' => $this->t('Enter the payload.'),
      '#required' => TRUE,
    ];

    $form['payload_fieldset']['verify_signature'] = [
      '#type' => 'button',
      '#value' => $this->t('Verify Signature'),
      '#description' => $this->t('Verify the signature.'),
      '#ajax' => [
        'callback' => '::verifySignatureCallback',
        'wrapper' => 'sig-field-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['sign_tx_hash'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sign Tx Hash'),
      '#description' => $this->t('Enter the aggregate tx hash.'),
      '#required' => TRUE,
    ];

    $form['sig_field'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'sig-field-wrapper'],
    ];
    // $sig_verified = $form_state->getValue('sig_verified');
    // if ($sig_verified) {
      $form['sig_field']['account_pvtKey'] = [
        '#type' => 'password',
        '#title' => $this->t('Cosigning Account Private Key'),
        '#description' => $this->t('Enter the private key of the account.'),
        '#required' => TRUE,
      ];

      $form['sig_field']['symbol_address'] = [
        '#markup' => '<div id="symbol_address">Symbol Address</div>',
      ];

      $form['sig_field']['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ];
    // }

    return $form;
  }

  
  public function verifySignatureCallback(array &$form, FormStateInterface $form_state) {
    $network_type = $form_state->getValue('network_type');
    $facade = new SymbolFacade($network_type);
  
    $payload = $form_state->getValue(['payload_fieldset', 'payload']);
    // \Drupal::logger('qls_ss11')->info('payload: @payload', ['@payload' => $payload]);
    $tx = TransactionFactory::deserialize(hex2bin($payload)); // バイナリデータにする
    // \Drupal::logger('qls_ss11')->info('tx: @tx', ['@tx' => print_r($tx, TRUE)]);
    $signature = new Signature($tx->signature);
    $res = $facade->verifyTransaction($tx, $signature);
    \Drupal::logger('qls_ss11')->info('verify: @res', ['@res' => $res]);
    if ($res) {
      $this->messenger()->addMessage($this->t('Verify: Success'));
      $form_state->setValue('sig_verified', TRUE);
    } else {
      $this->messenger()->addMessage($this->t('Verify: Failed'));
    }
  
    $form_state->setRebuild();
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
    

    $payload = $form_state->getValue(['payload_fieldset', 'payload']);
    $tx = TransactionFactory::deserialize(hex2bin($payload)); // バイナリデータにする

    $account_pvtKey = $form_state->getValue(['sig_field','account_pvtKey']);
    // \Drupal::logger('qls_ss11')->info('account_pvtKey: @account_pvtKey', ['@account_pvtKey' => $account_pvtKey]);
    $accountKey = $facade->createAccount(new PrivateKey($account_pvtKey));
    // $accountPubKey = $accountKey->publicKey;
    $cosignature = $facade->cosignTransaction($accountKey->keyPair, $tx);
    // $cosig_siner_pubkey = $cosignature->signerPublicKey;
    $signedTxSignature = $cosignature->signature;

    $signTxHash = $form_state->getValue('sign_tx_hash');

    // $recreatedTx = TransactionFactory::deserialize(hex2bin($signedPayload['payload']));
    // 連署者の署名を追加
    $cosig = new Cosignature();
    // $signTxHash = $facade->hashTransaction($aggregateTx);
    $cosig->parentHash = new SymbolHash256($signTxHash);
    $cosig->version = 0;
    $cosig->signerPublicKey = $cosignature->signerPublicKey;
    $cosig->signature = $signedTxSignature;
    array_push($tx->cosignatures, $cosig);

    $signedPayload = ["payload" => strtoupper(bin2hex($tx->serialize()))];
    \Drupal::logger('qls_ss11')->info('signedPayload: @signedPayload', ['@signedPayload' => print_r($signedPayload, TRUE)]);
    $this->messenger()->addMessage($this->t('signedPayload: @signedPayload', ['@signedPayload' => $signedPayload['payload']])); 

    // \Drupal::logger('qls_ss11')->info('signedTxSignature: @signedTxSignature', ['@signedTxSignature' => $signedTxSignature]);
    // $this->messenger()->addMessage($this->t('signedTxSignature: <pre>@signedTxSignature</pre>', ['@signedTxSignature' => $signedTxSignature]));
    // $signedTxSignerPublicKey = $cosignature->signerPublicKey;
    // \Drupal::logger('qls_ss11')->info('signedTxSignerPublicKey: @signedTxSignerPublicKey', ['@signedTxSignerPublicKey' => $signedTxSignerPublicKey]);
    // $this->messenger()->addMessage($this->t('signedTxSignerPublicKey: <pre>@signedTxSignerPublicKey</pre>', ['@signedTxSignerPublicKey' => $signedTxSignerPublicKey]));

  }
}