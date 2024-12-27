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
use SymbolSdk\Symbol\Models\Hash256;
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



// use Drupal\quicklearning_symbol\Service\AccountService;

/**
 * Provides a form with two steps.
 *
 * This example demonstrates a multistep form with text input elements. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class OfflineCosigAnnounceTxForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'offline_cosig_announce_tx_form';
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
      '#markup' => $this->t('12.3 アナウンス'),
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



    $form['signed_payload'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Signed Payload'),
      '#description' => $this->t('Enter the signed payload.'),
      '#required' => TRUE,
    ];



    $form['sig_field']['actions']['submit'] = [
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
      // $node_url = 'http://sym-test-03.opening-line.jp:3000';
      $node_url = 'http://sakia.harvestasya.com:3000';
    } elseif ($network_type === 'mainnet') {
      $networkType = new NetworkType(NetworkType::MAINNET);
      $node_url = 'http://sym-main-03.opening-line.jp:3000';
    }

    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();
    $apiInstance = new TransactionRoutesApi($client, $config);
    
    $signed_payload = $form_state->getValue('signed_payload');
    $recreatedTx = TransactionFactory::deserialize(hex2bin($signed_payload));

    // $signTxHash = $form_state->getValue('sign_tx_hash');
    // // $aggregateTx = $facade->getTransaction(new Hash256($signTxHash));
    // $cosig_siner_pubkey_str = $form_state->getValue(['sig_field', 'cosig_siner_pubkey']);
    // $cosig_siner_pubkey = new PublicKey($cosig_siner_pubkey_str);
    // $cosig_siner_signature = $form_state->getValue(['sig_field', 'cosig_siner_signature']);
    // $account_pvtKey = $form_state->getValue(['sig_field', 'account_pvtKey']);
    // $accountKey = $facade->createAccount(new PrivateKey($account_pvtKey));
    // $cosigSignerSignatureStr = $form_state->getValue(['sig_field', 'cosig_siner_signature']);
    // $cosigSignerSignature = new Cosignature($cosigSignerSignatureStr);

    // 連署者の署名を追加
    // $cosignature = new Cosignature();
    // // $signTxHash = $facade->hashTransaction($aggregateTx);
    // $cosignature->parentHash = new Hash256($signTxHash);
    // $cosignature->version = 0;
    // $cosignature->signerPublicKey = $cosig_siner_pubkey;
    // $cosignature->signature = $cosig_siner_signature;
    // array_push($recreatedTx->cosignatures, $cosignature);

    $signedPayload = ["payload" => strtoupper(bin2hex($recreatedTx->serialize()))];
    // echo $signedPayload;
    // \Drupal::logger('qls_ss11')->info('signedPayload: @signedPayload', ['@signedPayload' => $signedPayload]);

    try {
      $result = $apiInstance->announceTransaction($signedPayload);
      $this->messenger()->addMessage($this->t('Transaction successfully announced: @result', ['@result' => $result]));
    } catch (Exception $e) {
      $this->messenger()->addError($this->t('Error: @message', ['@message' => $e->getMessage()])); 
    }

  }
}