<?php

namespace Drupal\qls_ss4\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use SymbolSdk\Symbol\KeyPair;
use SymbolSdk\Symbol\MessageEncoder;
use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\CryptoTypes\PublicKey as CryptoPublicKey;
use SymbolRestClient\Api\AccountRoutesApi;
use SymbolSdk\Symbol\Models\PublicKey;
use SymbolSdk\Symbol\Models\TransferTransactionV1;
use SymbolSdk\Symbol\Models\EmbeddedTransferTransactionV1;
use SymbolSdk\Symbol\Models\AggregateCompleteTransactionV2;
use SymbolSdk\Symbol\Models\NetworkType;
use SymbolSdk\Symbol\Models\Timestamp;
use SymbolSdk\Symbol\Models\UnresolvedMosaic;
use SymbolSdk\Symbol\Models\UnresolvedMosaicId;
use SymbolSdk\Symbol\Models\Amount;
use SymbolSdk\Symbol\Models\UnresolvedAddress;
use SymbolSdk\Symbol\Address;
use SymbolSdk\Symbol\Verifier;

use SymbolRestClient\Api\NodeRoutesApi;
use SymbolRestClient\Api\NetworkRoutesApi;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\TransactionStatusRoutesApi;
use SymbolRestClient\Configuration;
use SymbolSdk\Facade\SymbolFacade;

use Drupal\qls_ss4\Service\SymbolAccountService;
use Drupal\qls_ss4\Service\TransactionService;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class AggregateTransferTransactionForm extends FormBase {

  /**
   * Build the Aggregate Transfer Transaction form.
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
      '#markup' => $this->t('4.6 アグリゲートトランザクション'),
    ];
    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('同一内容を一斉送信するサンプル'),
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

    $form['recipientAddresses'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Recipient Addresses'),
      '#description' => $this->t('Enter comma separated addresses.'),
      '#required' => TRUE,
    ];

    $form['sender_pvtKey'] = [
      '#type' => 'password',
      '#title' => $this->t('Sender Private Key'),
      '#description' => $this->t('Enter the private key of the sender.'),
      '#required' => TRUE,
    ];

    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#description' => $this->t('Max: 1023 byte.'),
      // '#default_value' => 'Hello, Symbol!',
    ];

    $form['feeMultiprier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('feeMultiprier'),
      '#description' => $this->t('transaction size * feeMultiprier = transaction fee'),
      '#required' => TRUE,
      '#default_value' => '100',
    ];

    $form['mosaicid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mosaic ID'),
      '#description' => $this->t('TESTNET XYM:0x72C0212E67A08BCE / MAINNET XYM:0x6BED913FA20223F8'),
      '#required' => TRUE,
      '#default_value' => '0x72C0212E67A08BCE',
    ];

    $form['amount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Amount'),
      '#description' => $this->t('Enter the amount of the mosaic. (1 XYM = 1000000)'),
      '#required' => TRUE,
      '#default_value' => '1000000',
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
      '#value' => $this->t('Make Aggregate Transaction'),
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
    return 'aggregate_transfer_transaction_form';
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
    
    $message = $form_state->getValue('message');
    if (mb_strlen($message, '8bit') > 1023) {
      $form_state->setErrorByName('message', $this->t('The message must be less equal than 1023 byte.'));
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
    
    // まず、アグリゲートトランザクションに含めるトランザクションを作成します。このと
    // きDeadline を指定する必要はありませんがnetwork は必ず指定してください。（指定し
    // ない場合Failure_Core_Wrong_Network が発生します）リスト化するときに、生成し
    // たトランザクションにtoAggregate を追加して送信元アカウントの公開鍵を指定します。
    // ちなみに送信元アカウントと署名アカウントが必ずしも一致するとは限りません。後の章
    // での解説で「Bob の送信トランザクションをAlice が署名する」といった事が起こり得る
    // ためこのような書き方をします。これはSymbol ブロックチェーンでトランザクションを
    // 扱ううえで最も重要な概念になります。なお、本章で扱うトランザクションは同じAlice
    // ですので、アグリゲートボンデッドトランザクションへの署名もAlice を指定します。

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
    // \Drupal::logger('qls_ss4')->notice('<pre>@object</pre>', ['@object' => print_r($networkType, TRUE)]); 
 
    $message = $form_state->getValue('message');
    if($message){

      $messageData = '\0'.$message;
      //$messageData = $message;
      \Drupal::logger('qls_ss4')->notice('<pre>@object</pre>', ['@object' => print_r($messageData, TRUE)]);  

    } else {
      $messageData = "";
    }

    $sender_pvtKey = $form_state->getValue('sender_pvtKey');
    // 秘密鍵からアカウント生成
    $senderKey = $facade->createAccount(new PrivateKey($sender_pvtKey));

    $mosaicid = $form_state->getValue('mosaicid');
    $amount = $form_state->getValue('amount');

    // 受取人アドレス(送信先)
    $recipientAddressesCsv = $form_state->getValue('recipientAddresses');
    $recipientAddresses = explode(',', $recipientAddressesCsv);

    

    $innerTxs = [];
    foreach ($recipientAddresses as $recipientAddStr) {
      $recipientAddress = new UnresolvedAddress($recipientAddStr);
      // アグリゲートTxに含めるTxを作成
      $innerTxs[] = new EmbeddedTransferTransactionV1(
          network: $networkType,
          signerPublicKey: $senderKey->publicKey,
          recipientAddress: $recipientAddress,
          mosaics: [
            new UnresolvedMosaic(
              mosaicId: new UnresolvedMosaicId($mosaicid),
              amount: new Amount($amount)
            )
          ],
          message: $messageData,
        );
    }

    $feeMultiprier = $form_state->getValue('feeMultiprier');
    
    // マークルハッシュの算出
    $merkleHash = $facade->hashEmbeddedTransactions($innerTxs);
    // アグリゲートTx作成
    $aggregateTx = new AggregateCompleteTransactionV2(
      network: $networkType,
      signerPublicKey: $senderKey->publicKey,
      deadline: new Timestamp($facade->now()->addHours(2)),
      transactionsHash: $merkleHash,
      transactions: $innerTxs
    );
    
    // $facade->setMaxFee($aggregateTx, $feeMultiprier); // 手数料

    // 4.6.1 アグリゲートトランザクションにおける最大手数料
    // アグリゲートトランザクションも通常のトランザクション同様、最大手数料を直接指定
    // する方法とfeeMultiprier で指定する方法があります。先の例では最大手数料を直接指定
    // する方法を使用しました。ここではfeeMultiprier で指定する方法を紹介します。
    $requiredCosignatures = 1; // 必要な連署者の数を指定
    if ($requiredCosignatures > count($aggregateTx->cosignatures)) {
      $calculatedCosignatures = $requiredCosignatures;
    } else {
      $calculatedCosignatures = count($aggregateTx->cosignatures);
    } 
    $sizePerCosignature = 8 + 32 + 64;
    $calculatedSize = $aggregateTx->size() -
      count($aggregateTx->cosignatures) * $sizePerCosignature +
      $calculatedCosignatures * $sizePerCosignature;
    $aggregateTx->fee = new Amount($calculatedSize * 100); // 手数料を設定


    \Drupal::logger('qls_ss4')->notice('<pre>@object</pre>', ['@object' => print_r($aggregateTx, TRUE)]);  

    // 署名
    $sig = $senderKey->signTransaction($aggregateTx);
    $payload = $facade->attachSignature($aggregateTx, $sig);
    \Drupal::logger('qls_ss4')->notice('<pre>@object</pre>', ['@object' => print_r($payload, TRUE)]); 
    

    $config = new Configuration();
    $config->setHost($node_url);
    $client = \Drupal::httpClient();
    $apiInstance = new TransactionRoutesApi($client, $config);

    try {
      $result = $apiInstance->announceTransaction($payload);
      // return $result;
      $this->messenger()->addMessage($this->t('Transaction successfully announced: @result', ['@result' => $result]));
    } catch (\Exception $e) {
      \Drupal::logger('qls_ss4')->error('トランザクションの発行中にエラーが発生しました: @message', ['@message' => $e->getMessage()]);
      // throw $e;
    }

    // try {
    //   // Drupal Serviceを使う方法
    //   // TransactionServiceを使ってトランザクションを発行
    //   $result = $this->transactionService->announceTransaction($node_url, $payload);
    //   $this->messenger()->addMessage($this->t('Transaction successfully announced: @result', ['@result' => $result]));
 
    // } catch (\Exception $e) {
    //   $this->messenger()->addError($this->t('Error: @message', ['@message' => $e->getMessage()]));
    // }

    

    // 4.4 確認
    // 4.4.1 ステータスの確認
    // ノードに受理されたトランザクションのステータスを確認
    // sleep(3);
    // アナウンススより先にステータスを確認しに行ってしまいエラーを返す可能性があるためのsleep
    
    
    // $txStatusApi = new TransactionStatusRoutesApi($client, $config);
    // try {
    //   $txStatus = $txStatusApi->getTransactionStatus($merkleHash);
    //   $this->messenger()->addMessage($this->t('Transaction Status: @txStatus', ['@txStatus' => $txStatus])); 
    //   \Drupal::logger('qls_ss4')->notice('<pre>@object</pre>', ['@object' => print_r($txStatus, TRUE)]); 
    // } catch (Exception $e) {
    //   // echo 'Exception when calling TransactionRoutesApi->announceTransaction:';
    //   // $e->getMessage();
    //   $this->messenger()->addError($this->t('Error: @message', ['@message' => $e->getMessage()])); 
    // }
  
  }

}
