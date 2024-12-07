<?php
namespace Drupal\qls_ss7\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use SymbolSdk\Symbol\MessageEncoder;
use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Symbol\Models\TransferTransactionV1;
use SymbolSdk\Symbol\Models\NetworkType;
use SymbolSdk\Symbol\Models\Timestamp;
use SymbolSdk\Symbol\Models\UnresolvedMosaic;
use SymbolSdk\Symbol\Models\UnresolvedMosaicId;
use SymbolSdk\Symbol\Models\Amount;
use SymbolSdk\Symbol\Models\UnresolvedAddress;
use SymbolSdk\Symbol\Address;

use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\TransactionStatusRoutesApi;
use SymbolRestClient\Configuration;
use SymbolSdk\Facade\SymbolFacade;

use SymbolRestClient\Api\MosaicRoutesApi;
use SymbolRestClient\Api\MetadataRoutesApi;
use SymbolSdk\Symbol\Models\EmbeddedAccountMetadataTransactionV1;
use SymbolSdk\Symbol\Models\EmbeddedMosaicMetadataTransactionV1;
use SymbolSdk\Symbol\Models\AggregateCompleteTransactionV2;
use SymbolSdk\Symbol\Models\EmbeddedNamespaceMetadataTransactionV1;
use SymbolSdk\Symbol\Metadata;
use SymbolSdk\Symbol\IdGenerator;
use SymbolRestClient\Api\NamespaceRoutesApi;
use SymbolSdk\Symbol\Models\NamespaceId;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class ListMetadataForm extends FormBase {

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
    return 'list_metadata_form';
  }
  // /**
  //  * SymbolAccountServiceのインスタンス
  //  *
  //  * @var \Drupal\qls_ss7\Service\SymbolAccountService
  //  */
  // protected $symbolAccountService;

  // /**
  //  * TransactionServiceのインスタンス
  //  *
  //  * @var \Drupal\qls_ss7\Service\TransactionService
  //  */
  // protected $transactionService;

  // /**
  //  * コンストラクタでSymbolAccountServiceを注入
  //  */
  // public function __construct(TransactionService $transaction_service, SymbolAccountService $symbol_account_service) {
  //   $this->transactionService = $transaction_service;
  //   $this->symbolAccountService = $symbol_account_service;
  // }

  // /**
  //  * createメソッドでサービスコンテナから依存性を注入
  //  */
  // public static function create(ContainerInterface $container) {
  //   return new static(
  //     $container->get('qls_ss7.transaction_service'),         // TransactionService
  //     $container->get('qls_ss7.symbol_account_service')       // SymbolAccountService
  //   );
  // }

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
    $form['#attached']['library'][] = 'qls_ss7/metadata_namespace';

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('7.4 確認'),
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

    // $form['source_address'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Source Private Key'),
    //   '#description' => $this->t('Metadata Source Owner Private Key.'),
    //   '#required' => TRUE,
    // ];

    $form['source_pvtKey'] = [
      '#type' => 'password',
      '#title' => $this->t('Source Private Key'),
      '#description' => $this->t('Source Owner Private Key.'),
      '#required' => TRUE,
    ];
    $form['source_symbol_address'] = [
      '#markup' => '<div id="source-symbol-address">Symbol Address</div>',
    ];

    // Group submit handlers in an actions element with a key of "actions" so
    // that it gets styled correctly, and so that other modules may add actions
    // to the form. This is not required, but is convention.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button that handles the submission of the form.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('List Metadata'),
    ];

    // フォーム送信後のデータを取得
    $data = $form_state->get('metadada_table_data');
    if ($data) {
      // テーブルヘッダーを定義
      $headers = [
        $this->t('Target Address'),
        $this->t('Scoped Metadata Key'),
        $this->t('Metadata Type'),
        $this->t('Value'),
      ];

      // テーブル行を作成
      $rows = [];
      foreach ($data as $item) {
        $rows[] = [
          $item['targetAddress'],
          $item['scopedMetadataKey'],
          $item['metadataType'],
          $item['value'],
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
    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();
    $metaApiInstance = new MetadataRoutesApi($client, $config);
    
    $source_pvtKey = $form_state->getValue('source_pvtKey');
    $sourceKey = $facade->createAccount(new PrivateKey($source_pvtKey));
    // $sourceAddress = $sourceKey->address; // メタデータ作成者アドレス

    $metadataInfo = $metaApiInstance->searchMetadataEntries(
      target_address: $sourceKey->address,
      source_address: $sourceKey->address,
    );

    // {
    //   "id": "66A120C284E82060AFC1E5AE",
    //   "metadataEntry": {
    //   "version": 1,
    //   "compositeHash":
    //   "77B448E5375D16F44FF3C2E35221759B35438D360BD89DB0679003FFD1E7D9F5",
    //   "sourceAddress": "98E521BD0F024F58E670A023BF3A14F3BECAF0280396BED0",
    //   "targetAddress": "98E521BD0F024F58E670A023BF3A14F3BECAF0280396BED0",
    //   "scopedMetadataKey": "8EF1ED391DB8F32F",
    //   "targetId": {},
    //   "metadataType": 0,
    //   "value": "686F6765"
    //   }
    //   },
    $flattenedData = $this->flattenMetadataData($metadataInfo);
    $form_state->set('metadada_table_data', $flattenedData);
    $form_state->setRebuild();

  }
  private function flattenMetadataData($metadataInfo) {
    $flattenedData = [];
    foreach ($metadataInfo->getData() as $metadataItem) {
      // $id = $metadataItem->getId();
      $metadataEntry = $metadataItem->getMetadataEntry();
      $flattenedData[] = [
        // $sourceAddress = $metadataEntry->getSourceAddress();
        'targetAddress' => $metadataEntry->getTargetAddress(),
        'scopedMetadataKey' => $metadataEntry->getScopedMetadataKey(),
        'metadataType' => $metadataEntry->getMetadataType(),
        'value' => hex2bin($metadataEntry->getValue()),// デコードされた値を取得
      ];
      // ログに出力
      // \Drupal::logger('qls_ss7')->notice("ID: $id, Source: $sourceAddress, Target: $targetAddress, Value: $decodedValue"); 
    }
    return $flattenedData;
  }

}  