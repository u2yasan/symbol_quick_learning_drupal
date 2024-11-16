<?php

namespace Drupal\qls_ss4\Service;

use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Configuration;
use GuzzleHttp\ClientInterface;

class TransactionService {

  /**
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * コンストラクタでGuzzleクライアントを注入
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   HTTPクライアント。
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * トランザクションを発行するメソッド
   *
   * @param string $nodeUrl
   *   SymbolノードのURL。
   * @param string $payload
   *   トランザクションペイロード。
   *
   * @return mixed
   *   トランザクション発行の結果。
   *
   * @throws \Exception
   */
  public function announceTransaction(string $nodeUrl, array $payload) {
    $config = new Configuration();
    $config->setHost($nodeUrl);

    $apiInstance = new TransactionRoutesApi($this->httpClient, $config);

    try {
      $result = $apiInstance->announceTransaction($payload);
      return $result;
    } catch (\Exception $e) {
      \Drupal::logger('qls_ss4')->error('トランザクションの発行中にエラーが発生しました: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

}