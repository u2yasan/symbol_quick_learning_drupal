<?php
namespace Drupal\quicklearning_symbol\Service;

use SymbolRestClient\Api\AccountRoutesApi;
use SymbolRestClient\Configuration;
use GuzzleHttp\ClientInterface;

class AccountService {

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
   * @return array|null
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

      // $response = $accountApi->getAccountInfo($address);
      // $accountInfoJson = (string) $response->getBody();

      // \Drupal::logger('quicklearning_symbol')->info('Account Info JSON: @accountInfoJson', ['@accountInfoJson' => $accountInfoJson]);
      // $accountInfo = json_decode($accountInfoJson, true);
      // if (json_last_error() !== JSON_ERROR_NONE) {
      //   \Drupal::logger('quicklearning_symbol')->error('JSON decode error: @error', ['@error' => json_last_error_msg()]);
      //   return NULL;
      // }
      // \Drupal::logger('quicklearning_symbol')->info('Account Info: @accountInfo', ['@accountInfo' => $accountInfo]);
      // return $accountInfo['account'];
    } catch (\Exception $e) {
      \Drupal::logger('quicklearning_symbol')->error('Failed to get account info: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

}