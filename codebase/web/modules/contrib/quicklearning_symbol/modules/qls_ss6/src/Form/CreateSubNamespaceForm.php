<?php

namespace Drupal\qls_ss6\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Exception\RequestException;

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Facade\SymbolFacade;

use SymbolRestClient\Configuration;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\NetworkRoutesApi;

use SymbolSdk\Symbol\Models\NamespaceRegistrationTransactionV1;
use SymbolSdk\Symbol\Models\NetworkType;
use SymbolSdk\Symbol\Models\Timestamp;
use SymbolSdk\Symbol\Models\BlockDuration;
use SymbolSdk\Symbol\Models\NamespaceId;
use SymbolSdk\Symbol\Models\NamespaceRegistrationType;
use SymbolSdk\Symbol\IdGenerator;

use GMP;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class CreateSubNamespaceForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Since FormBase uses service traits, we can inject these services without
    // adding our own __construct() method.
    $form = new static($container);
    $form->setStringTranslation($container->get('string_translation'));
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

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
    $form['#attached']['library'][] = 'qls_ss6/create_subnamespace';

    // $network_type = $form_state->getValue('network_type');
  // \Drupal::logger('qls_ss6')->debug('65 Network Type: @network_type', ['@network_type' => $network_type]);
  // \Drupal::logger('qls_ss6')->notice('66 network type:<pre>@object</pre>', ['@object' => print_r($network_type, TRUE)]);
  // \Drupal::logger('debug')->debug('Step 1 Values: @values', ['@values' => print_r($form_state->getValue('step1'), TRUE)]);

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('Sub-Nemaspace'),
    ];

    $form['step'] = [
      '#type' => 'value',
      '#value' => !empty($form_state->getValue('step')) ? $form_state->getValue('step') : 1,
    ];

    switch ($form['step']['#value']) {
      case 1:
        $limit_validation_errors = [['step']];
        $form['step1'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Step 1: Network Type & Account'),
        ];
        $form['step1']['network_type'] = [
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

        $form['step1']['ownder_pvtKey'] = [
          '#type' => 'password',
          '#title' => $this->t('Owner Private Key'),
          '#description' => $this->t('Enter the private key of the owner.'),
          '#required' => TRUE,
        ];
    
        $form['step1']['symbol_address'] = [
          '#markup' => '<div id="symbol_address">Symbol Address</div>',
        ];
        $form['step1']['symbol_address_hidden'] = [
          '#type' => 'hidden',
          // '#value' => '', // 初期値は空
          '#attributes' => [
            'id' => 'symbol-address-hidden', // カスタム ID を指定
          ],
        ];
        break;

      case 2:
        $limit_validation_errors = [['step'], ['step1']];
        $form['step1'] = [
          '#type' => 'value',
          '#value' => $form_state->getValue('step1'),
        ];
        $form['step2'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Step 2: Sub-Namespace'),
        ];

        $form['step2']['parent_namespace'] = [
          '#type' => 'select',
          '#title' => $this->t('Parent Namespace'),
          '#options' => $this->getRootNamespaceOptions($form_state), // 動的に選択肢を生成
          '#empty_option' => $this->t('- Select a namespace -'), // 初期選択肢
        ];

        $form['step2']['sub_namespace_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Sub Namespace Name'),
          '#default_value' => $form_state->hasValue(['step2', 'sub_namespace_name']) ? $form_state->getValue(['step2', 'sub_namespace_name']) : '',
          '#description' => $this->t('Lowercase alphabet, numbers 0-9, hyphen, and underscore'),
          '#required' => TRUE,
        ];

        $form['expires_in'] = [
          '#type' => 'item',
          '#title' => $this->t('Expires In'),
        ];

        $form['estimated_rental_fee'] = [
          '#type' => 'item', 
          '#title' => $this->t('Estimated Rental Fee'),
          '#markup' => '<div id="estimated_rental_fee">10XYM</div>',
        ];
        // •サブネームスペースのレンタルフィーは、ネットワーク設定（パラメータ）によって管理されています。
        // •メインネットおよびテストネットのデフォルト値は 10 XYM とされています。
        //GET http://<node-url>/network/fees/rental
        // 10XYM
        break;

      default:
        $limit_validation_errors = [];
    }

    $form['actions'] = ['#type' => 'actions'];
    if ($form['step']['#value'] > 1) {
      $form['actions']['prev'] = [
        '#type' => 'submit',
        '#value' => $this->t('Previous step'),
        '#limit_validation_errors' => $limit_validation_errors,
        '#submit' => ['::prevSubmit'],
        '#ajax' => [
          'wrapper' => 'subnamespace-wrapper',
          'callback' => '::prompt',
        ],
      ];
    }
    if ($form['step']['#value'] != 2) {
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next step'),
        '#submit' => ['::nextSubmit'],
        '#ajax' => [
          'wrapper' => 'subnamespace-wrapper',
          'callback' => '::prompt',
        ],
      ];
    }
    if ($form['step']['#value'] == 2) {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t("Make Sub-Namespace"),
      ];
    }
    $form['#prefix'] = '<div id="subnamespace-wrapper">';
    $form['#suffix'] = '</div>';

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
    return 'create_subnamespace_form';
  }

  /**
   * Wizard callback function.
   *
   * @param array $form
   *   Form API form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form API form.
   *
   * @return array
   *   Form array.
   */
  public function prompt(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Ajax callback that moves the form to the next step and rebuild the form.
   *
   * @param array $form
   *   The Form API form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The FormState object.
   *
   * @return array
   *   The Form API form.
   */
  public function nextSubmit(array $form, FormStateInterface $form_state) {
    // $form_state->set('step1_network_type', $form_state->getValue(['step1', 'network_type']));
    $form_state->set('step1_network_type', $form_state->getValue('network_type'));
    $form_state->set('ownder_pvtKey', $form_state->getValue('ownder_pvtKey'));
    
    // $form_state->set('step1_network_type', $form_state->getValue(['step1', 'network_type']));
    // \Drupal::logger('qls_ss6')->notice('243 network type:<pre>@object</pre>', ['@object' => print_r($form_state->getValue(['step1', 'network_type']), TRUE)]);
    // \Drupal::logger('qls_ss6')->notice('244 network type:<pre>@object</pre>', ['@object' => print_r($form_state->getValue('network_type'), TRUE)]);
    $form_state->setValue('step', $form_state->getValue('step') + 1);
    $form_state->setRebuild();
    return $form;
  }
  /**
   * Ajax callback that moves the form to the previous step.
   *
   * @param array $form
   *   The Form API form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The FormState object.
   *
   * @return array
   *   The Form API form.
   */
  public function prevSubmit(array $form, FormStateInterface $form_state) {
    $form_state->setValue('step', $form_state->getValue('step') - 1);
    $form_state->setRebuild();
    return $form;
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
   
    $current_step = $form_state->get('step');
    if ($current_step == 1) {
      
    } elseif ($current_step == 2) {
      $sub_namespace_name = $form_state->getValue(['step2','sub_namespace_name']);
      if (!preg_match('/^[a-z0-9_-]+$/', $sub_namespace_name)) {
        $form_state->setErrorByName('sub_namespace_name', $this->t('Sub-Namespace Name must be lowercase alphabet, numbers 0-9, hyphen, and underscore.'));
      }
      if (strlen($sub_namespace_name) > 64) {
        $form_state->setErrorByName('sub_namespace_name', $this->t('Sub-Namespace Name cannot exceed 64 characters.'));
      }
    }
    
  }

  private function getRootNamespaceOptions(FormStateInterface $form_state) {
    $options = [];
    // $network_type = $form_state->getValue(['step1','network_type']);
    // $network_type = $form_state->get('step1_network_type');
    $network_type = $form_state->getValue(['network_type']);
    // \Drupal::logger('qls_ss6')->debug('<pre>@network_type</pre>', ['@network_type' => $network_type]);
    // $keys = array_keys($form_state->getValues());
    // \Drupal::logger('qls_ss6')->debug('<pre>@keys</pre>', ['@keys' => print_r($keys, TRUE)]);
    // \Drupal::logger('qls_ss6')->debug('<pre>@state</pre>', ['@state' => print_r($form_state->getValues(), TRUE)]);
    if($network_type === 'testnet') {
      $node_url = 'http://sym-test-03.opening-line.jp:3000';
    } elseif($network_type === 'mainnet') {
      $node_url = 'http://sym-main-03.opening-line.jp:3000';
    }
    $symbol_address_hidden = $form_state->getValue(['symbol_address_hidden']);
    // \Drupal::logger('qls_ss6')->debug('<pre>@symbol_address_hidden</pre>', ['@symbol_address_hidden' => $symbol_address_hidden]);
    $endpoint = '/namespaces?ownerAddress='.$symbol_address_hidden;

    try {
      // APIリクエスト
      $response = \Drupal::httpClient()->get($node_url . $endpoint, [
        'headers' => ['Accept' => 'application/json'],
      ]);

      if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getBody(), TRUE);

        // APIデータをセレクト用の配列に変換
        $options = [];
        foreach ($data['data'] as $namespace) {
          if($namespace['namespace']['depth']!=3){//2階層までのネームスペースを取得
            if (isset($namespace['namespace']['level1'])) {
              $namespaceid1 = $namespace['namespace']['level1'];
              $responseBody1 = $this->getNameSpaceNamePostRequest($namespaceid1, $form_state);
              $namespaces1= json_decode($responseBody1, true);

              // IDをキーとしてネームスペースを効率よく検索できるようにする
              $namespaceMap = [];
              foreach ($namespaces1 as $namespace) {
                  $namespaceMap[$namespace['id']] = $namespace['name'];
              }
              // $data をループして options 配列を構築
              foreach ($namespaces1 as $namespace) {
                if (isset($namespace['parentId'], $namespaceMap[$namespace['parentId']])) {
                    // parentId を使って親の名前を取得し、連結
                    // $options[$namespace['id']] = $namespaceMap[$namespace['parentId']] . '.' . $namespace['name'];
                    $namespaceName = $namespaceMap[$namespace['parentId']] . '.' . $namespace['name'];
                    // $options[$namespace['id']] = $namespaceMap[$namespace['parentId']] . '.' . $namespace['name'];
                    $options[$namespaceName] = $namespaceName;
                }
              }
            }
            elseif (isset($namespace['namespace']['level0'])) {
              $namespaceid = $namespace['namespace']['level0'];
              $responseBody = $this->getNameSpaceNamePostRequest($namespaceid, $form_state);
              // \Drupal::logger('qls_ss6')->info('responseBody: @response', ['@response' => $responseBody]);
              $namespaces= json_decode($responseBody, true);
              foreach ($namespaces as $namespace) {
                if (isset($namespace['id'], $namespace['name'])) {
                    // $options[$namespace['id']] = $namespace['name'];
                    $options[$namespace['name']] = $namespace['name'];
                }
              }
            }
          }
        }
        
        return $options;

      } else {
        \Drupal::logger('qls_ss6')->error('Failed to fetch namespaces: HTTP ' . $response->getStatusCode());
      }
    } catch (\Exception $e) {
      \Drupal::logger('qls_ss6')->error('Error fetching namespaces: ' . $e->getMessage());
    }

    // エラーハンドリング：デフォルト値を返す
    return ['' => $this->t('No namespaces available')];
  }

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
    \Drupal::logger('qls_ss6')->info('Response: @response', ['@response' => $body]);

    return $body; // 必要に応じて処理
  }
  catch (RequestException $e) {
    // エラーハンドリング
    \Drupal::logger('qls_ss6')->error('Error during POST request: @error', ['@error' => $e->getMessage()]);
    throw $e;
  }
}
  
// public function debugRecursive($data, $depth = 2) {
//   if ($depth === 0 || !is_array($data)) {
//     return '[...]';
//   }

//   $result = [];
//   foreach ($data as $key => $value) {
//     $result[$key] = is_array($value) ? $this->debugRecursive($value, $depth - 1) : $value;
//   }
//   return $result;
// }
// private function debugRecursive($data, $depth = 2) {
//   if ($depth === 0 || !is_array($data)) {
//       return is_array($data) ? '[...]' : $data;
//   }

//   $result = [];
//   foreach ($data as $key => $value) {
//       $result[$key] = is_array($value) ? $this->debugRecursive($value, $depth - 1) : $value;
//   }
//   return $result;
// }
// private function debugWithCircularCheck($data, $visited = []) {
//   if (is_array($data)) {
//       $result = [];
//       foreach ($data as $key => $value) {
//           // 循環参照をチェック
//           if (is_object($value)) {
//               $hash = spl_object_hash($value);
//               if (in_array($hash, $visited, TRUE)) {
//                   $result[$key] = '[CIRCULAR REFERENCE]';
//               } else {
//                   $visited[] = $hash;
//                   $result[$key] = $this->debugWithCircularCheck($value, $visited);
//               }
//           } else {
//               $result[$key] = is_array($value) ? $this->debugWithCircularCheck($value, $visited) : $value;
//           }
//       }
//       return $result;
//   }
//   return $data;
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
//     $values = $this->debugRecursive($form_state->getValues('step1'));
// \Drupal::logger('debug')->debug('<pre>@values</pre>', ['@values' => print_r($values, TRUE)]);
    // $network_type = $form_state->getValue(['step1','network_type']);
    // $network_type = $form_state->getValue(['network_type']);
    // $keys = array_keys($form_state->getValues());
    // \Drupal::logger('debug')->debug('Keys: @keys', ['@keys' => print_r($keys, TRUE)]);
    // \Drupal::logger('debug')->debug('Values: @values', ['@values' => print_r($form_state->getValues('step1'), TRUE)]);
    // $network_type = $form_state->getValue(['step1_network_type']);
    // $network_type = $form_state->getValue(['network_type']);
//     $values = $this->debugRecursive($form_state->getValues('step1'), 2);
// \Drupal::logger('qls_ss6')->debug('<pre>@values</pre>', ['@values' => print_r($values, TRUE)]);
    // $network_type = $form_state->getValue('network_type');
    $network_type = $form_state->get('step1_network_type');
    // \Drupal::logger('qls_ss6')->notice('483 network type:<pre>@object</pre>', ['@object' => print_r($network_type, TRUE)]);
    $facade = new SymbolFacade($network_type);
    // ノードURLを設定
    if ($network_type === 'testnet') {
      $networkType = new NetworkType(NetworkType::TESTNET);
      $node_url = 'http://sym-test-03.opening-line.jp:3000';
    } elseif ($network_type === 'mainnet') {
      $networkType = new NetworkType(NetworkType::MAINNET);
      $node_url = 'http://sym-main-03.opening-line.jp:3000';
    }

    $ownder_pvtKey = $form_state->get('ownder_pvtKey');
    $ownerKey = $facade->createAccount(new PrivateKey($ownder_pvtKey));
   
    $parent_namespace = $form_state->getValue('parent_namespace');
    // $namespace = explode('.', $parent_namespace);
    $namespace = !empty($parent_namespace) ? explode('.', $parent_namespace) : [];
    // \Drupal::logger('qls_ss6')->debug('502 <pre>@values</pre>', ['@values' => print_r($namespace, TRUE)]);
    if (count($namespace) === 2) {
      $root_namespace = $namespace[0];
      $sub_namespace = $namespace[1];
    } else {
      $root_namespace = $namespace[0];
    }
    // \Drupal::logger('qls_ss6')->debug('508 <pre>@values</pre>', ['@values' => print_r($root_namespace, TRUE)]);
    // $mosaicNames = $namespaceApiInstance->getMosaicsNames($mosaicIds);
    if($sub_namespace){
      $root_namespaceid = IdGenerator::generateNamespaceId($root_namespace);
      $parnetNameId = IdGenerator::generateNamespaceId($sub_namespace, $root_namespaceid);
    }else{
      $parnetNameId = IdGenerator::generateNamespaceId($root_namespace); //ルートネームスペース名
    }
    // $parnetNameId = $root_namespaceid;
    // $parnetNameId = gmp_intval(gmp_init($root_namespaceid, 16)); // GMPを使用
    $name = $form_state->getValue('sub_namespace_name'); //サブネームスペース名

    // // Tx作成
    // $tx = new NamespaceRegistrationTransactionV1(
    //   network: $networkType,
    //   signerPublicKey: $ownerKey->publicKey, // 署名者公開鍵
    //   deadline: new Timestamp($facade->now()->addHours(2)),
    //   duration: new BlockDuration(86400), // 有効期限
    //   parentId: new NamespaceId($parnetNameId),
    //   id: new NamespaceId(IdGenerator::generateNamespaceId($name, $parnetNameId)),
    //   registrationType: new NamespaceRegistrationType(
    //     NamespaceRegistrationType::CHILD
    //   ),
    //   name: $name,
    // );
    // $facade->setMaxFee($tx, 200);

    // $parnetNameId = IdGenerator::generateNamespaceId("qls"); //ルートネームスペース名
    // $name = "d"; //サブネームスペース名
    
    $tx = new NamespaceRegistrationTransactionV1(
      network: $networkType,
      signerPublicKey: $ownerKey->publicKey,  // 署名者公開鍵
      deadline: new Timestamp($facade->now()->addHours(2)),
      parentId: new NamespaceId($parnetNameId),
      id: new NamespaceId(IdGenerator::generateNamespaceId($name, $parnetNameId)),
      registrationType: new NamespaceRegistrationType(NamespaceRegistrationType::CHILD),
      name: $name,
    );
    $facade->setMaxFee($tx, 200);

    // 署名
    $sig = $ownerKey->signTransaction($tx);
    $payload = $facade->attachSignature($tx, $sig);
    // \Drupal::logger('qls_ss6')->notice('<pre>@object</pre>', ['@object' => print_r($payload, TRUE)]); 

    // \Drupal::logger('qls_ss6')->notice('<pre>@object</pre>', ['@object' => print_r($networkType, TRUE)]); 
    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();
    $apiInstance = new TransactionRoutesApi($client, $config);
    
    try {
      $result = $apiInstance->announceTransaction($payload);
      // echo $result . PHP_EOL;
      $this->messenger()->addMessage($this->t('Transaction successfully announced: @result', ['@result' => $result]));
    } catch (Exception $e) {
      \Drupal::logger('qls_ss6')->error('トランザクションの発行中にエラーが発生しました: @message', ['@message' => $e->getMessage()]);
    }

  }

}
