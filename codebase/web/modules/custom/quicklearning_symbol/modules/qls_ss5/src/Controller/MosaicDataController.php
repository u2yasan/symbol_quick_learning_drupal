<?php

namespace Drupal\qls_ss5\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class MosaicDataController {
  public function getMosaicData() {
    // $mocaisInfo 配列を定義または取得する。
    
    $session = \Drupal::service('session');
    $mosaicsInfo = $session->get('mosaic_flattened_data');
    if ($mosaicsInfo === NULL) {
      \Drupal::messenger()->addMessage(t('No mosaic data found in session.'));
    } else {
      \Drupal::logger('mosaic_data')->notice('<pre>@data</pre>', ['@data' => print_r($mosaicsInfo, TRUE)]);
    }

    return new JsonResponse($mosaicsInfo);
  }
}