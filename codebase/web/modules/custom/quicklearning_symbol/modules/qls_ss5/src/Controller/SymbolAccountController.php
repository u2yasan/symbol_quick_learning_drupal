<?php

namespace Drupal\qls_ss5\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\your_module\Service\SymbolAccountService;

class SymbolAccountController extends ControllerBase {

  /**
   * SymbolAccountServiceのインスタンス
   *
   * @var \Drupal\your_module\Service\SymbolAccountService
   */
  protected $symbolAccountService;

  /**
   * コンストラクタでSymbolAccountServiceを注入
   */
  public function __construct(SymbolAccountService $symbol_account_service) {
    $this->symbolAccountService = $symbol_account_service;
  }

  /**
   * createメソッドでサービスコンテナから依存性を注入
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('qls_ss3.symbol_account_service')
    );
  }

  /**
   * アカウント情報を表示するページコールバック
   */
  public function accountInfo($address,$node_url) {
    // $node_url = 'http://sym-test-03.opening-line.jp:3000';
    // $alice_address = 'YOUR_ADDRESS_HERE';

    $accountInfo = $this->symbolAccountService->getAccountInfo($node_url, $address);

    return [
      '#markup' => $this->t('Account information: @info', [
        '@info' => print_r($accountInfo, TRUE),
      ]),
    ];
  }

}