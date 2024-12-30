<?php

namespace Drupal\qls_ss5\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Facade\SymbolFacade;

use SymbolSdk\Symbol\Models\MosaicSupplyRevocationTransactionV1;
use SymbolSdk\Symbol\Models\MosaicFlags;
use SymbolSdk\Symbol\Models\MosaicNonce;
use SymbolSdk\Symbol\Models\BlockDuration;
use SymbolSdk\Symbol\Models\Amount;
use SymbolSdk\Symbol\Models\UnresolvedMosaicId;
use SymbolSdk\Symbol\Models\UnresolvedMosaic;
use SymbolSdk\Symbol\Models\MosaicSupplyChangeAction;
use SymbolSdk\Symbol\IdGenerator;
use SymbolSdk\Symbol\Models\EmbeddedMosaicDefinitionTransactionV1;
use SymbolSdk\Symbol\Models\EmbeddedMosaicSupplyChangeTransactionV1;
use SymbolSdk\Symbol\Models\EmbeddedTransferTransactionV1;
use SymbolSdk\Symbol\Models\MosaicId;
use SymbolSdk\Symbol\Models\AggregateCompleteTransactionV2;
use SymbolSdk\Symbol\Models\NetworkType;
use SymbolSdk\Symbol\Models\Timestamp;
use SymbolSdk\Symbol\Models\UnresolvedAddress;
use SymbolRestClient\Configuration;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\TransactionStatusRoutesApi;
use SymbolRestClient\Api\AccountRoutesApi;
use SymbolRestClient\Api\MosaicRoutesApi;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class ListMosaicsForm extends FormBase {

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

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('モザイク作成したアカウントが持つモザイク情報を確認します。'),
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

    $form['account_pvtKey'] = [
      '#type' => 'password',
      '#title' => $this->t('Account Private Key'),
      '#description' => $this->t('Enter the private key of the Account.'),
      '#required' => TRUE,
      // '#ajax' => [
      //   'callback' => '::updateSymbolAddress', // Ajaxコールバック関数
      //   'event' => 'blur', // フォーカスが外れたときにトリガー
      //   'wrapper' => 'symbol-address-wrapper', // 書き換え対象の要素ID
      // ],
    ];
    // $form['symbol_address'] = [
    //   // '#title' => $this->t('Account Symbol Address'),
    //   '#markup' => '<div id="symbol-address-wrapper">'.$this->t('Symbol address from private key').'</div>',
    // ];

    // Group submit handlers in an actions element with a key of "actions" so
    // that it gets styled correctly, and so that other modules may add actions
    // to the form. This is not required, but is convention.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button that handles the submission of the form.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('List Mosaics'),
    ];

    // // Submit 後に Views を表示
    //  if ($view_displayed) {
    //   $form['view'] = [
    //     '#type' => 'view',
    //     '#name' => 'your_view_machine_name', // ビューのマシン名
    //     '#display_id' => 'owned_mosaic_list',  // ビューのディスプレイ ID
    //     // '#arguments' => [$form_state->getValue('input_text')], // 引数を渡す例
    //   ];
    // }

    // フォーム送信後のデータを取得
    $data = $form_state->get('mosaic_table_data');
    if ($data) {
      // テーブルヘッダーを定義
      $headers = [
        $this->t('ID'),
        $this->t('Supply'),
        $this->t('Owner Address'),
        $this->t('Divisibility'),
        $this->t('Flags'),
        $this->t('Duration'),
        $this->t('Start Height'),
      ];

      // テーブル行を作成
      $rows = [];
      foreach ($data as $item) {
        $rows[] = [
          $item['id'],
          $item['supply'],
          $item['owner_address'],
          $item['divisibility'],
          $item['flags'],
          $item['duration'],
          $item['start_height'],
        ];
      }

      // テーブルをフォームに追加
      $form['mosaic_table'] = [
        '#type' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
        '#empty' => $this->t('No mosaic data available.'),
      ];
    }

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
    return 'list_mosaics_form';
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
    $pvtKey = $form_state->getValue('account_pvtKey');
    if (strlen($pvtKey) !=  64) {
      // Set an error for the form element with a key of "title".
      $form_state->setErrorByName('account_pvtKey', $this->t('The private key must be 64 characters long.'));
    }
  }
  /**
   * Ajaxコールバック関数
   */
  // public function updateSymbolAddress(array &$form, FormStateInterface $form_state) {
    
  //   // 入力されたプライベートキーを取得
  //   $pvtKey = $form_state->getValue('account_pvtKey');
  //   if (!$pvtKey || strlen($pvtKey) !== 64) {
  //     // エラーメッセージをフォームに追加
  //     $form['symbol_address']['#markup'] = '<div id="symbol-address-wrapper" style="color: red;">'
  //         . $this->t('The private key must be 64 characters long.') . '</div>';

  //   }
  //   else{
  //     $network_type = $form_state->getValue('network_type');
  //     $facade = new SymbolFacade($network_type);
  //     try {
  //       $accountKey = $facade->createAccount(new PrivateKey($pvtKey));
  //       $accountRawAddress = $accountKey->address;
        
        
  //     } catch (\Exception $e) {
  //       \Drupal::logger('qls_ss5')->error('Failed to create account: ' . $e->getMessage());
  //       $accountRawAddress = "Error: Unable to generate address.";
  //     }
  //     // $this->messenger()->addMessage($this->t('RawAddress: @rawAddress', ['@rawAddress' => $accountRawAddress]));
  //     //\Drupal::logger('qls_ss5')->notice('<pre>@object</pre>', ['@object' => print_r($accountRawAddress, TRUE)]);
      
  //     // 動的に更新するフィールドの値を設定
  //     $form['symbol_address']['#markup'] = '<div id="symbol-address-wrapper">' . 'test' . '</div>';

  //   }
  //   return $form['symbol_address'];
    
  // }

  /**
   * Implements a form submit handler.
   *
   * The submitForm method is the default method called for any submit elements.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /*
     * This would normally be replaced by code that actually does something
     * with the title.
     */
    $network_type = $form_state->getValue('network_type');
    // ノードURLを設定
    if ($network_type === 'testnet') {
      // $networkType = new NetworkType(NetworkType::TESTNET);
      $node_url = 'http://sym-test-03.opening-line.jp:3000';
    } elseif ($network_type === 'mainnet') {
      // $networkType = new NetworkType(NetworkType::MAINNET);
      $node_url = 'http://sym-main-03.opening-line.jp:3000';
    }
    // SymbolFacadeを使って新しいアカウントを作成
    $facade = new SymbolFacade($network_type);

    $pvtKey = $form_state->getValue('account_pvtKey');
    $accountKey = $facade->createAccount(new PrivateKey($pvtKey));
    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();


    // 3.3 アカウント情報の確認- 所有モザイク一覧の取得 を事前に実施する
    try{
      $accountApiInstance = new AccountRoutesApi($client, $config);
      $mosaicApiInstance = new MosaicRoutesApi($client, $config);
    } catch (\Exception $e) {
      \Drupal::logger('qls_ss5')->error('Failed to create api instance: ' . $e->getMessage());
    }

    $account = $accountApiInstance->getAccountInfo($accountKey->address);
    foreach($account->getAccount()->getMosaics() as $mosaic) {
      $mocaisInfo[] = $mosaicApiInstance->getMosaic($mosaic->getId());
      
    }
    // \Drupal::logger('qls_ss5')->notice('<pre>@object</pre>', ['@object' => print_r($mocaisInfo, TRUE)]);
    $flattenedData = $this->flattenMosaicData($mocaisInfo);
    // \Drupal::logger('qls_ss5')->notice('<pre>@object</pre>', ['@object' => print_r($flattenedData, TRUE)]); 
    // \Drupal::state()->set('mosaic_flattened_data', $flattenedData); 
    // // セッションサービスを取得
    // $session = \Drupal::service('session');
    // // フラットなモザイクデータを保存
    // $session->set('mosaic_flattened_data', $flattenedData);
    // \Drupal::state()->set('mosaic_flattened_data', $flattenedData);

    $this->messenger()->addMessage($this->t('You specified a network_type of %network_type.', ['%network_type' => $network_type]));

    // $form_state->set('view_displayed', TRUE);
    // フォームステートにデータを設定
    $form_state->set('mosaic_table_data', $flattenedData);
    $form_state->setRebuild();
    
  }

  

  function flattenMosaicData($mosaicInfoArray) {
    $flattenedData = [];

    foreach ($mosaicInfoArray as $mosaicInfo) {
        $mosaic = $mosaicInfo->getMosaic(); // MosaicDTO オブジェクトを取得

        $flattenedData[] = [
            'id' => $mosaic->getId(), // モザイクID
            'supply' => $mosaic->getSupply(), // サプライ量
            'owner_address' => $mosaic->getOwnerAddress(), // 所有者アドレス
            'divisibility' => $mosaic->getDivisibility(), // 分割可能性
            'flags' => $mosaic->getFlags(), // フラグ
            'duration' => $mosaic->getDuration(), // 有効期間
            'start_height' => $mosaic->getStartHeight(), // 開始ブロック高さ
        ];
    }

    return $flattenedData;
  }

}
