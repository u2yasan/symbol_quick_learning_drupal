<?php

namespace Drupal\qls_ss13\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Facade\SymbolFacade;

use SymbolRestClient\Configuration;
use SymbolRestClient\Api\RestrictionMosaicRoutesApi;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\MultisigRoutesApi;
use SymbolRestClient\Api\NetworkRoutesApi;
use SymbolRestClient\Api\BlockRoutesApi;
use SymbolSdk\Symbol\Models\AccountAddressRestrictionTransactionV1;
use SymbolSdk\Symbol\Models\AccountRestrictionFlags;
use SymbolSdk\Symbol\Models\EmbeddedTransferTransactionV1;
use SymbolSdk\Symbol\Models\NamespaceRegistrationTransactionV1;
use SymbolSdk\Symbol\Models\AggregateBondedTransactionV2;
use SymbolSdk\Symbol\Models\AggregateCompleteTransactionV2;
use SymbolSdk\Symbol\Models\Hash256;
use SymbolSdk\Symbol\Models\HashLockTransactionV1;
use SymbolSdk\Symbol\Models\PublicKey;
// use SymbolSdk\Symbol\Models\Signature;
use SymbolSdk\CryptoTypes\Signature;
use SymbolSdk\Merkle\MerkleHashBuilder;
use SymbolSdk\Symbol\Models\Cosignature;
use SymbolSdk\Symbol\Models\DetachedCosignature;
use SymbolSdk\Symbol\Models\NetworkType;
use SymbolSdk\Symbol\Models\EmbeddedMultisigAccountModificationTransactionV1;
use SymbolSdk\Symbol\Models\Timestamp;
use SymbolSdk\Symbol\Models\Amount;
use SymbolSdk\Symbol\Models\TransactionFactory;
use SymbolSdk\Symbol\Models\UnresolvedAddress;
use SymbolSdk\Symbol\Models\UnresolvedMosaic;
use SymbolSdk\Symbol\Models\UnresolvedMosaicId;
use SymbolSdk\Symbol\Models\BlockDuration;
use SymbolSdk\Symbol\Models\NamespaceId;
use SymbolSdk\Symbol\Models\NamespaceRegistrationType;
use SymbolSdk\Symbol\IdGenerator;
use SymbolSdk\Merkle\Merkle;
use SymbolSdk\Symbol\Models\BlockType;


use Drupal\quicklearning_symbol\Service\AccountService;

/**
 * Provides a form with two steps.
 *
 * This example demonstrates a multistep form with text input elements. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class DeserializePayloadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'deserialize_payload_form';
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
      '#markup' => $this->t('13.1.2 payload 確認'),
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

    $form['payload'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Payload'),
      '#description' => $this->t('Input payload'),
      '#required' => TRUE,
    ];
    $form['height'] = [
      '#type' => 'number',
      '#title' => $this->t('Height'),
    ];

    // This container wil be replaced by AJAX.
    $form['container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'box-container'],
    ];
    // The box contains some markup that we can change on a submit request.
    $form['container']['box'] = [
      '#type' => 'markup',
      '#markup' => '<h1>Deserialized Payload</h1>',
    ];


    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#ajax' => [
        'callback' => '::promptCallback',
        'wrapper' => 'box-container',
      ],
    ];

    return $form;
  }

  
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    
  }

  public function promptCallback(array &$form, FormStateInterface $form_state) {
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
    // $apiInstance = new TransactionRoutesApi($client, $config);

    $payload = $form_state->getValue('payload');
    $tx = TransactionFactory::deserialize(hex2bin($payload));
    \Drupal::logger('qls_ss13')->info('tx: @tx', ['@tx' => print_r($tx, TRUE)]);
    $hash = $facade->hashTransaction($tx);

    // $signature = new Signature($tx->signature);
    // $res = $facade->verifyTransaction($tx, $signature);
    // \Drupal::logger('qls_ss13')->info('verify: @res', ['@res' => $res]);
    // $this->messenger()->addMessage($this->t('Signed Payload <pre>@signedPayload</pre>', ['@signedPayload' => print_r($signedPayload['payload'], TRUE)]));
   
    /**
     * マークルコンポーネントハッシュの計算
     */
    $merkleComponentHash = $hash;

    if (isset($tx->cosignatures) && count($tx->cosignatures) > 0) {
      $hasher = new MerkleHashBuilder();
      $hash = new Hash256($hash);
      $hasher->update($hash);
      foreach ($tx->cosignatures as $cosignature) {
        $hasher->update(new Hash256($cosignature->signerPublicKey));
      }
      $merkleComponentHash = $hasher->final();
    }
    // echo "===マークルコンポーネントハッシュ===" . PHP_EOL;
    // echo strtoupper($merkleComponentHash) . PHP_EOL;

    /**
     * InBlockの検証
     */

    $leafhash = new Hash256($merkleComponentHash);

    // ノードから取得
    // $config = new Configuration();
    // $config->setHost($NODE_URL);
    // $client = new GuzzleHttp\Client();
    $blockApiInstance = new BlockRoutesApi($client, $config);

    $height = $form_state->getValue('height');
    $HRoot = $blockApiInstance->getBlockByHeight($height);
    $HRootHash = new Hash256($HRoot["block"]["transactions_hash"]);

    $merkleProof = $blockApiInstance->getMerkleTransaction($height, $leafhash);
    $merklePath = $merkleProof["merkle_path"];

    $resutl = Merkle::proveMerkle($leafhash, $merklePath, $HRootHash);
    // echo "===InBlockの検証===" . PHP_EOL;
    // var_dump($resutl);

    $blockInfo = $blockApiInstance->getBlockByHeight($height);
    $block = $blockInfo["block"];
    $previousBlockHash = $blockApiInstance->getBlockByHeight($height - 1);
    $previousBlockHash = $previousBlockHash["meta"]["hash"];

    if ($block['type'] === BlockType::NORMAL) {
      $hasher = hash_init('sha3-256');

      hash_update($hasher, hex2bin($block['signature'])); // signature
      hash_update($hasher, hex2bin($block['signer_public_key'])); // publicKey

      hash_update($hasher, hex2bin($this->reverseHex($block['version'],1)));
      hash_update($hasher, hex2bin($this->reverseHex($block['network'], 1)));
      hash_update($hasher, hex2bin($this->reverseHex($block['type'], 2)));
      hash_update($hasher, hex2bin($this->reverseHex($block['height'], 8)));
      hash_update($hasher, hex2bin($this->reverseHex($block['timestamp'], 8)));
      hash_update($hasher, hex2bin($this->reverseHex($block['difficulty'], 8)));

      hash_update($hasher, hex2bin($block['proof_gamma']));
      hash_update($hasher, hex2bin($block['proof_verification_hash']));
      hash_update($hasher, hex2bin($block['proof_scalar']));
      hash_update($hasher, hex2bin($previousBlockHash));
      hash_update($hasher, hex2bin($block['transactions_hash']));
      hash_update($hasher, hex2bin($block['receipts_hash']));
      hash_update($hasher, hex2bin($block['state_hash']));
      hash_update($hasher, hex2bin($block['beneficiary_address']));
      hash_update($hasher, hex2bin($this->reverseHex($block['fee_multiplier'], 4)));

      $hash = strtoupper(bin2hex(hash_final($hasher, true)));

      // echo "===ブロックヘッダーの検証===" . PHP_EOL;
      // var_dump($hash === $blockInfo['meta']['hash']);
    }


    $element = $form['container'];
    $element['box']['#markup'] = '<h1>Deserialized Payload</h1>'.$tx
    .'<h1>hash</h1>'.$hash.'<h1>merkleComponentHash</h1>'.strtoupper($merkleComponentHash)
    .'<h1>InBlockの検証</h1>'.print_r($resutl, TRUE)
    .'<h1>ブロックヘッダーの検証</h1>'.print_r($hash === $blockInfo['meta']['hash'], TRUE);
    // .'<h1>署名の検証</h1>'.$res;
    // $element['box']['#markup'] = $this->transactionToHtml($tx);
    return $element;
  }

  // トランザクション情報をHTML表示形式に変換
//   public function transactionToHtml($tx) {
//   $html = '<div>';
//   $html .= '<h1>Transaction Details</h1>';
//   $html .= '<p><strong>Type:</strong> ' . htmlspecialchars($tx->type) . '</p>';
//   $html .= '<p><strong>Network:</strong> ' . htmlspecialchars($tx->networkType) . '</p>';
//   $html .= '<p><strong>Version:</strong> ' . htmlspecialchars($tx->version) . '</p>';
//   $html .= '<p><strong>Signer Public Key:</strong> ' . htmlspecialchars($tx->signerPublicKey) . '</p>';
//   $html .= '<p><strong>Fee:</strong> ' . htmlspecialchars($tx->maxFee) . '</p>';
//   $html .= '<p><strong>Deadline:</strong> ' . htmlspecialchars($tx->deadline) . '</p>';

//   // インナートランザクションの表示
//   if (isset($tx->innerTransactions)) {
//       $html .= '<h2>Inner Transactions</h2>';
//       foreach ($tx->innerTransactions as $index => $innerTx) {
//           $html .= '<div style="margin-left: 20px; border-left: 2px solid #ccc; padding-left: 10px;">';
//           $html .= '<h3>Inner Transaction #' . ($index + 1) . '</h3>';
//           $html .= '<p><strong>Type:</strong> ' . htmlspecialchars($innerTx->type) . '</p>';
//           $html .= '<p><strong>Signer Public Key:</strong> ' . htmlspecialchars($innerTx->signerPublicKey) . '</p>';

//           if (isset($innerTx->recipientAddress)) {
//               $html .= '<p><strong>Recipient Address:</strong> ' . htmlspecialchars($innerTx->recipientAddress) . '</p>';
//           }

//           if (isset($innerTx->message)) {
//               $html .= '<p><strong>Message:</strong> ' . htmlspecialchars($innerTx->message->payload) . '</p>';
//           }

//           $html .= '</div>';
//       }
//   }

//   // コサイン署名の表示
//   if (isset($tx->cosignatures)) {
//       $html .= '<h2>Cosignatures</h2>';
//       foreach ($tx->cosignatures as $index => $cosig) {
//           $html .= '<div style="margin-left: 20px; border-left: 2px solid #ccc; padding-left: 10px;">';
//           $html .= '<h3>Cosignature #' . ($index + 1) . '</h3>';
//           $html .= '<p><strong>Signer Public Key:</strong> ' . htmlspecialchars($cosig->signerPublicKey) . '</p>';
//           $html .= '<p><strong>Signature:</strong> ' . htmlspecialchars($cosig->signature) . '</p>';
//           $html .= '</div>';
//       }
//   }

//   $html .= '</div>';
//   return $html;
// }

  //検証用共通関数

  public function reverseHex($hex, $bytes = 1) {
    // 10進数を16進数に変換し、必要に応じてゼロパディング
    $hex = str_pad(dechex($hex), $bytes * 2, "0", STR_PAD_LEFT);
    // 16進数の文字列をバイナリデータに変換
    $bin = hex2bin($hex);
    // バイナリデータを逆順にする
    $reversed = strrev($bin);
    // バイナリデータを16進数の文字列に変換
    $reversedHex = bin2hex($reversed);
    return $reversedHex;
  }

  //葉のハッシュ値取得関数
  public function getLeafHash($encodedPath, $leafValue) {
    $hasher = hash_init('sha3-256');
    hash_update($hasher, hex2bin($encodedPath . $leafValue));
    $hash = strtoupper(bin2hex(hash_final($hasher, true)));
    return $hash;
  }
  // getLeafHash("200F84DD2830B37539EF766DD37A0DA6150FB8E14AEE2ED2773262F4AF14CF","39B9DF440E50AF995D7E8DD94FA38BF68033CC39053B8C9FA1BFC2AA25C99F91");

  // 枝のハッシュ値取得関数
  public function getBranchHash($encodedPath, $links) {
    $branchLinks = array_fill(0, 16, bin2hex(str_repeat(chr(0), 32)));
    foreach ($links as $link) {
        $index = hexdec($link['bit']);
        $branchLinks[$index] = $link['link'];
    }
    $concatenated = $encodedPath . implode("", $branchLinks);
    $hasher = hash_init('sha3-256');
    hash_update($hasher, hex2bin($concatenated));

    return strtoupper(bin2hex(hash_final($hasher, true)));
  }
  // $array = [
  //   ["bit" => 2, "link" => "513DB50C2C5D5ADFEE727C4DAB2FD15D67D445DACE0EE4A91D2316BB6B5184DB"],
  //   ["bit" => 4, "link" => "B6BFC203326047B79F350E8D397B7278B52B05B8061CBAE31A1E3E75EB374A11"],
  //   ["bit" => 5, "link" => "A196EDBEDD6025B5A1C91D07033067FD7C231954FFD68CA7845053F6E0DECDE9"],
  //   ["bit" => 6, "link" => "BE463563FE9855B88AC5EB5D4C648FCD0ACBF88FB470B4A156C6DF699469A7B0"],
  //   ["bit" => 7, "link" => "3879965782C68D7BAFD9A3A5318606AC7720633E05F16A74998C979CE7CF6F4D"],
  //   ["bit" => 8, "link" => "D92E9488B9A54EBF96760969B05E260B97E4FD0C4BCA87193ED88CC50C6A6610"],
  //   ["bit" => 9, "link" => "D5AD2127DD636D531F26609A6F0055BB5B607C978BF4E8C7E3515125A76A956B"],
  //   ["bit" => "A", "link" => "65DFF47A75A11595830C0B6E03E44DC3E869A57EFC51632350B4A999D69BB24E"],
  //   ["bit" => "B", "link" => "38E66E2F0B029AE498F6EE0DC22C4F2836AF9F0E02B7E4EDD94A13EF0451FDA5"],
  //   ["bit" => "C", "link" => "17F2795C8028161B541527BE46B12353CCADE3E01FCEF52C71FE5BC6AEDEA6B7"],
  //   ["bit" => "E", "link" => "E64C24870C17F255F96E19120929AF366C81ADCDFB585D9976F3F26C855EF71C"],
  //   ["bit" => "F", "link" => "AAC621E07225A53114FBDE5A4EA0C79860743C0122F25A26BAAD0638C697E5FD"]
  // ];
  // $res = getBranchHash('00', $array);
  // echo $res . PHP_EOL;

  // ワールドステートの検証

  public function checkState($stateProof, $stateHash, $pathHash, $rootHash) {
    $merkleLeaf = null;
    $merkleBranches = [];
    foreach($stateProof['tree'] as $n){
      if($n['type'] === 255){
        $merkleLeaf = $n;
      } else {
        $merkleBranches[] = $n;
      }
    }
    $merkleBranches = array_reverse($merkleBranches);
    $leafHash = getLeafHash($merkleLeaf['encoded_path'], $stateHash);

    $linkHash = $leafHash;  // リンクハッシュの初期値は葉ハッシュ
    $bit = "";
    for($i=0; $i <  count($merkleBranches); $i++){
      $branch = $merkleBranches[$i];
      $branchLink = array_filter($branch['links'], function($link) use ($linkHash) {
        return $link['link'] === $linkHash;
      });
      $branchLink = reset($branchLink); // 最初の要素を取得
      $linkHash = getBranchHash($branch['encoded_path'], $branch['links']);
      $bit = substr($merkleBranches[$i]['path'], 0, $merkleBranches[$i]['nibble_count']) . $branchLink['bit'] . $bit;
    }
    $treeRootHash = $linkHash; //最後のlinkHashはrootHash
    $treePathHash = $bit . $merkleLeaf['path'];
    if(strlen($treePathHash) % 2 == 1){
      $treePathHash = substr($treePathHash, 0, -1);
    }

    // 検証
    var_dump($treeRootHash === $rootHash);
    var_dump($treePathHash === $pathHash);

  }

  public function hexToUint8($hex) {
    // 16進数文字列をバイナリデータに変換
    $binary = hex2bin($hex);
    // バイナリデータを配列に変換
    return array_values(unpack('C*', $binary));
  }

}