<?php
// compute_matrix loading
$compute_matrix = file_get_contents('./compute_matrix.json');
$compute_matrix = json_decode($compute_matrix);

// cluster_matrix loading
$cluster_matrix = file_get_contents('./cluster_matrix.json');
$cluster_matrix = json_decode($cluster_matrix);

$websiteScoreAverageUser = array(
    0, 0, 0, 0, 0,
    0, 0, 0, 0, 0,
    0, 0, 0, 0, 0,
    0, 0, 0, 0, 0,
    0, 0, 0, 0, 0
  );

$websiteClusterName = '';
$websiteClusterScore = 100000;

function computingScore($cluster_matrix, $websiteScoreAverageUser, $websiteClusterScore, $websiteClusterName) {
  foreach ($cluster_matrix as $key => $cluster) {
    $score = 0;
    $final_score = 0;
    if (!empty($cluster)) {
      $cluster_val = $cluster;
      $cluster_name = $key;
      for ($i = 0; $i < count($websiteScoreAverageUser); $i++) {
        $score += pow($cluster_val[$i] - $websiteScoreAverageUser[$i], 2);
      }
      $final_score = sqrt($score);
      if ($final_score < $websiteClusterScore) {
        $websiteClusterScore = $final_score;
        $websiteClusterName = $key;
      }
    }
  }

  return array('websiteClusterScore' => $websiteClusterScore, 'websiteClusterName' => $websiteClusterName);
}

$pages_matrix = array();
foreach ($compute_matrix as $page => $cluster) {
  $score = computingScore($cluster_matrix, $cluster, $websiteClusterScore, $websiteClusterName);
  $pages_matrix[$score['websiteClusterName']][] = $page;
  //echo $page.'<br>';
  //echo $score['websiteClusterName'].'<br><br>';
}
ksort($pages_matrix);
echo json_encode($pages_matrix, JSON_PRETTY_PRINT);