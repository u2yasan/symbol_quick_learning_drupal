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
class MultiSigCosigForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'multi_sig_cosig_form';
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
    $form['#attached']['library'][] = 'qls_ss9/multi_sig_cosig';


    $form['description'] = [
      '#type' => 'item',
      '#title' => $this->t('連署'),
    ];

    $form['network_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Network Type'),
      '#options' => [
        'testnet' => $this->t('Testnet'),
        'mainnet' => $this->t('Mainnet'),
      ],
      '#default_value' => 'testnet',
      '#required' => TRUE,
    ];

    $form['cosigner_pvtKey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Co-signer Private Key'),
      '#description' => $this->t('Enter the private key of the co-signer.'),
      '#required' => TRUE,
    ];

    $form['aggregateTxHash'] = [
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
    /**
     * 連署
     */
    // トランザクションの取得
    $network_type = $form_state->getValue('network_type');
    $facade = new SymbolFacade($network_type);
    $aggregateTxHash = $form_state->getValue('aggregateTxHash');
    $cosigner_pvtKey = $form_state->getValue('cosigner_pvtKey');
    $cosignerKey = $facade->createAccount(new PrivateKey($cosigner_pvtKey));
   
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
    // 連署者の連署
    $signTxHash = new Hash256($txInfo->getMeta()->getHash());
    $signature = new Signature($cosignerKey->keyPair->sign($signTxHash->binaryData));
    $body = [
        'parentHash' => $signTxHash->__toString(),
        'signature' => $signature->__toString(),
        'signerPublicKey' => $cosignerKey->publicKey->__toString(),
        'version' => '0'
    ];
    //アナウンス
    try {
      $result = $apiInstance->announceCosignatureTransaction($body);
      // echo $result . PHP_EOL;
      $this->messenger()->addMessage($this->t('Cosignature successfully announced: @result', ['@result' => $result]));

    } catch (Exception $e) {
      \Drupal::logger('qls_ss9')->error('Transaction Failed: @message', ['@message' => $e->getMessage()]);
      // echo 'Exception when calling TransactionRoutesApi->announceTransaction: ', $e->getMessage(), PHP_EOL;
    }
    
  }


}
