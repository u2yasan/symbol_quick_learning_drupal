<?php

namespace Drupal\qls_ss9\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use SymbolSdk\Facade\SymbolFacade;

use SymbolRestClient\Configuration;
use SymbolRestClient\Api\TransactionRoutesApi;

use SymbolSdk\Symbol\Models\NetworkType;

/**
 * Provides a form with two steps.
 *
 * This example demonstrates a multistep form with text input elements. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class MultiSigConfirmForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'multi_sig_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'qls_ss9/multi_sig_confirm';

    $form['description'] = [
      '#type' => 'item',
      '#title' => $this->t('9.4 マルチシグ送信の確認'),
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

    $form['aggregateTxHash'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Aggregate Transaction Hash'),
      '#description' => $this->t('Enter the hash of the aggregate transaction hash.'),
      '#required' => TRUE,
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

    //アナウンス
    try {
      $txInfo = $apiInstance->getConfirmedTransaction($aggregateTxHash);
      $txInfoArray = json_decode(json_encode($txInfo), true); // オブジェクトを配列に変換
      $prettyJson = json_encode($txInfoArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); // 整形されたJSON文字列を生成
      $this->messenger()->addMessage($this->t('Tx info of the Aggregated Confirmed Tx: <pre>@result</pre>', ['@result' => $prettyJson]));

    } catch (Exception $e) {
      \Drupal::logger('qls_ss9')->error('Transaction Failed: @message', ['@message' => $e->getMessage()]);
    }
    
  }
}
