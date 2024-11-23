<?php
namespace Drupal\qls_ss5\Plugin\views\query;

use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\ResultRow;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Provides a Views data source for mosaic data.
 *
 * @ViewsQuery(
 *   id = "mosaic_data_source",
 *   title = @Translation("Mosaic Data Source"),
 *   help = @Translation("Fetches Mosaic data from Symbol API for Views.")
 * )
 */
class MosaicDataSource extends QueryPluginBase {

  /**
   * {@inheritdoc}
   */
  public function execute(ViewExecutable $view) {
    // セッションサービスを取得
    $session = \Drupal::service('session');

    // データを取得（デフォルト値として空配列を設定）
    $flattenedData = $session->get('mosaic_flattened_data', []);

    if (!is_array($flattenedData)) {
      \Drupal::logger('qls_ss5')->error('Mosaic flattened data is not an array.');
      return;
    }
    if (empty($flattenedData)) {
      \Drupal::logger('qls_ss5')->warning('No mosaic data available in state storage.');
      return;
    }

    foreach ($flattenedData as $index => $mosaic) {
      $row = new ResultRow();
      $row->mosaic_id = $mosaic['id'];
      $row->supply = $mosaic['supply'];
      $row->owner_address = $mosaic['owner_address'];
      $row->divisibility = $mosaic['divisibility'];
      $row->flags = $mosaic['flags'];
      $row->duration = $mosaic['duration'];
      $row->start_height = $mosaic['start_height'];
      $view->result[] = $row;
    }
  }
}