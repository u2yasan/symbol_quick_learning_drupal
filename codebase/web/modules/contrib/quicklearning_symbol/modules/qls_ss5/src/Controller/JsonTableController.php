<?php

namespace Drupal\qls_ss5\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for displaying JSON data as a table.
 */
class JsonTableController extends ControllerBase {

  /**
   * Renders JSON data as a table.
   */
  public function renderTable() {
    // サンプルJSONデータ
    $json_data = [
      ['id' => 1, 'name' => 'Item One', 'value' => 100],
      ['id' => 2, 'name' => 'Item Two', 'value' => 200],
      ['id' => 3, 'name' => 'Item Three', 'value' => 300],
    ];

    // テンプレートへのデータ渡し
    return [
      '#theme' => 'json_table',
      '#data' => $json_data,
      '#headers' => ['ID', 'Name', 'Value'],
      '#attached' => [
        'library' => [
          'qls_ss5/json_table',
        ],
      ],
    ];
  }
}