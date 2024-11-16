<?php
namespace Drupal\qls_ss4\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\qls_ss4\Service\TransactionService;

class TransactionController extends ControllerBase {

  /**
   * トランザクションサービスのインスタンス
   *
   * @var \Drupal\qls_ss4\Service\TransactionService
   */
  protected $transactionService;

  /**
   * コンストラクタでサービスを注入
   */
  public function __construct(TransactionService $transaction_service) {
    $this->transactionService = $transaction_service;
  }

  /**
   * createメソッドで依存性を注入
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('qls_ss4.transaction_service')
    );
  }

  /**
   * トランザクションを発行して結果を表示する
   */
  public function announce($nodeUrl, $payload) {
    // $nodeUrl = 'http://your-node-url:3000';
    // $payload = 'your-payload-data';

    try {
      $result = $this->transactionService->announceTransaction($nodeUrl, $payload);
      return [
        '#markup' => $this->t('Transaction successfully announced: @result', ['@result' => $result]),
      ];
    } catch (\Exception $e) {
      return [
        '#markup' => $this->t('Error announcing transaction: @message', ['@message' => $e->getMessage()]),
      ];
    }
  }

}