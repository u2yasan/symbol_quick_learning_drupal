<?php

namespace Drupal\qls_ss5\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use SymbolSdk\Symbol\Models\NetworkType;
use SymbolRestClient\Configuration;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Model\TransactionInfoDTOMeta;
use SymbolRestClient\Model\UnresolvedMosaic;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class ConfirmTransactionForm extends FormBase {

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
    $form['#prefix'] = '<div id="transaction-info-wrapper">';
    $form['#suffix'] = '</div>';

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('5.2.1 送信確認'),
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

    $form['transaction_hash'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Transaction Hash'),
      '#description' => $this->t('Transfer Transaction Hash'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button that handles the submission of the form.
    // Submit button
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#ajax' => [
        'callback' => '::ajaxSubmitCallback',
        'wrapper' => 'transaction-info-wrapper',
      ],
    ];

    // // フォーム送信後のデータを取得
    // $meta = $form_state->get('$meta');
    // $transaction = $form_state->get('$transaction');

     // Placeholder for displaying results
     $form['output'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'transaction-info-output'],
      '#markup' => '',
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
    return 'confirm_transaction_form';
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
    $txHash = $form_state->getValue('transaction_hash');
    if (strlen($txHash) !=  64) {
      // Set an error for the form element with a key of "title".
      $form_state->setErrorByName('transaction_hash', $this->t('Transaction Hash key must be 64 characters long.'));
    }
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // AJAX only: No full submit processing required.
  }

  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state) {
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

    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();

    try{
      $apiInstance = new TransactionRoutesApi($client, $config);
      $txInfo = $apiInstance->getConfirmedTransaction($form_state->getValue('transaction_hash'));
    }
    catch (\Exception $e) {
      \Drupal::logger('qls_ss5')->error('Failed to create api instance: ' . $e->getMessage());
    }
   
    \Drupal::logger('qls_ss5')->notice('txInfo:<pre>@object</pre>', ['@object' => print_r($txInfo, TRUE)]); 

    $this->messenger()->addMessage($this->t('You specified a network_type of %network_type.', ['%network_type' => $network_type]));

    // フォームステートにデータを設定
    $meta = $txInfo['meta'];
    $meta_container = $this->getProtectedContainer($meta);
    // \Drupal::logger('qls_ss5')->notice('meta container:<pre>@object</pre>', ['@object' => print_r($container, TRUE)]); 
 
    $transaction = $txInfo['transaction'];
    $transaction_container = $this->getProtectedContainer($transaction);
        \Drupal::logger('qls_ss5')->notice('transaction container:<pre>@object</pre>', ['@object' => print_r($transaction_container, TRUE)]); 
    // $form_state->set('meta', $meta);
    // $form_state->set('transaction', $transaction);
    
    // Format the output
    $output = '<h2>Meta Information</h2><ul>';
    foreach ($meta_container as $key => $value) {
      $output .= '<li>' . $this->safeHtmlspecialchars($key) . ': ' . $this->safeHtmlspecialchars($value) . '</li>';
    }
    $output .= '</ul>';

    $output .= '<h2>Transaction Details</h2><ul>';
   
    $output .= $this->renderTransactionDetails($transaction_container);

    // foreach ($transaction_container as $key => $value) {
    //   if (is_array($value)) {
    //     $output .= '<li>' . htmlspecialchars($key) . ': <pre>' . htmlspecialchars(print_r($value, TRUE)) . '</pre></li>';
    //   } else {
    //     $output .= '<li>' . htmlspecialchars($key) . ': ' . htmlspecialchars($value) . '</li>';
    //   }
    // }
    // $output .= '</ul>';

    // Update the output container
    $form['output']['#markup'] = $output;

    return $form;
  }

  public function getProtectedContainer($object) {
    // リフレクションクラスを使ってプロパティにアクセス
    $reflectionClass = new \ReflectionClass($object);
    $property = $reflectionClass->getProperty('container');
    $property->setAccessible(true); // プロパティのアクセス制限を解除

    // 値を取得
    return $property->getValue($object);
  }

  public function renderTransactionDetails($data) {
    $output = '<ul>';
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            // 配列の場合は再帰的に処理
            $output .= '<li>' . $this->safeHtmlspecialchars($key) . ': <pre>' . $this->safeHtmlspecialchars(print_r($value, TRUE)) . '</pre></li>';
        } elseif (is_object($value)) {
            // オブジェクトの場合はリフレクションでプロパティを取得
            $output .= '<li>' . $this->safeHtmlspecialchars($key) . ': <ul>';
            $output .= $this->renderObjectDetails($value);
            $output .= '</ul></li>';
        } else {
            // 単純な値の場合は直接出力
            $output .= '<li>' . $this->safeHtmlspecialchars($key) . ': ' . $this->safeHtmlspecialchars($value) . '</li>';
        }
    }
    $output .= '</ul>';
    return $output;
}

  public function renderObjectDetails($object) {
    $output = '';
    $reflection = new \ReflectionClass($object);
    $properties = $reflection->getProperties();
    foreach ($properties as $property) {
        $property->setAccessible(true); // アクセス可能にする
        $name = $property->getName();
        $value = $property->getValue($object);

        if (is_array($value)) {
            $output .= '<li>' . $this->safeHtmlspecialchars($name) . ': <pre>' . $this->safeHtmlspecialchars(print_r($value, TRUE)) . '</pre></li>';
        } elseif (is_object($value)) {
            // オブジェクトの場合は再帰的に処理
            $output .= '<li>' . $this->safeHtmlspecialchars($name) . ': <ul>';
            $output .= $this->renderObjectDetails($value);
            $output .= '</ul></li>';
        } else {
            $output .= '<li>' . $this->safeHtmlspecialchars($name) . ': ' . $this->safeHtmlspecialchars($value) . '</li>';
        }
    }
    return $output;
  }

  public function safeHtmlspecialchars($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
  }
}
