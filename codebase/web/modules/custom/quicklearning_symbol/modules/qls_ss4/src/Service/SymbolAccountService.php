<?php

namespace Drupal\qls_ss4\Service;

use SymbolRestClient\Api\AccountRoutesApi;
use SymbolRestClient\Configuration;
use GuzzleHttp\ClientInterface;

class SymbolAccountService {

  /**
   * Guzzle HTTPクライアント
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * コンストラクタでGuzzleクライアントを注入
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * アカウント情報を取得するメソッド
   *
   * @param string $node_url
   *   ノードのURL
   * @param string $address
   *   アカウントのアドレス
   *
   * @return object
   *   アカウント情報
   */
  public function getAccountInfo($node_url, $address) {
    $config = new Configuration();
    $config->setHost($node_url);

    $accountApi = new AccountRoutesApi($this->httpClient, $config);

    // アカウント情報を取得
    try {
      $accountInfo = $accountApi->getAccountInfo($address);
      return $accountInfo;
    } catch (\Exception $e) {
      \Drupal::logger('qls_ss4')->error('Failed to get account info: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

}