<?php

namespace Drupal\qls_ss6\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Facade\SymbolFacade;

use SymbolRestClient\Configuration;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\NetworkRoutesApi;
use SymbolRestClient\Api\AccountRoutesApi;

use SymbolSdk\Symbol\Models\NamespaceRegistrationTransactionV1;
use SymbolSdk\Symbol\Models\AddressAliasTransactionV1;
use SymbolSdk\Symbol\Models\MosaicAliasTransactionV1;
use SymbolSdk\Symbol\Models\Address;
use SymbolSdk\Symbol\Models\AliasAction;
use SymbolSdk\Symbol\Models\MosaicId;
use SymbolSdk\Symbol\Models\NetworkType;
use SymbolSdk\Symbol\Models\Timestamp;
use SymbolSdk\Symbol\Models\BlockDuration;
use SymbolSdk\Symbol\Models\NamespaceId;
use SymbolSdk\Symbol\IdGenerator;


/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class LinkNamespaceForm extends FormBase {

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
    $form['#attached']['library'][] = 'qls_ss6/link_namespace';

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('Link Nemaspace'),
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

    $form['ownder_pvtKey'] = [
      '#type' => 'password',
      '#title' => $this->t('Owner Private Key'),
      '#description' => $this->t('Enter the private key of the owner.'),
      '#required' => TRUE,
    ];

    $form['symbol_address'] = [
      '#markup' => '<div id="symbol-address">Symbol Address</div>',
    ];
    $form['symbol_address_hidden'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'symbol-address-hidden', 
      ],
    ];

    $form['aliastype_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Alias Type'),
      '#options' => [
        '' => $this->t('Choose Alias Type'),
        '1' => $this->t('Link a mosaic'),
        '2' => $this->t('Link an address'),
      ],
      // The #ajax section tells the AJAX system that whenever this dropdown
      // emits an event, it should call the callback and put the resulting
      // content into the wrapper we specify. The questions-fieldset-wrapper is
      // defined below.
      '#ajax' => [
        'wrapper' => 'aliastype-fieldset-wrapper',
        'callback' => '::promptCallback',
      ],
    ];

    // This fieldset just serves as a container for the part of the form
    // that gets rebuilt. It has a nice line around it so you can see it.
    $form['aliastype_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Alias Type'),
      '#open' => TRUE,
      // We set the ID of this fieldset to fieldset-wrapper so the
      // AJAX command can replace it.
      '#attributes' => [
        'id' => 'aliastype-fieldset-wrapper',
        'class' => ['aliastype-wrapper'],
      ],
    ];

    // When the AJAX request comes in, or when the user hit 'Submit' if there is
    // no JavaScript, the form state will tell us what the user has selected
    // from the dropdown. We can look at the value of the dropdown to determine
    // which secondary form to display.
    $aliastype = $form_state->getValue('aliastype_select');
    if (!empty($aliastype) && $aliastype !== '0') {
      $form['aliastype_fieldset']['type'] = [
        '#markup' => $this->t('Which Alias Type'),
      ];

      $form['aliastype_fieldset']['namespace'] = [
        '#type' => 'select',
        '#title' => $this->t('Choose A Namespace'),
        '#options' => $this->getOwnedNamespaceOptions($form_state) ?? [],
        '#required' => TRUE,
      ];
      // Build up a secondary form, based on the type of question the user
      // chose.
      switch ($aliastype) {
        case '1'://Link a mosaic'
          
          $form['aliastype_fieldset']['mosaic'] = [
            '#type' => 'select',
            '#title' => $this->t('Choose A Mosaic'),
            '#options' => $this->getOwnedMosaicOptions($form_state) ?? [],
            '#required' => TRUE,
          ];

          break;
        case '2'://Link an address'
          $form['aliastype_fieldset']['address'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Link an address'),
            '#description' => $this->t('ex.TCWM5Z7LGSDFGBPWQYGZPKDJH2HGCJCT4NHJUAA'),
          ];

          break;

      }
      $form['aliastype_fieldset']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Send'),
      ]; 
      return $form;
    }
    // // Group submit handlers in an actions element with a key of "actions" so
    // // that it gets styled correctly, and so that other modules may add actions
    // // to the form. This is not required, but is convention.
    // $form['actions'] = [
    //   '#type' => 'actions',
    // ];

    // // Add a submit button that handles the submission of the form.
    // $form['actions']['submit'] = [
    //   '#type' => 'submit',
    //   '#value' => $this->t('Make Namespace'),
    // ];

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
    return 'link_namespace_form';
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  private function getOwnedNamespaceOptions(FormStateInterface $form_state) {
    $options = [];
    $network_type = $form_state->getValue(['network_type']);
    if($network_type === 'testnet') {
      $node_url = 'http://sym-test-03.opening-line.jp:3000';
    } elseif($network_type === 'mainnet') {
      $node_url = 'http://sym-main-03.opening-line.jp:3000';
    }
    $symbol_address_hidden = $form_state->getValue(['symbol_address_hidden']);
    $endpoint = '/namespaces?ownerAddress='.$symbol_address_hidden;
    try {
      // APIリクエスト
      $response = \Drupal::httpClient()->get($node_url . $endpoint, [
        'headers' => ['Accept' => 'application/json'],
      ]);

      if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getBody(), TRUE);
        // $options = [];
        foreach ($data['data'] as $namespace) {
          if($namespace['namespace']['depth']==1 && $namespace['namespace']['alias']['type']==0){

          $root_namespaceid = $namespace['namespace']['level0'];
          $response = $this->getNameSpaceNamePostRequest($root_namespaceid, $form_state);
          $root_namespace= json_decode($response, true);
          // \Drupal::logger('qls_ss6')->notice('221:<pre>@object</pre>', ['@object' => print_r($root_namespace, TRUE)]);

          $options[$root_namespace[0]['name']] = $root_namespace[0]['name'];
          }
          if($namespace['namespace']['depth']==2 && $namespace['namespace']['alias']['type']==0){
            $root_namespaceid = $namespace['namespace']['level0'];
            $response = $this->getNameSpaceNamePostRequest($root_namespaceid, $form_state);
            $root_namespace= json_decode($response, true); 
            $child_namespaceid = $namespace['namespace']['level1'];
            $response = $this->getNameSpaceNamePostRequest($child_namespaceid, $form_state);
            $child_namespace= json_decode($response, true);
            $namespace_name = $root_namespace[0]['name']. '.'. $child_namespace[0]['name'];
            $options[$namespace_name] = $namespace_name;
          }
          if($namespace['namespace']['depth']==3 && $namespace['namespace']['alias']['type']==0){
            $root_namespaceid = $namespace['namespace']['level0'];
            $response = $this->getNameSpaceNamePostRequest($root_namespaceid, $form_state);
            $root_namespace= json_decode($response, true); 
            $child_namespaceid = $namespace['namespace']['level1'];
            $response = $this->getNameSpaceNamePostRequest($child_namespaceid, $form_state);
            $child_namespace= json_decode($response, true);
            $grandchild_namespaceid = $namespace['namespace']['level2'];
            $response = $this->getNameSpaceNamePostRequest($grandchild_namespaceid, $form_state);
            $grandchild_namespace= json_decode($response, true);
            $namespace_name = $root_namespace[0]['name']. '.'. $child_namespace[0]['name']. '.'. $grandchild_namespace[0]['name'];
            $options[$namespace_name] = $namespace_name;
          }

        }
      }
      

    } catch (\Exception $e) {
      \Drupal::logger('qls_ss6')->error('Error fetching owned namespaces: ' . $e->getMessage());
    }
    // \Drupal::logger('qls_ss6')->notice('238:<pre>@object</pre>', ['@object' => print_r($options, TRUE)]);

    return $options;
  }

  private function getOwnedMosaicOptions(FormStateInterface $form_state) {
    $options = [];
    $network_type = $form_state->getValue(['network_type']);
    if($network_type === 'testnet') {
      $node_url = 'http://sym-test-03.opening-line.jp:3000';
    } elseif($network_type === 'mainnet') {
      $node_url = 'http://sym-main-03.opening-line.jp:3000';
    }
    $symbol_address = $form_state->getValue(['symbol_address_hidden']);
    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();

    $accountApiInstance = new AccountRoutesApi($client, $config);
    $account = $accountApiInstance->getAccountInfo($symbol_address);
    $json_data = json_encode($account, JSON_PRETTY_PRINT);
    $array_data = json_decode($json_data, true);
    // \Drupal::logger('qls_ss6')->notice('260:<pre>@object</pre>', ['@object' => print_r($account, TRUE)]);
    // \Drupal::logger('qls_ss6')->notice('261:<pre>@object</pre>', ['@object' => print_r($json_data, TRUE)]); 
    if ($array_data['account']['mosaics']) {
      foreach ($array_data['account']['mosaics'] as $mosaic) {
        if($mosaic['id']!='72C0212E67A08BCE'){ // testnetのsymbol.xym
          $options[$mosaic['id']] = $mosaic['id'];
        }
      }
    }
    return $options;
  }

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
      // $statusCode = $response->getStatusCode(); // HTTP ステータスコード
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
    $facade = new SymbolFacade($network_type);
    // ノードURLを設定
    if ($network_type === 'testnet') {
      $networkType = new NetworkType(NetworkType::TESTNET);
      $node_url = 'http://sym-test-03.opening-line.jp:3000';
    } elseif ($network_type === 'mainnet') {
      $networkType = new NetworkType(NetworkType::MAINNET);
      $node_url = 'http://sym-main-03.opening-line.jp:3000';
    }

    $ownder_pvtKey = $form_state->getValue('ownder_pvtKey');
    $ownerKey = $facade->createAccount(new PrivateKey($ownder_pvtKey));
    
    $aliastype_select = $form_state->getValue('aliastype_select');
    $namespace = $form_state->getValue('namespace');

    if($aliastype_select == 1){
      $linkmosaic = '0x'.$form_state->getValue('mosaic');
      /**
       * モザイクへリンク
       */
      \Drupal::logger('qls_ss6')->notice('namespace:<pre>@object</pre>', ['@object' => print_r($namespace, TRUE)]); 
      $namespaceIds = IdGenerator::generateNamespacePath($namespace); // ルートネームスペース
      \Drupal::logger('qls_ss6')->notice('namespaceIds:<pre>@object</pre>', ['@object' => print_r($namespaceIds, TRUE)]); 
      $namespaceId = new NamespaceId($namespaceIds[count($namespaceIds) - 1]);
      \Drupal::logger('qls_ss6')->notice('namespaceId:<pre>@object</pre>', ['@object' => print_r($namespaceId, TRUE)]); 
      $mosaicId = new MosaicId($linkmosaic);
      // \Drupal::logger('qls_ss6')->notice('mosaicId:<pre>@object</pre>', ['@object' => print_r($mosaicId, TRUE)]);

      //Tx作成
      $tx = new MosaicAliasTransactionV1(
        network: $networkType,
        signerPublicKey: $ownerKey->publicKey,
        deadline: new Timestamp($facade->now()->addHours(2)),
        namespaceId: new NamespaceId($namespaceId),
        mosaicId: $mosaicId,
        aliasAction: new AliasAction(AliasAction::LINK),
      );

    }else if($aliastype_select == 2){
      $linkaddress = $form_state->getValue('address');
      // \Drupal::logger('qls_ss6')->notice('linkaddress:<pre>@object</pre>', ['@object' => print_r($linkaddress, TRUE)]);

      /**
       * アカウントへのリンク
       */
      \Drupal::logger('qls_ss6')->notice('namespace:<pre>@object</pre>', ['@object' => print_r($namespace, TRUE)]);
      $namespaceId = IdGenerator::generateNamespaceId($namespace);
      // $namespaceIds = IdGenerator::generateNamespacePath($namespace); // ルートネームスペース
      // \Drupal::logger('qls_ss6')->notice('namespaceIds:<pre>@object</pre>', ['@object' => print_r($namespaceIds, TRUE)]);
      // $namespaceId = new NamespaceId($namespaceIds[count($namespaceIds) - 1]);
      // \Drupal::logger('qls_ss6')->notice('namespaceId:<pre>@object</pre>', ['@object' => print_r($namespaceId, TRUE)]);
      // $ownder_pvtKey = $form_state->get('ownder_pvtKey');
      // $ownerKey = $facade->createAccount(new PrivateKey($ownder_pvtKey));
      $linkaddress = $ownerKey->address;
      // \Drupal::logger('qls_ss6')->notice('address:<pre>@object</pre>', ['@object' => print_r($address, TRUE)]);
      // \Drupal::logger('qls_ss6')->notice('new address:<pre>@object</pre>', ['@object' => print_r(new Address($linkaddress), TRUE)]);
      
      //Tx作成
      $tx = new AddressAliasTransactionV1(
        network: $networkType,
        signerPublicKey: $ownerKey->publicKey,
        deadline: new Timestamp($facade->now()->addHours(2)),
        namespaceId: new NamespaceId($namespaceId),
        address: new Address($linkaddress),
        aliasAction: new AliasAction(AliasAction::LINK),
      );

    }
    
    $facade->setMaxFee($tx, 100);

    //署名
    $sig = $ownerKey->signTransaction($tx);
    $payload = $facade->attachSignature($tx, $sig);
    // \Drupal::logger('qls_ss6')->notice('payload:<pre>@object</pre>', ['@object' => print_r($payload, TRUE)]);

    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();
    $apiInstance = new TransactionRoutesApi($client, $config);

// try {
//   $result = $apiInstance->announceTransaction($payload);
//   echo $result . PHP_EOL;
// } catch (Exception $e) {
//   echo 'Exception when calling TransactionRoutesApi->announceTransaction: ', $e->getMessage(), PHP_EOL;
// }
// $hash = $facade->hashTransaction($tx);
// echo "\n===トランザクションハッシュ===" . PHP_EOL;
// echo $hash . PHP_EOL;
   
    // $tx = new NamespaceRegistrationTransactionV1(
    //   network: $networkType,
    //   signerPublicKey: $ownerKey->publicKey, // 署名者公開鍵
    //   deadline: new Timestamp($facade->now()->addHours(2)),
    //   duration: $blockDuration, // 有効期限
    //   id: new NamespaceId(IdGenerator::generateNamespaceId($root_namespace_name)), //必須
    //   name: $root_namespace_name,
    // );
    // $facade->setMaxFee($tx, 100);

    // // 署名
    // $sig = $ownerKey->signTransaction($tx);
    // $payload = $facade->attachSignature($tx, $sig);

    // // アナウンス
    // $config = new Configuration();
    // $config->setHost($node_url);
    // $client = \Drupal::httpClient();
    // $apiInstance = new TransactionRoutesApi($client, $config);
    
    try {
      $result = $apiInstance->announceTransaction($payload);
      // echo $result . PHP_EOL;
      $this->messenger()->addMessage($this->t('Transaction successfully announced: @result', ['@result' => $result]));
    } catch (Exception $e) {
      \Drupal::logger('qls_ss6')->error('トランザクションの発行中にエラーが発生しました: @message', ['@message' => $e->getMessage()]);
    }

  }

  /**
   * Callback for the select element.
   *
   * Since the questions_fieldset part of the form has already been built during
   * the AJAX request, we can return only that part of the form to the AJAX
   * request, and it will insert that part into questions-fieldset-wrapper.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function promptCallback(array $form, FormStateInterface $form_state) {
    return $form['aliastype_fieldset'];
  }

}

/**
 * モザイクへリンク
 */
// $namespaceIds = IdGenerator::generateNamespacePath("fugafuga.hoge"); // ルートネームスペース
// $namespaceId = new NamespaceId($namespaceIds[count($namespaceIds) - 1]);
// $mosaicId = new MosaicId("0x12679808DC2A1493");

// //Tx作成
// $tx = new MosaicAliasTransactionV1(
//   network: new NetworkType(NetworkType::TESTNET),
//   signerPublicKey: $aliceKey->publicKey,
//   deadline: new Timestamp($facade->now()->addHours(2)),
//   namespaceId: new NamespaceId($namespaceId),
//   mosaicId: $mosaicId,
//   aliasAction: new AliasAction(AliasAction::LINK),
// );
// $facade->setMaxFee($tx, 100);

// //署名
// $sig = $aliceKey->signTransaction($tx);
// $payload = $facade->attachSignature($tx, $sig);

// $apiInstance = new TransactionRoutesApi($client, $config);

// try {
//   $result = $apiInstance->announceTransaction($payload);
//   echo $result . PHP_EOL;
// } catch (Exception $e) {
//   echo 'Exception when calling TransactionRoutesApi->announceTransaction: ', $e->getMessage(), PHP_EOL;
// }
// $hash = $facade->hashTransaction($tx);
// echo "\n===トランザクションハッシュ===" . PHP_EOL;
// echo $hash . PHP_EOL;