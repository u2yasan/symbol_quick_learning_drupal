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
use SymbolSdk\Symbol\Models\NetworkType;


use Drupal\quicklearning_symbol\Service\AccountService;

/**
 * Provides a form with two steps.
 *
 * This example demonstrates a multistep form with text input elements. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class SearchMosaicRestrictionsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_mosaic_restrictions_form';
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
      '#markup' => $this->t('11.2.3 制限状態確認'),
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


    $form['mosaic_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mosaic ID'),
      '#required' => TRUE,
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
    $restrictionAipInstance = new RestrictionMosaicRoutesApi($client, $config);
    // $mosaic_id = "0x".$form_state->getValue('mosaic_id');
    // $mosaicID = new UnresolvedMosaicId($mosaic_id);
    $mosaic_id = $form_state->getValue('mosaic_id'); 
    
    // \Drupal::logger('qls_ss11')->info('jsonPayload: @jsonPayload', ['@jsonPayload' => print_r($jsonPayload, TRUE)]);
    try {
      $result = $restrictionAipInstance->searchMosaicRestrictions(
        mosaic_id: $mosaic_id
      ); 
      $formattedResult = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      $this->messenger()->addMessage($this->t('Mosaic Restrictions: <pre>@result</pre>', ['@result' => $formattedResult]));  
    } catch (Exception $e) {
      \Drupal::logger('qls_ss11')->error('Transaction Failed: @message', ['@message' => $e->getMessage()]);
    } 
  }
}