<?php

namespace Drupal\qls_ss6\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Facade\SymbolFacade;

use SymbolSdk\Symbol\Address;
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

use SymbolSdk\CryptoTypes\Hash256;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class ListNamespacesForm extends FormBase {

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

    // クラスの利用可能なメソッドを取得
// $methods = get_class_methods(Address::class);
// \Drupal::logger('qls_ss6')->notice('methods:<pre>@methods</pre>', ['@methods' => print_r($methods, TRUE)]); 


    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('ネームスペース一覧'),
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
      '#value' => $this->t('List Namespaces'),
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
    $data = $form_state->get('namespace_table_data');
    if ($data) {
      // テーブルヘッダーを定義
      $headers = [
        $this->t('ID'),
        $this->t('Name'),
        $this->t('Expiration'),
        $this->t('Expired'),
        $this->t('Alias Type'),
        $this->t('Alias'),
        $this->t('Action'),
      ];

      // テーブル行を作成
      $rows = [];
      foreach ($data as $item) {
        $rows[] = [
          $item['id'],
          $item['name'],
          $item['expiration'],
          $item['expired'], 
          $item['aliasType'],
          $item['alias'],
          $item['action'],
        ];
      }

      // テーブルをフォームに追加
      $form['namespace_table'] = [
        '#type' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
        '#empty' => $this->t('No Namaspaces data available.'),
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
    return 'list_namespaces_form';
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
      $networkType = new NetworkType(NetworkType::TESTNET);
      $node_url = 'http://sym-test-03.opening-line.jp:3000';
    } elseif ($network_type === 'mainnet') {
      $networkType = new NetworkType(NetworkType::MAINNET);
      $node_url = 'http://sym-main-03.opening-line.jp:3000';
    }
    // SymbolFacadeを使って新しいアカウントを作成
    $facade = new SymbolFacade($network_type);

    $pvtKey = $form_state->getValue('account_pvtKey');
    $accountKey = $facade->createAccount(new PrivateKey($pvtKey));
    $accountAddress = $accountKey->address;
    $endpoint = '/namespaces?ownerAddress='.$accountAddress;

    $config = new Configuration();
    $config->setHost($node_url);
    // $client = \Drupal::httpClient();
    // $apiInstance = new TransactionRoutesApi($client, $config);

    try {
      // APIリクエスト
      $response = \Drupal::httpClient()->get($node_url . $endpoint, [
        'headers' => ['Accept' => 'application/json'],
      ]);
      if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getBody(), TRUE);
        // \Drupal::logger('qls_ss6')->notice('<pre>@object</pre>', ['@object' => print_r($data, TRUE)]); 
      } else {
        \Drupal::logger('qls_ss6')->error('Failed to fetch namespaces: HTTP ' . $response->getStatusCode());
      }
    } catch (\Exception $e) {
      \Drupal::logger('qls_ss6')->error('Error fetching namespaces: ' . $e->getMessage());
    }
    // $form_state->set('view_displayed', TRUE);

    $nodeInfo = $this->getNodeinfo($form_state);
    // \Drupal::logger('qls_ss6')->notice('<pre>@nodeInfo</pre>', ['@nodeInfo' => print_r($nodeInfo, TRUE)]); 
    $currentHeight = $nodeInfo['height'];

    $namespace_table_data[] = [];
    foreach ($data['data'] as $index => $namespace) {
      // if($namespace['namespace']['depth']!=3){//2階層までのネームスペースを取得
      // \Drupal::logger('qls_ss6')->info('depth: @depth', ['@depth' => $namespace['namespace']['depth']]); 
      $root_namespaces = []; 
      $root_namespaceid = $namespace['namespace']['level0'];
      $responseBody = $this->getNameSpaceNamePostRequest($root_namespaceid, $form_state);
      // \Drupal::logger('qls_ss6')->info('responseBody: @response', ['@response' => $responseBody]);
          // responseBody: [{"id":"D52C99BDEE2471AB","name":"qls"}]
      $root_namespaces= json_decode($responseBody, true);
      // $starthight = $namespace['namespace']['startHeight'];
      $endHight = $namespace['namespace']['endHeight'];
      $expired = ''; 
      if($endHight < $currentHeight){
        $expired = 'true';
      }else{
        $expired = 'false';
        $expiration = $this->formatSecondsToReadableTime(($endHight - $currentHeight)*30);
      }
      $namespace_table_data[$index]['expiration'] = $expiration;
      $namespace_table_data[$index]['expired'] = $expired;
      $namespace_table_data[$index]['action'] = 'link alias'; 

      $aliasType = 0; 
      $alias_type = $namespace['namespace']['alias']['type'];
      if($alias_type == 1){
        $aliasType = 'Mosaic';
        $namespace_table_data[$index]['alias'] = $namespace['namespace']['alias']['mosaicId'];
        $namespace_table_data[$index]['action'] = 'unlink mosaic'; 
        
      }elseif($alias_type == 2){
        $aliasType = 'Address';
        $address = Address::fromDecodedAddressHexString($namespace['namespace']['alias']['address']);
        $namespace_table_data[$index]['alias'] = $address;
        $namespace_table_data[$index]['action'] = 'unlink address'; 
      }  
      $namespace_table_data[$index]['aliasType'] = $aliasType;
      

      switch ($namespace['namespace']['depth'])
      {
        case 1:
          // \Drupal::logger('qls_ss6')->info('namespaces: @namespaces', ['@namespaces' => print_r($namespaces, true)]);
          $namespace_table_data[$index]['id'] = $namespace['namespace']['level0'];
          $namespace_table_data[$index]['name'] = $root_namespaces[0]['name'];
          $namespace_table_data[$index]['action'] .= 'extend dration';
          
          break;
        case 2:

          $namespaceid = $namespace['namespace']['level1'];
          $responseBody = $this->getNameSpaceNamePostRequest($namespaceid, $form_state);
          $namespaces= json_decode($responseBody, true);

          $namespace_table_data[$index]['id'] = $namespaceid;
          $namespace_table_data[$index]['name'] = $namespaces[1]['name']. '.' .$namespaces[0]['name'];
          
          break;
        case 3:
          $namespaceid = $namespace['namespace']['level2'];
          $responseBody = $this->getNameSpaceNamePostRequest($namespaceid, $form_state);
          $namespaces= json_decode($responseBody, true);

          $namespace_table_data[$index]['id'] = $namespaceid;
          $namespace_table_data[$index]['name'] = $namespaces[2]['name']. '.' .$namespaces[1]['name']. '.' .$namespaces[0]['name'];

          break;
        default:
          break;
        
       
      }
    }
    \Drupal::logger('qls_ss6')->info('namespace_table_data: <pre>@namespace_table_data</pre>', ['@namespace_table_data' => print_r($namespace_table_data, true)]); 

    // フォームステートにデータを設定
    $form_state->set('namespace_table_data', $namespace_table_data);
    $form_state->setRebuild();
   
  }

  

  // function flattenNamespaceData($mosaicInfoArray) {
  //   $flattenedData = [];

  //   foreach ($mosaicInfoArray as $mosaicInfo) {
  //       $mosaic = $mosaicInfo->getMosaic(); // MosaicDTO オブジェクトを取得

  //       $flattenedData[] = [
  //           'id' => $mosaic->getId(), // モザイクID
  //           'supply' => $mosaic->getSupply(), // サプライ量
  //           'owner_address' => $mosaic->getOwnerAddress(), // 所有者アドレス
  //           'divisibility' => $mosaic->getDivisibility(), // 分割可能性
  //           'flags' => $mosaic->getFlags(), // フラグ
  //           'duration' => $mosaic->getDuration(), // 有効期間
  //           'start_height' => $mosaic->getStartHeight(), // 開始ブロック高さ
  //       ];
  //   }

  //   return $flattenedData;
  // }

  //Get readable names for a set of namespaces
  //https://symbol.github.io/symbol-openapi/v0.11.3/#tag/Namespace-routes/operation/getNamespacesNames
  private function getNameSpaceNamePostRequest($namespaceid, FormStateInterface $form_state) {
    
    $network_type = $form_state->getValue(['network_type']);
      if($network_type === 'testnet') {
        $node_url = 'http://sym-test-03.opening-line.jp:3000/namespaces/names';
      } elseif($network_type === 'mainnet') {
        $node_url = 'http://sym-main-03.opening-line.jp:3000/namespaces/names';
      }

    // 送信するデータ
    $data = [
      'namespaceIds' => $namespaceid
    ];

    try {
      // HTTP クライアントで POST リクエストを送信
      $response = \Drupal::httpClient()->post($node_url, [
        'headers' => [
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
        ],
        'json' => $data, // データを JSON として送信
      ]);

      // レスポンスを取得
      $statusCode = $response->getStatusCode(); // HTTP ステータスコード
      $body = $response->getBody()->getContents(); // レスポンス本文

      // 結果をログに記録
      // \Drupal::logger('qls_ss6')->info('Response: @response', ['@response' => $body]);

      return $body; // 必要に応じて処理
    }
    catch (RequestException $e) {
      // エラーハンドリング
      \Drupal::logger('qls_ss6')->error('Error during POST request: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }

  private function getNodeinfo(FormStateInterface $form_state) {
    
    $network_type = $form_state->getValue(['network_type']);
      if($network_type === 'testnet') {
        $node_url = 'http://sym-test-03.opening-line.jp:3000/chain/info';
      } elseif($network_type === 'mainnet') {
        $node_url = 'http://sym-main-03.opening-line.jp:3000/chain/info';
      }


      try {
        // HTTP クライアントで GET リクエストを送信
        $response = \Drupal::httpClient()->get($node_url, [
          'headers' => [
              'Accept' => 'application/json',
          ],
      ]);

      // レスポンスをデコード
      $body = $response->getBody()->getContents();
      $data = json_decode($body, true);

      return $data; // 必要に応じて処理
    }
    catch (RequestException $e) {
      // エラーハンドリング
      \Drupal::logger('qls_ss6')->error('Error during POST request: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }

  private function formatSecondsToReadableTime($seconds) {
    $days = floor($seconds / (24 * 3600));
    $seconds %= (24 * 3600);
    $hours = floor($seconds / 3600);
    $seconds %= 3600;
    $minutes = floor($seconds / 360);

    return sprintf('%d d %d h %d m', $days, $hours, $minutes);
    // Drupalの翻訳対応の形式で文字列を生成
    // return $this->t('@days d @hours h @minutes m', [
    //     '@days' => $days,
    //     '@hours' => $hours,
    //     '@minutes' => $minutes,
    // ]);
  }

}
