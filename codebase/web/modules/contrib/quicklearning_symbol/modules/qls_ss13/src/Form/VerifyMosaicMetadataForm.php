<?php

namespace Drupal\qls_ss13\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Facade\SymbolFacade;

use SymbolRestClient\Configuration;
use SymbolRestClient\Api\MetadataRoutesApi;
use SymbolRestClient\Api\RestrictionMosaicRoutesApi;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\MultisigRoutesApi;
use SymbolRestClient\Api\NetworkRoutesApi;
use SymbolRestClient\Api\BlockRoutesApi;
use SymbolRestClient\Api\AccountRoutesApi;
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
use SymbolSdk\Symbol\Metadata;
use SymbolSdk\Symbol\Models\Address;


// use Drupal\quicklearning_symbol\Service\AccountService;

/**
 * Provides a form with two steps.
 *
 * This example demonstrates a multistep form with text input elements. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class VerifyMosaicMetadataForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'verify_mosaic_metadata_form';
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
      '#markup' => $this->t('13.3.2 モザイクへ登録したメタデータの検証'),
    ];

    $form['network_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Network Type'),
      '#description' => $this->t('Select either testnet or mainnet'),
      '#options' => [
        'testnet' => $this->t('Testnet'),
        'mainnet' => $this->t('Mainnet'),
      ],
      '#default_value' => 'testnet',
      '#required' => TRUE,
    ];

    $form['mosaic_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mosaic ID'),
      '#required' => TRUE,
    ];

    $form['raw_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Raw Address'),
      '#description' => $this->t('Enter the address to verify.'),
      '#required' => TRUE,
    ];

    $form['key_mosaic'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mosaic Metadata Key'),
      '#required' => TRUE,
    ];
    $form['value_mosaic'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mosaic Metadata Value'),
      '#required' => TRUE,
    ];

    // This container wil be replaced by AJAX.
    $form['container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'box-container'],
    ];
    // The box contains some markup that we can change on a submit request.
    $form['container']['box'] = [
      '#type' => 'markup',
      '#markup' => '<h1>Mosaic Metdata</h1>',
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

    $blockApiInstance = new BlockRoutesApi($client, $config);

    $addressRawAddress = $form_state->getValue('raw_address');
    $srcAddress = new Address($addressRawAddress);
    $targetAddress = new Address($addressRawAddress);


    $key_mosaic = $form_state->getValue('key_mosaic');
    $scopeKey = Metadata::metadataGenerateKey($key_mosaic); //メタデータキー
    $scopeKey = strtoupper(dechex($scopeKey));

    $targetId = $form_state->getValue('mosaic_id');

    $hasher = hash_init('sha3-256');
    hash_update($hasher, $srcAddress->binaryData);
    hash_update($hasher, $targetAddress->binaryData);
    hash_update($hasher, pack('C*', ...array_reverse($this->hexToUint8($scopeKey))));
    hash_update($hasher, pack('C*', ...array_reverse($this->hexToUint8($targetId))));
    hash_update($hasher, chr(1));

    $compositeHash = hash_final($hasher, true);

    $hasher = hash_init('sha3-256');
    hash_update($hasher, $compositeHash);
    $pathHash1 = strtoupper(bin2hex(hash_final($hasher, true)));

    //stateHash(Value値)
    $hasher = hash_init('sha3-256');
    $version = 1;
    hash_update($hasher, pack('C*', ...$this->hexToUint8($this->reverseHex($version, 2)))); //version
    hash_update($hasher, $srcAddress->binaryData);
    hash_update($hasher, $targetAddress->binaryData);
    hash_update($hasher, pack('C*', ...array_reverse($this->hexToUint8($scopeKey))));
    hash_update($hasher, pack('C*', ...array_reverse($this->hexToUint8($targetId))));
    hash_update($hasher, chr(1));

    $value = $form_state->getValue('value_mosaic');
    // $value = "test";
    $length = strlen($value);
    $hexLength = dechex($length);
    $paddedHex = str_pad($hexLength, 4, "0", STR_PAD_LEFT);

    hash_update($hasher, pack('C*', ...array_reverse($this->hexToUint8($paddedHex))));
    hash_update($hasher, $value);

    $stateHash1 = strtoupper(bin2hex(hash_final($hasher, true)));
    // echo "State Hash 1: " . $stateHash . PHP_EOL;

    //サービス提供者以外のノードから最新のブロックヘッダー情報を取得
    $blockInfo = $blockApiInstance->searchBlocks(order: 'desc');
    $rootHash1 = $blockInfo['data'][0]['meta']['state_hash_sub_cache_merkle_roots'][8];

    //サービス提供者を含む任意のノードからマークル情報を取得
    $metadataApiInstance = new MetadataRoutesApi($client, $config);
    $stateProof1 = $metadataApiInstance->getMetadataMerkle(bin2hex($compositeHash));

    //検証
    $result = $this->checkState($stateProof1, $stateHash1, $pathHash1, $rootHash1);

    $element = $form['container'];
    $element['box']['#markup'] = '<h1>Account Info</h1>'
    .'<h2>State Hash 1</h2> '.$stateHash1
    .'<h2>検証</h2> '.$result;
    // .'<h1>importanceブロックの検証</h1>'.print_r($hash === $blockInfo['meta']['hash'], TRUE)
    // .'<h1>AccountInfo</h1>'.print_r($accountInfo, TRUE)
    // .'<h1>stateHashの検証</h1>'.print_r($hash === $blockInfo['block']['state_hash'], TRUE);
    // $element['box']['#markup'] = $this->transactionToHtml($tx);
    return $element;
  }

 

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
    $leafHash = $this->getLeafHash($merkleLeaf['encoded_path'], $stateHash);

    $linkHash = $leafHash;  // リンクハッシュの初期値は葉ハッシュ
    $bit = "";
    for($i=0; $i <  count($merkleBranches); $i++){
      $branch = $merkleBranches[$i];
      $branchLink = array_filter($branch['links'], function($link) use ($linkHash) {
        return $link['link'] === $linkHash;
      });
      $branchLink = reset($branchLink); // 最初の要素を取得
      $linkHash = $this->getBranchHash($branch['encoded_path'], $branch['links']);
      $bit = substr($merkleBranches[$i]['path'], 0, $merkleBranches[$i]['nibble_count']) . $branchLink['bit'] . $bit;
    }
    $treeRootHash = $linkHash; //最後のlinkHashはrootHash
    $treePathHash = $bit . $merkleLeaf['path'];
    if(strlen($treePathHash) % 2 == 1){
      $treePathHash = substr($treePathHash, 0, -1);
    }

    // 検証
    // var_dump($treeRootHash === $rootHash);
    // var_dump($treePathHash === $pathHash);
    \Drupal::logger('qls_ss13')->info('treeRootHash === rootHash: ' . ($treeRootHash === $rootHash));
    \Drupal::logger('qls_ss13')->info('treePathHash === pathHash: ' . ($treePathHash === $pathHash));
    return $treeRootHash === $rootHash && $treePathHash === $pathHash;
    
  }

  public function hexToUint8($hex) {
    // 16進数文字列をバイナリデータに変換
    $binary = hex2bin($hex);
    // バイナリデータを配列に変換
    return array_values(unpack('C*', $binary));
  }

}