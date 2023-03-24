<?php

$user = '';
if (!empty($_GET['userId']))
  $user = $_GET['userId'];
$path = '';
if (!empty($_GET['path']))
  $path = $_GET['path'];

$users = array(
  'JR3k4PQXC7GI',
  'JR3k4K80KUUA',
  'JR3y5SMBV2JW',
  'JR3k4WHX3IN6'
);

$event = array(
  'userId' => $user,
  'context' => array(
    'page' => array(
      'path' => $path
    )
  ),
  'properties' => array(
    'path' => $path
  )
);

$settings = array(
  'initialUserProfile' => array(
    0, 0, 0, 0, 0,
    0, 0, 0, 0, 0,
    0, 0, 0, 0, 0,
    0, 0, 0, 0, 0,
    0, 0, 0, 0, 0
  )
);

$resetUserSettings = array(
  'userId' => $user,
  'traits' => array(
    'websitePageVisitCount' => 0,
    'websiteScoreAverageUser' => $settings['initialUserProfile'],
    'websiteScoreAbsoluteUser' => $settings['initialUserProfile'],
    'websiteClusterName' => null
  )
);

$websitePageVisitCount = '';
if (!empty($_GET['websitePageVisitCount']))
  $websitePageVisitCount = $_GET['websitePageVisitCount'];
$websiteScoreAverageUser = $settings['initialUserProfile'];
if (!empty($_GET['websiteScoreAverageUser']))
  $websiteScoreAverageUser = $_GET['websiteScoreAverageUser'];
$websiteScoreAbsoluteUser = $settings['initialUserProfile'];
if (!empty($_GET['websiteScoreAbsoluteUser']))
  $websiteScoreAbsoluteUser = $_GET['websiteScoreAbsoluteUser'];
$websiteClusterName = '';
if (!empty($_GET['websiteClusterName']))
  $websiteClusterName = $_GET['websiteClusterName'];
// Trigger user update
if (!empty($_GET['userId']) && !empty($websitePageVisitCount) && !empty($websiteScoreAverageUser) && !empty($websiteScoreAbsoluteUser) && !empty($websiteClusterName))
  pushCluster($user, $websitePageVisitCount, $websiteScoreAverageUser, $websiteScoreAbsoluteUser, $websiteClusterName);
// Trigger cluster reset
if (!empty($_GET['userId']) && !empty($_GET['reset']))
  pushCluster($user, 0, null, null, null);


function fetchCluster($event, $settings) {
  $space_id = $_ENV['spaceId'];
  $space_key = $_ENV['spaceKey'];
  $user_id = $event['userId'];
  $endpoint =
    'https://profiles.segment.com/v1/spaces/' .
    $space_id .
    '/collections/users/profiles/user_id:' .
    $user_id .
    '/traits?include=websitePageVisitCount&include=websiteScoreAverageUser&include=websiteScoreAbsoluteUser&include=websiteClusterName';
  $headers = array(
    'Content-Type: application/json'
  );

  $ch = curl_init();
	try {
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_USERPWD, $space_key . ":");
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 5000);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		
		$response = json_decode(curl_exec($ch));
		
	  if (curl_errno($ch)) {
			echo curl_error($ch);
			die();
		}
		
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (empty($response))
      die("Empty response");
    if ($http_code != intval(200))
      die("API error: " . $http_code);

    return calculateCluster($event, $response, $settings);
	} catch (\Throwable $th) {
		throw $th;
	} finally {
		curl_close($ch);
	}
}

function pushCluster($user, $websitePageVisitCount, $websiteScoreAverageUser, $websiteScoreAbsoluteUser, $websiteClusterName) {
  $space_writeKey = $_ENV['writeKey'];
  $payload = array(
    'userId' => $user,
    'traits' => array(
      'websitePageVisitCount' => $websitePageVisitCount,
      'websiteScoreAverageUser' => (!empty($websiteScoreAverageUser))?json_decode($websiteScoreAverageUser):$websiteScoreAverageUser,
      'websiteScoreAbsoluteUser' => (!empty($websiteScoreAbsoluteUser))?json_decode($websiteScoreAbsoluteUser):$websiteScoreAbsoluteUser,
      'websiteClusterName' => $websiteClusterName
    )
  );
  $endpoint = 'https://api.segment.io/v1/identify';
  $headers = array(
    'Content-Type: application/json'
  );

	$ch = curl_init();
	try {
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_USERPWD, $space_writeKey . ":");
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 5000);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
    curl_setopt($ch, CURLOPT_POST, count($payload));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		
		$response = curl_exec($ch);
	  if (curl_errno($ch)) {
			echo curl_error($ch);
			die();
		}
		
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (empty($response))
      die("Empty response");
    if ($http_code != intval(200))
      die("Push fail: " . $http_code);

		echo "User with ID ".$user." updated";
	} catch (\Throwable $th) {
		throw $th;
	} finally {
		curl_close($ch);
	}
}

function calculateCluster($event, $data, $settings) {
  // compute_matrix loading
  $compute_matrix = file_get_contents('./compute_matrix.json');
  $compute_matrix = json_decode($compute_matrix);

  // cluster_matrix loading
  $cluster_matrix = file_get_contents('./cluster_matrix.json');
  $cluster_matrix = json_decode($cluster_matrix);

  $websitePageVisitCount = 0;
  $websiteScoreAverageUser = $settings['initialUserProfile'];
  $websiteScoreAbsoluteUser = $settings['initialUserProfile'];
  $websiteClusterName = '';
  $websiteClusterScore = 100000;

  // User current cluster score
  $current_page = '';
  if (!empty($data->traits->websitePageVisitCount))
    $websitePageVisitCount = $data->traits->websitePageVisitCount;
  if (!empty($data->traits->websiteScoreAverageUser))
    $websiteScoreAverageUser = $data->traits->websiteScoreAverageUser;
  if (!empty($data->traits->websiteScoreAbsoluteUser))
    $websiteScoreAbsoluteUser = $data->traits->websiteScoreAbsoluteUser;
  if (!empty($data->traits->websiteClusterName))
    $websiteClusterName = $data->traits->websiteClusterName;

  // Trigger computing for current score
  $computing = computingScore($cluster_matrix, $websiteScoreAverageUser, $websiteClusterScore, $websiteClusterName);
  $websiteClusterScore = $computing['websiteClusterScore'];
  $websiteClusterName = $computing['websiteClusterName'];
  
  echo "Current Cluster Score: " . $websiteClusterScore . "<br>";
  echo "Current Cluster Name: " . $websiteClusterName . "<br>";
  echo "Current Page Visit Count: " . $websitePageVisitCount . "<br>";
  echo scoreToTable($websiteScoreAbsoluteUser, 'Current Absolute Score');
  echo scoreToTable($websiteScoreAverageUser, 'Current Average Score');
  
  if (!empty($event['context']['page']))
    $current_page = $event['context']['page']['path'];
  else if (!empty($event['properties']['path']))
    $current_page = $event['properties']['path'];
  echo "<br>User visited " . $current_page . "<br>";

  if (!empty($compute_matrix->{$current_page})) {
    $websitePageVisitCount = $websitePageVisitCount + 1;
    $computeScore = $compute_matrix->{$current_page};
    for ($i = 0; $i < count($computeScore); $i++)
      $websiteScoreAbsoluteUser[$i] = $websiteScoreAbsoluteUser[$i] + $computeScore[$i];
    for ($i = 0; $i < count($computeScore); $i++)
      $websiteScoreAverageUser[$i] = $websiteScoreAbsoluteUser[$i] / $websitePageVisitCount;

    // Trigger computing for new score
    $computing = computingScore($cluster_matrix, $websiteScoreAverageUser, $websiteClusterScore, $websiteClusterName);
    $websiteClusterScore = $computing['websiteClusterScore'];
    $websiteClusterName = $computing['websiteClusterName'];

    echo "New Cluster Score: " . $websiteClusterScore . "<br>";
    echo "New Cluster Name: " . $websiteClusterName . "<br>";
    echo "New Page Visit Count: " . $websitePageVisitCount . "<br>";
    echo scoreToTable($computeScore, 'Compute score');
    echo scoreToTable($websiteScoreAbsoluteUser, 'New Absolute Score');
    echo scoreToTable($websiteScoreAverageUser, 'New Average Score');

    return array(
      'websitePageVisitCount' => $websitePageVisitCount,
      'websiteScoreAverageUser' => $websiteScoreAverageUser,
      'websiteScoreAbsoluteUser' => $websiteScoreAbsoluteUser,
      'websiteClusterName' => $websiteClusterName
    );
  }
}

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

function scoreToTable($array, $name) {
  $table  = '<table>';
  $table .= '<caption>'.$name.'</caption>';
  foreach ($array as $k => $v) {
    if ($k === 0)
      $table .= '<tr>';
    else if ($k % 5 === 0)
      $table .= '</tr><tr>';
    $table .= '<td>'.$v.'</td>';
    if ($k === count($array)-1) {
      $table .= '</tr>';
    }
  }
  $table .= '</table>';
  return $table;
}

function pickPagesInCluster($clusterName) {
  $pages_matrix = file_get_contents('./pages_matrix.json');
  $pages_matrix = json_decode($pages_matrix);
  if (property_exists($pages_matrix, $clusterName)) {
    fetchAlgoliaContents(array_rand(array_flip($pages_matrix->$clusterName), 15));// NEW > get 15 pages max
  } else
    echo 'No content for cluster '.$clusterName;
}

function fetchAlgoliaContents($pages) {
  $algoliaAppId = $_ENV['algoliaAppId'];
  $algoliaKey = $_ENV['algoliaKey'];
  $algoliaIndex = $_ENV['algoliaIndex'];
  $website = $_ENV['website'];
  $payload = '{"requests": [';
  foreach ($pages as $p)
    $payload .= '{"indexName": "'.$algoliaIndex.'","objectID": "'.$website.$p.'"},';
  $payload = trim($payload, ',');
  $payload .= ']}';
  $endpoint = 'https://v4xbg1o1ev.algolia.net/1/indexes/*/objects';
  $headers = array(
    'Content-Type: application/json',
    'X-Algolia-API-Key: '.$algoliaKey,
    'X-Algolia-Application-Id: '.$algoliaAppId
  );

	$ch = curl_init();
	try {
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 5000);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		
		$response = curl_exec($ch);
	  if (curl_errno($ch)) {
			echo curl_error($ch);
			die();
		}
		
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (empty($response))
      die("Empty response");
    if ($http_code != intval(200))
      die("Fetch fail: " . $http_code);

    // NEW filter contents to push only good entries and get only 5 of them
    $results = json_decode($response);
    $contents = array();
    $i = 0;
    foreach ($results->results as $r) {
      if (!empty($r->url) && !empty($r->image)) {
        $contents[] = $r;
        $i++;
      }
      if ($i == 5)
        break;
    }

    displayAlgoliaContents($contents);
	} catch (\Throwable $th) {
		throw $th;
	} finally {
		curl_close($ch);
	}
}

function displayAlgoliaContents($contents) {
  foreach ($contents as $c) {
    echo '<div style="margin-bottom:5px;"><a href="'.$c->url.'" title="'.$c->title.'" target="_blank" style="width:400px;height:100px;color:#1e647d;font-size:14px;font-weight:600;text-decoration:none;display:flex;align-items:center;"><img src="'.$c->image.'?fm=webp&fit=fill&w=105&h=100&q=70" style="margin-right:5px;">'.$c->title.'</a></div>';
  }
}

?>
<html>
  <head>
    <title>Cluster calculation</title>
    <link href="styles.css" rel="stylesheet" title="default" type="text/css">
  </head>
  <body>
    <h1>Calculate cluster</h1>
    <form method="get">
      <label>User ID</label> <input type="text" name="userId" value="" placeholder="Provide user ID"><br>
      <select name="path">
        <option value="">Select a path</option>
        <option value="/calendar-organizer">/calendar-organizer</option>
        <option value="/en-us/baby/newborn/article/2-month-old-baby">/en-us/baby/newborn/article/2-month-old-baby</option>
        <option value="/en-us/guides-and-downloadables/new-parents-guide">/en-us/guides-and-downloadables/new-parents-guide</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/amniocentesis">/en-us/pregnancy/prenatal-health-and-wellness/article/amniocentesis</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/gestational-diabetes">/en-us/pregnancy/prenatal-health-and-wellness/article/gestational-diabetes</option>
        <option value="/en-us/baby/parenting-life/article/how-to-meditate-mommy-me-time">/en-us/baby/parenting-life/article/how-to-meditate-mommy-me-time</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/5-pregnancy-tips-from-my-best-friend">/en-us/pregnancy/prenatal-health-and-wellness/article/5-pregnancy-tips-from-my-best-friend</option>
        <option value="/en-us/baby/newborn/article/premature-babies-development">/en-us/baby/newborn/article/premature-babies-development</option>
        <option value="/en-us/pregnancy/multiple-pregnancy/article/the-first-few-weeks-at-home-with-twins">/en-us/pregnancy/multiple-pregnancy/article/the-first-few-weeks-at-home-with-twins</option>
        <option value="/en-us/best-baby-products/health-safety/best-diaper-rash-creams">/en-us/best-baby-products/health-safety/best-diaper-rash-creams</option>
        <option value="/en-us/r-ratings-and-reviews">/en-us/r-ratings-and-reviews</option>
        <option value="/en-us/pregnancy/giving-birth/article/vaginal-birth">/en-us/pregnancy/giving-birth/article/vaginal-birth</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/toxoplasmosis">/en-us/pregnancy/prenatal-health-and-wellness/article/toxoplasmosis</option>
        <option value="/en-us/about-us/quality-and-safety/article/our-dedication-to-safety">/en-us/about-us/quality-and-safety/article/our-dedication-to-safety</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/placenta-previa-what-is-it-and-what-to-do">/en-us/pregnancy/prenatal-health-and-wellness/article/placenta-previa-what-is-it-and-what-to-do</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/running-while-pregnant">/en-us/pregnancy/prenatal-health-and-wellness/article/running-while-pregnant</option>
        <option value="/en-us/toddler/activities/article/toddler-activities-leaf-rubbing">/en-us/toddler/activities/article/toddler-activities-leaf-rubbing</option>
        <option value="/en-us/pregnancy/giving-birth/article/how-to-time-contractions">/en-us/pregnancy/giving-birth/article/how-to-time-contractions</option>
        <option value="/en-us/diapers-wipes/pampers-expressions-wipes">/en-us/diapers-wipes/pampers-expressions-wipes</option>
        <option value="/en-us/about-us/big-acts-of-love/big-love-for-low-carbon">/en-us/about-us/big-acts-of-love/big-love-for-low-carbon</option>
        <option value="/en-us/best-baby-products/infant-activity/best-baby-gear">/en-us/best-baby-products/infant-activity/best-baby-gear</option>
        <option value="/en-us/quizzes/gender-reveal-party-theme">/en-us/quizzes/gender-reveal-party-theme</option>
        <option value="/en-us/pregnancy/preparing-for-your-new-baby/article/newborn-baby-checklist">/en-us/pregnancy/preparing-for-your-new-baby/article/newborn-baby-checklist</option>
        <option value="/en-us/baby/newborn/article/torticollis-in-babies">/en-us/baby/newborn/article/torticollis-in-babies</option>
        <option value="/en-us/baby/diapering/article/how-often-to-change-diaper">/en-us/baby/diapering/article/how-often-to-change-diaper</option>
        <option value="/en-us/baby/care/article/car-safety-vehicular-heatstroke">/en-us/baby/care/article/car-safety-vehicular-heatstroke</option>
        <option value="/en-us/diapers-wipes/swaddlers-sweet-dreams-wipes/reviews">/en-us/diapers-wipes/swaddlers-sweet-dreams-wipes/reviews</option>
        <option value="/en-us/baby/parenting-life/article/what-are-the-signs-of-postpartum-depression">/en-us/baby/parenting-life/article/what-are-the-signs-of-postpartum-depression</option>
        <option value="/en-us/baby/development/article/encouraging-independence-in-children">/en-us/baby/development/article/encouraging-independence-in-children</option>
        <option value="/en-us/best-baby-products/feeding/best-baby-bottles">/en-us/best-baby-products/feeding/best-baby-bottles</option>
        <option value="/en-us/about-us/authors/kathy-cline">/en-us/about-us/authors/kathy-cline</option>
        <option value="/en-us/baby/newborn/article/nicu-equipment-what-you-can-expect-to-find">/en-us/baby/newborn/article/nicu-equipment-what-you-can-expect-to-find</option>
        <option value="/en-us/about-us/quality-and-safety/article/faq">/en-us/about-us/quality-and-safety/article/faq</option>
        <option value="/en-us/about-us/pampers-heritage">/en-us/about-us/pampers-heritage</option>
        <option value="/en-us/best-baby-products/strollers/best-umbrella-stroller">/en-us/best-baby-products/strollers/best-umbrella-stroller</option>
        <option value="/en-us/baby/parenting-life/article/making-friends-with-other-moms">/en-us/baby/parenting-life/article/making-friends-with-other-moms</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-registry-must-haves">/en-us/pregnancy/baby-shower/article/baby-registry-must-haves</option>
        <option value="/en-us/pregnancy/baby-shower/article/choosing-a-baby-carrier">/en-us/pregnancy/baby-shower/article/choosing-a-baby-carrier</option>
        <option value="/en-us/pregnancy/baby-shower/article/how-to-choose-a-car-seat">/en-us/pregnancy/baby-shower/article/how-to-choose-a-car-seat</option>
        <option value="/en-us/pregnancy/baby-shower/article/how-to-choose-a-crib">/en-us/pregnancy/baby-shower/article/how-to-choose-a-crib</option>
        <option value="/en-us/pregnancy/baby-shower/article/how-to-choose-a-stroller">/en-us/pregnancy/baby-shower/article/how-to-choose-a-stroller</option>
        <option value="/en-us/pregnancy/pregnancy-announcement/article/21-gender-reveal-ideas">/en-us/pregnancy/pregnancy-announcement/article/21-gender-reveal-ideas</option>
        <option value="/en-us/toddler/activities">/en-us/toddler/activities</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/what-is-implantation-bleeding">/en-us/pregnancy/pregnancy-symptoms/article/what-is-implantation-bleeding</option>
        <option value="/en-us/best-baby-products/nursery">/en-us/best-baby-products/nursery</option>
        <option value="/en-us/pregnancy/baby-names/article/presidential-baby-names">/en-us/pregnancy/baby-names/article/presidential-baby-names</option>
        <option value="/en-us/pregnancy/baby-shower/planning-checklist">/en-us/pregnancy/baby-shower/planning-checklist</option>
        <option value="/en-us/pregnancy/baby-names/article/flower-names-for-girls">/en-us/pregnancy/baby-names/article/flower-names-for-girls</option>
        <option value="/en-us/pregnancy/baby-names/article/hawaiian-girl-names">/en-us/pregnancy/baby-names/article/hawaiian-girl-names</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/group-b-strep">/en-us/pregnancy/prenatal-health-and-wellness/article/group-b-strep</option>
        <option value="/en-us/toddler/parenting-life">/en-us/toddler/parenting-life</option>
        <option value="/en-us/diapers-wipes/pampers-sensitive-wipes/reviews">/en-us/diapers-wipes/pampers-sensitive-wipes/reviews</option>
        <option value="/en-us/baby/activities/article/flying-with-a-baby">/en-us/baby/activities/article/flying-with-a-baby</option>
        <option value="/en-us/baby/activities/article/working-from-home-with-baby">/en-us/baby/activities/article/working-from-home-with-baby</option>
        <option value="/en-us/baby/parenting-life/article/baby-proofing-your-home">/en-us/baby/parenting-life/article/baby-proofing-your-home</option>
        <option value="/en-us/baby/parenting-life/article/stay-at-home-mom">/en-us/baby/parenting-life/article/stay-at-home-mom</option>
        <option value="/en-us/baby/sleep/article/adjusting-babys-sleep-for-daylight-savings-time">/en-us/baby/sleep/article/adjusting-babys-sleep-for-daylight-savings-time</option>
        <option value="/en-us/baby/sleep/article/baby-sleep-cycles">/en-us/baby/sleep/article/baby-sleep-cycles</option>
        <option value="/en-us/baby/sleep/article/baby-sleep-temperature">/en-us/baby/sleep/article/baby-sleep-temperature</option>
        <option value="/en-us/baby/sleep/article/baby-sleep-training">/en-us/baby/sleep/article/baby-sleep-training</option>
        <option value="/en-us/baby/sleep/article/baby-sleeping-on-side">/en-us/baby/sleep/article/baby-sleeping-on-side</option>
        <option value="/en-us/baby/sleep/article/baby-sleeping-on-stomach">/en-us/baby/sleep/article/baby-sleeping-on-stomach</option>
        <option value="/en-us/baby/sleep/article/common-misconceptions-about-baby-sleep">/en-us/baby/sleep/article/common-misconceptions-about-baby-sleep</option>
        <option value="/en-us/baby/sleep/article/daylight-saving-time-springing-forward-smoothly">/en-us/baby/sleep/article/daylight-saving-time-springing-forward-smoothly</option>
        <option value="/en-us/baby/sleep/article/expert-advice-adjusting-babys-sleep-for-daylight-savings">/en-us/baby/sleep/article/expert-advice-adjusting-babys-sleep-for-daylight-savings</option>
        <option value="/en-us/baby/sleep/article/how-to-get-baby-to-sleep-in-crib">/en-us/baby/sleep/article/how-to-get-baby-to-sleep-in-crib</option>
        <option value="/en-us/baby/sleep/article/how-to-put-a-baby-to-sleep">/en-us/baby/sleep/article/how-to-put-a-baby-to-sleep</option>
        <option value="/en-us/baby/sleep/article/how-to-stop-swaddling">/en-us/baby/sleep/article/how-to-stop-swaddling</option>
        <option value="/en-us/baby/sleep/article/how-to-swaddle-a-baby">/en-us/baby/sleep/article/how-to-swaddle-a-baby</option>
        <option value="/en-us/baby/sleep/article/lullaby-songs">/en-us/baby/sleep/article/lullaby-songs</option>
        <option value="/en-us/baby/sleep/article/nap-schedules">/en-us/baby/sleep/article/nap-schedules</option>
        <option value="/en-us/baby/sleep/article/newborn-sleep">/en-us/baby/sleep/article/newborn-sleep</option>
        <option value="/en-us/baby/sleep/article/newborn-sleep-how-much-should-a-newborn-sleep">/en-us/baby/sleep/article/newborn-sleep-how-much-should-a-newborn-sleep</option>
        <option value="/en-us/baby/sleep/article/sleep-regression">/en-us/baby/sleep/article/sleep-regression</option>
        <option value="/en-us/baby/sleep/article/sleep-safety-ensuring-a-safe-nights-sleep-for-your-baby">/en-us/baby/sleep/article/sleep-safety-ensuring-a-safe-nights-sleep-for-your-baby</option>
        <option value="/en-us/baby/sleep/article/sleep-training-a-good-bedtime-routine">/en-us/baby/sleep/article/sleep-training-a-good-bedtime-routine</option>
        <option value="/en-us/baby/sleep/article/what-is-sids-and-how-to-reduce-the-risk">/en-us/baby/sleep/article/what-is-sids-and-how-to-reduce-the-risk</option>
        <option value="/en-us/baby/sleep/article/what-to-do-when-your-child-wakes-up-too-early">/en-us/baby/sleep/article/what-to-do-when-your-child-wakes-up-too-early</option>
        <option value="/en-us/baby/sleep/article/when-can-baby-sleep-with-blanket">/en-us/baby/sleep/article/when-can-baby-sleep-with-blanket</option>
        <option value="/en-us/baby/sleep/article/when-do-babies-sleep-through-the-night">/en-us/baby/sleep/article/when-do-babies-sleep-through-the-night</option>
        <option value="/en-us/baby/sleep/article/why-baby-bedtime-is-important">/en-us/baby/sleep/article/why-baby-bedtime-is-important</option>
        <option value="/en-us/baby/development/article/when-do-babies-eyes-change-color">/en-us/baby/development/article/when-do-babies-eyes-change-color</option>
        <option value="/en-us/sitemap">/en-us/sitemap</option>
        <option value="/en-us/pregnancy/baby-names/article/twin-baby-names">/en-us/pregnancy/baby-names/article/twin-baby-names</option>
        <option value="/en-us/best-baby-products/feeding/best-sippy-cups">/en-us/best-baby-products/feeding/best-sippy-cups</option>
        <option value="/en-us/pregnancy/baby-names/article/baby-name-predictions-for-the-new-prince-or-princess">/en-us/pregnancy/baby-names/article/baby-name-predictions-for-the-new-prince-or-princess</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/pregnancy-weight-gain-facts-and-advice">/en-us/pregnancy/prenatal-health-and-wellness/article/pregnancy-weight-gain-facts-and-advice</option>
        <option value="/en-us/best-baby-products/infant-activity/best-2018-baby-gear">/en-us/best-baby-products/infant-activity/best-2018-baby-gear</option>
        <option value="/en-US/pregnancy/baby-names/article/top-baby-names-for-boys">/en-US/pregnancy/baby-names/article/top-baby-names-for-boys</option>
        <option value="/en-us/baby/care/article/baby-first-aid-kit">/en-us/baby/care/article/baby-first-aid-kit</option>
        <option value="/en-us/about-us/authors/kim-west">/en-us/about-us/authors/kim-west</option>
        <option value="/en-us/pregnancy/giving-birth/videos/how-to-prepare-your-babys-nursery">/en-us/pregnancy/giving-birth/videos/how-to-prepare-your-babys-nursery</option>
        <option value="/en-us/guides-and-downloadables/birth-plan-guide">/en-us/guides-and-downloadables/birth-plan-guide</option>
        <option value="/en-us/pregnancy/preparing-for-your-new-baby">/en-us/pregnancy/preparing-for-your-new-baby</option>
        <option value="/en-us/edit-profile">/en-us/edit-profile</option>
        <option value="/test-baby-birth-trivia">/test-baby-birth-trivia</option>
        <option value="/en-us/about-us/diapers-and-wipes/article/pampers-pure-hybrid-faq">/en-us/about-us/diapers-and-wipes/article/pampers-pure-hybrid-faq</option>
        <option value="/en-us/quizzes/early-signs-of-pregnancy-quiz">/en-us/quizzes/early-signs-of-pregnancy-quiz</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/what-not-to-eat-when-pregnant">/en-us/pregnancy/prenatal-health-and-wellness/article/what-not-to-eat-when-pregnant</option>
        <option value="/en-us/pregnancy/giving-birth/article/breech-baby">/en-us/pregnancy/giving-birth/article/breech-baby</option>
        <option value="/en-us/pregnancy/giving-birth/article/postpartum-hair-loss">/en-us/pregnancy/giving-birth/article/postpartum-hair-loss</option>
        <option value="/en-us/rewards/faq">/en-us/rewards/faq</option>
        <option value="/en-us/baby/care/article/baby-skin-care">/en-us/baby/care/article/baby-skin-care</option>
        <option value="/en-us/ergobaby-gift">/en-us/ergobaby-gift</option>
        <option value="/en-us/diaper-wipes-pure/pampers-aqua-pure-wipes">/en-us/diaper-wipes-pure/pampers-aqua-pure-wipes</option>
        <option value="/en-us/pregnancy/pregnancy-announcement/videos/creative-pregnancy-announcement-ideas-old">/en-us/pregnancy/pregnancy-announcement/videos/creative-pregnancy-announcement-ideas-old</option>
        <option value="/en-us/diapers-wipes/swaddlers-sweet-dreams-wipes">/en-us/diapers-wipes/swaddlers-sweet-dreams-wipes</option>
        <option value="/en-us/pregnancy/giving-birth/article/what-is-apgar-score">/en-us/pregnancy/giving-birth/article/what-is-apgar-score</option>
        <option value="/en-us/pregnancy/baby-names/article/mythological-baby-names">/en-us/pregnancy/baby-names/article/mythological-baby-names</option>
        <option value="/en-us/diapers-wipes/pampers-cruisers-diapers">/en-us/diapers-wipes/pampers-cruisers-diapers</option>
        <option value="/en-us/about-us/authors/vera-sweeny">/en-us/about-us/authors/vera-sweeny</option>
        <option value="/en-us/baby/development/article/7-month-old-baby">/en-us/baby/development/article/7-month-old-baby</option>
        <option value="/en-us/best-baby-products/health-safety">/en-us/best-baby-products/health-safety</option>
        <option value="/en-us/pregnancy/multiple-pregnancy/article/twin-pregnancy-week-by-week">/en-us/pregnancy/multiple-pregnancy/article/twin-pregnancy-week-by-week</option>
        <option value="/en-us/baby/activities/article/baby-games">/en-us/baby/activities/article/baby-games</option>
        <option value="/en-us/baby/activities/article/motor-skills-development-playing-catch-baby-style">/en-us/baby/activities/article/motor-skills-development-playing-catch-baby-style</option>
        <option value="/en-us/baby/care/article/ingrown-toenail-on-baby-what-to-do">/en-us/baby/care/article/ingrown-toenail-on-baby-what-to-do</option>
        <option value="/en-us/baby/care/article/ringworm-in-babies">/en-us/baby/care/article/ringworm-in-babies</option>
        <option value="/en-us/baby/care/article/will-the-smell-of-paint-harm-my-child">/en-us/baby/care/article/will-the-smell-of-paint-harm-my-child</option>
        <option value="/en-us/baby/development/article/baby-fine-motor-skills">/en-us/baby/development/article/baby-fine-motor-skills</option>
        <option value="/en-us/baby/development/article/common-childhood-illnesses-sensible-solutions-and-treatments">/en-us/baby/development/article/common-childhood-illnesses-sensible-solutions-and-treatments</option>
        <option value="/en-us/baby/diapering/article/meconium">/en-us/baby/diapering/article/meconium</option>
        <option value="/en-us/baby/feeding/article/baby-food-allergies">/en-us/baby/feeding/article/baby-food-allergies</option>
        <option value="/en-us/baby/feeding/article/when-can-babies-have-honey">/en-us/baby/feeding/article/when-can-babies-have-honey</option>
        <option value="/en-us/baby/growth-chart-calculator">/en-us/baby/growth-chart-calculator</option>
        <option value="/en-us/baby/growth-chart-calculator-results">/en-us/baby/growth-chart-calculator-results</option>
        <option value="/en-us/baby/health">/en-us/baby/health</option>
        <option value="/en-us/baby/health/article/anemia-in-babies-and-infants">/en-us/baby/health/article/anemia-in-babies-and-infants</option>
        <option value="/en-us/baby/health/article/baby-arching-back">/en-us/baby/health/article/baby-arching-back</option>
        <option value="/en-us/baby/health/article/baby-birthmarks-port-wine-stain-or-hemangiomas">/en-us/baby/health/article/baby-birthmarks-port-wine-stain-or-hemangiomas</option>
        <option value="/en-us/baby/health/article/baby-congestion">/en-us/baby/health/article/baby-congestion</option>
        <option value="/en-us/baby/health/article/baby-constipation">/en-us/baby/health/article/baby-constipation</option>
        <option value="/en-us/baby/health/article/baby-ear-infections">/en-us/baby/health/article/baby-ear-infections</option>
        <option value="/en-us/baby/health/article/baby-eczema">/en-us/baby/health/article/baby-eczema</option>
        <option value="/en-us/baby/health/article/baby-growth-chart">/en-us/baby/health/article/baby-growth-chart</option>
        <option value="/en-us/baby/health/article/baby-heat-rash">/en-us/baby/health/article/baby-heat-rash</option>
        <option value="/en-us/baby/health/article/baby-hiccups">/en-us/baby/health/article/baby-hiccups</option>
        <option value="/en-us/baby/health/article/baby-rash">/en-us/baby/health/article/baby-rash</option>
        <option value="/en-us/baby/health/article/baby-skin-care">/en-us/baby/health/article/baby-skin-care</option>
        <option value="/en-us/baby/health/article/chicken-pox">/en-us/baby/health/article/chicken-pox</option>
        <option value="/en-us/baby/health/article/coughs-and-coughing-in-babies-and-toddlers">/en-us/baby/health/article/coughs-and-coughing-in-babies-and-toddlers</option>
        <option value="/en-us/baby/health/article/croup-in-babies">/en-us/baby/health/article/croup-in-babies</option>
        <option value="/en-us/baby/health/article/dealing-with-diarrhea-helping-your-child-find-relief">/en-us/baby/health/article/dealing-with-diarrhea-helping-your-child-find-relief</option>
        <option value="/en-us/baby/health/article/dealing-with-fever-in-newborn-and-babies">/en-us/baby/health/article/dealing-with-fever-in-newborn-and-babies</option>
        <option value="/en-us/baby/health/article/dehydration-babies">/en-us/baby/health/article/dehydration-babies</option>
        <option value="/en-us/baby/health/article/drool-rash">/en-us/baby/health/article/drool-rash</option>
        <option value="/en-us/baby/health/article/fifth-disease">/en-us/baby/health/article/fifth-disease</option>
        <option value="/en-us/baby/health/article/hand-foot-mouth-disease">/en-us/baby/health/article/hand-foot-mouth-disease</option>
        <option value="/en-us/baby/health/article/ingrown-toenail-on-baby-what-to-do">/en-us/baby/health/article/ingrown-toenail-on-baby-what-to-do</option>
        <option value="/en-us/baby/health/article/newborn-baby-skin-peeling">/en-us/baby/health/article/newborn-baby-skin-peeling</option>
        <option value="/en-us/baby/health/article/pediatricians-partnering-with-your-healthcare-provider">/en-us/baby/health/article/pediatricians-partnering-with-your-healthcare-provider</option>
        <option value="/en-us/baby/health/article/pinkeye-conjunctivitis-in-babies">/en-us/baby/health/article/pinkeye-conjunctivitis-in-babies</option>
        <option value="/en-us/baby/health/article/pneumonia-in-babies">/en-us/baby/health/article/pneumonia-in-babies</option>
        <option value="/en-us/baby/health/article/reflux-in-babies">/en-us/baby/health/article/reflux-in-babies</option>
        <option value="/en-us/baby/health/article/ringworm-in-babies">/en-us/baby/health/article/ringworm-in-babies</option>
        <option value="/en-us/baby/health/article/roseola-signs-symptoms-and-treatment">/en-us/baby/health/article/roseola-signs-symptoms-and-treatment</option>
        <option value="/en-us/baby/health/article/rsv-in-infants">/en-us/baby/health/article/rsv-in-infants</option>
        <option value="/en-us/baby/health/article/stork-bite">/en-us/baby/health/article/stork-bite</option>
        <option value="/en-us/baby/health/article/strep-throat-in-babies">/en-us/baby/health/article/strep-throat-in-babies</option>
        <option value="/en-us/baby/health/article/thrush-in-babies">/en-us/baby/health/article/thrush-in-babies</option>
        <option value="/en-us/baby/health/article/tongue-tied-babies">/en-us/baby/health/article/tongue-tied-babies</option>
        <option value="/en-us/baby/health/article/vaccines-and-vaccination-child-immunization-schedule">/en-us/baby/health/article/vaccines-and-vaccination-child-immunization-schedule</option>
        <option value="/en-us/baby/health/article/well-baby-visit-1-month-baby-check-up">/en-us/baby/health/article/well-baby-visit-1-month-baby-check-up</option>
        <option value="/en-us/baby/health/article/well-baby-visit-1-year-checkup">/en-us/baby/health/article/well-baby-visit-1-year-checkup</option>
        <option value="/en-us/baby/health/article/well-baby-visit-4-months">/en-us/baby/health/article/well-baby-visit-4-months</option>
        <option value="/en-us/baby/health/article/whooping-cough">/en-us/baby/health/article/whooping-cough</option>
        <option value="/en-us/baby/health/article/your-babys-2-month-checkup">/en-us/baby/health/article/your-babys-2-month-checkup</option>
        <option value="/en-us/baby/health/article/your-babys-6-month-checkup">/en-us/baby/health/article/your-babys-6-month-checkup</option>
        <option value="/en-us/baby/health/article/your-babys-9-month-checkup">/en-us/baby/health/article/your-babys-9-month-checkup</option>
        <option value="/en-us/baby/newborn/article/cradle-cap">/en-us/baby/newborn/article/cradle-cap</option>
        <option value="/en-us/baby/newborn/article/gassy-baby">/en-us/baby/newborn/article/gassy-baby</option>
        <option value="/en-us/baby/newborn/article/jaundice">/en-us/baby/newborn/article/jaundice</option>
        <option value="/en-us/baby/newborn/article/newborn-sneezing">/en-us/baby/newborn/article/newborn-sneezing</option>
        <option value="/en-us/baby/newborn/article/what-is-colic-symptoms-and-remedies">/en-us/baby/newborn/article/what-is-colic-symptoms-and-remedies</option>
        <option value="/en-us/r-ergobaby-gift">/en-us/r-ergobaby-gift</option>
        <option value="/en-us/pregnancy/preparing-for-your-new-baby/article/nursery-theme-ideas-and-decorations">/en-us/pregnancy/preparing-for-your-new-baby/article/nursery-theme-ideas-and-decorations</option>
        <option value="/en-us/baby/care/article/massage-for-your-baby">/en-us/baby/care/article/massage-for-your-baby</option>
        <option value="/en-us/baby/care/article/newborn-baby-skin-peeling">/en-us/baby/care/article/newborn-baby-skin-peeling</option>
        <option value="/en-us/guides-and-downloadables/contraction-tracking-chart">/en-us/guides-and-downloadables/contraction-tracking-chart</option>
        <option value="/en-us/about-us/authors/shannon-mcavoy">/en-us/about-us/authors/shannon-mcavoy</option>
        <option value="/en-us/pregnancy/baby-names/article/celebrity-baby-names">/en-us/pregnancy/baby-names/article/celebrity-baby-names</option>
        <option value="/en-us/about-us/pampers-unicef-partnership/videos/eliminating-maternal-and-newborn-tetanus">/en-us/about-us/pampers-unicef-partnership/videos/eliminating-maternal-and-newborn-tetanus</option>
        <option value="/en-us/best-baby-products/infant-activity">/en-us/best-baby-products/infant-activity</option>
        <option value="/en-us/baby/newborn/article/tummy-time">/en-us/baby/newborn/article/tummy-time</option>
        <option value="/en-us/pregnancy/baby-names/article/middle-names-for-girls">/en-us/pregnancy/baby-names/article/middle-names-for-girls</option>
        <option value="/en-us/about-us/authors/madeline-johnson">/en-us/about-us/authors/madeline-johnson</option>
        <option value="/en-us/toddler/activities/article/how-toddlers-learn-through-play">/en-us/toddler/activities/article/how-toddlers-learn-through-play</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/signs-of-pregnancy">/en-us/pregnancy/pregnancy-symptoms/article/signs-of-pregnancy</option>
        <option value="/en-us/baby/diapering/article/diaper-bag-checklist">/en-us/baby/diapering/article/diaper-bag-checklist</option>
        <option value="/en-us/baby/parenting-life/article/11-inspirational-quotes-about-a-mothers-love">/en-us/baby/parenting-life/article/11-inspirational-quotes-about-a-mothers-love</option>
        <option value="/en-us/pregnancy/giving-birth/article/what-do-contractions-feel-like">/en-us/pregnancy/giving-birth/article/what-do-contractions-feel-like</option>
        <option value="/en-us/best-baby-products/travel-gear">/en-us/best-baby-products/travel-gear</option>
        <option value="/en-us/baby/development/article/baby-talk-making-sense-of-it-all">/en-us/baby/development/article/baby-talk-making-sense-of-it-all</option>
        <option value="/en-us/pregnancy/multiple-pregnancy/article/pregnant-with-twins-faqs-tips-and-advice">/en-us/pregnancy/multiple-pregnancy/article/pregnant-with-twins-faqs-tips-and-advice</option>
        <option value="/en-us/best-baby-products/nursery/best-teething-toys">/en-us/best-baby-products/nursery/best-teething-toys</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/whats-in-your-phone">/en-us/pregnancy/baby-shower/printable-baby-shower-games/whats-in-your-phone</option>
        <option value="/en-us/diapers-wipes/pampers-splashers-swim-pants-for-boys-and-girls/reviews">/en-us/diapers-wipes/pampers-splashers-swim-pants-for-boys-and-girls/reviews</option>
        <option value="/en-us/toddler/development/article/23-month-old">/en-us/toddler/development/article/23-month-old</option>
        <option value="/en-us/baby/care/article/baby-safety-around-the-house">/en-us/baby/care/article/baby-safety-around-the-house</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/couvade-syndrome">/en-us/pregnancy/pregnancy-symptoms/article/couvade-syndrome</option>
        <option value="/en-us/country-selector">/en-us/country-selector</option>
        <option value="/en-us/r-feedback">/en-us/r-feedback</option>
        <option value="/en-us/r-ddc-result-open-email">/en-us/r-ddc-result-open-email</option>
        <option value="/en-us/best-baby-products/travel-gear/best-diaper-bags">/en-us/best-baby-products/travel-gear/best-diaper-bags</option>
        <option value="/en-us/best-baby-products/nursery/best-travel-crib">/en-us/best-baby-products/nursery/best-travel-crib</option>
        <option value="/en-us/about-us/diapers-and-wipes/article/whats-in-a-pampers-diaper">/en-us/about-us/diapers-and-wipes/article/whats-in-a-pampers-diaper</option>
        <option value="/en-us/set-activate-password">/en-us/set-activate-password</option>
        <option value="/en-us/baby/development/article/10-month-old-baby">/en-us/baby/development/article/10-month-old-baby</option>
        <option value="/en-us/about-us/authors/allaya-cooks-campbell">/en-us/about-us/authors/allaya-cooks-campbell</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/he-said-she-said">/en-us/pregnancy/baby-shower/printable-baby-shower-games/he-said-she-said</option>
        <option value="/en-us/about-us/diapers-and-wipes/article/a-quick-guide-to-diaper-rash">/en-us/about-us/diapers-and-wipes/article/a-quick-guide-to-diaper-rash</option>
        <option value="/en-us/diapers-wipes/toddler-products">/en-us/diapers-wipes/toddler-products</option>
        <option value="/en-us/baby/teething/article/teething-fever">/en-us/baby/teething/article/teething-fever</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/3d-4d-ultrasound-scans">/en-us/pregnancy/prenatal-health-and-wellness/article/3d-4d-ultrasound-scans</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/morning-sickness-symptoms-remedies">/en-us/pregnancy/pregnancy-symptoms/article/morning-sickness-symptoms-remedies</option>
        <option value="/en-us/baby/newborn/article/common-nicu-tests">/en-us/baby/newborn/article/common-nicu-tests</option>
        <option value="/en-us/pregnancy/baby-names/article/90s-tv-inspired-baby-names">/en-us/pregnancy/baby-names/article/90s-tv-inspired-baby-names</option>
        <option value="/en-us/best-baby-products/infant-activity/best-baby-walking-shoes">/en-us/best-baby-products/infant-activity/best-baby-walking-shoes</option>
        <option value="/en-us/registration/thank-you-completed">/en-us/registration/thank-you-completed</option>
        <option value="/en-us/pregnancy/baby-names/article/biblical-baby-names">/en-us/pregnancy/baby-names/article/biblical-baby-names</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/5-top-tips-for-a-winter-pregnancy">/en-us/pregnancy/prenatal-health-and-wellness/article/5-top-tips-for-a-winter-pregnancy</option>
        <option value="/en-us/about-us/authors/lawrence-kutner">/en-us/about-us/authors/lawrence-kutner</option>
        <option value="/en-us/rewards/catalog">/en-us/rewards/catalog</option>
        <option value="/en-us/toddler/activities/article/toddler-activities-introducing-your-child-to-your-workplace">/en-us/toddler/activities/article/toddler-activities-introducing-your-child-to-your-workplace</option>
        <option value="/en-us/baby/development/article/baby-eyes-color-vision-and-more">/en-us/baby/development/article/baby-eyes-color-vision-and-more</option>
        <option value="/en-us/diapers-wipes/pampers-fragrance-free-wipes">/en-us/diapers-wipes/pampers-fragrance-free-wipes</option>
        <option value="/en-us/diaper-wipes-pure/pampers-pure-protection-diapers">/en-us/diaper-wipes-pure/pampers-pure-protection-diapers</option>
        <option value="/en-us/best-baby-products/infant-activity/best-pacifiers">/en-us/best-baby-products/infant-activity/best-pacifiers</option>
        <option value="/en-us/baby/sleep/article/how-to-soothe-a-crying-baby">/en-us/baby/sleep/article/how-to-soothe-a-crying-baby</option>
        <option value="/en-us/baby/teething/article/ways-to-make-brushing-fun">/en-us/baby/teething/article/ways-to-make-brushing-fun</option>
        <option value="/en-us/about-us/whats-in-our-product">/en-us/about-us/whats-in-our-product</option>
        <option value="/cruisers-360-cash-back-target">/cruisers-360-cash-back-target</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/a-z-of-pregnancy-aches-and-pains">/en-us/pregnancy/pregnancy-symptoms/article/a-z-of-pregnancy-aches-and-pains</option>
        <option value="/en-us/quizzes">/en-us/quizzes</option>
        <option value="/en-us/about-us/pampers-unicef-partnership">/en-us/about-us/pampers-unicef-partnership</option>
        <option value="/en-us/best-baby-products/health-safety/best-baby-gates">/en-us/best-baby-products/health-safety/best-baby-gates</option>
        <option value="/en-us/best-baby-products/health-safety/best-baby-nail-clippers">/en-us/best-baby-products/health-safety/best-baby-nail-clippers</option>
        <option value="/en-us/about-us/quality-and-safety/article/our-experts-in-safety">/en-us/about-us/quality-and-safety/article/our-experts-in-safety</option>
        <option value="/en-us/about-us/authors/margaret-comerford-freda">/en-us/about-us/authors/margaret-comerford-freda</option>
        <option value="/en-us/pregnancy/baby-names/article/most-popular-baby-names-of-2017">/en-us/pregnancy/baby-names/article/most-popular-baby-names-of-2017</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-games">/en-us/pregnancy/baby-shower/article/baby-shower-games</option>
        <option value="/en-us/baby/newborn/article/your-babys-first-bath">/en-us/baby/newborn/article/your-babys-first-bath</option>
        <option value="/en-us/best-baby-products/nursery/best-toddler-bed">/en-us/best-baby-products/nursery/best-toddler-bed</option>
        <option value="/en-us/best-baby-products/feeding/best-baby-formula">/en-us/best-baby-products/feeding/best-baby-formula</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/nursery-rhymes">/en-us/pregnancy/baby-shower/printable-baby-shower-games/nursery-rhymes</option>
        <option value="/en-us/baby/development/article/your-11-month-old-speech-and-social-development">/en-us/baby/development/article/your-11-month-old-speech-and-social-development</option>
        <option value="/en-us/pregnancy/giving-birth/article/how-to-find-the-childbirth-class-thats-right-for-you">/en-us/pregnancy/giving-birth/article/how-to-find-the-childbirth-class-thats-right-for-you</option>
        <option value="/en-us/about-us/authors/lisa-druxman">/en-us/about-us/authors/lisa-druxman</option>
        <option value="/en-us/best-baby-products/feeding/best-burp-cloths">/en-us/best-baby-products/feeding/best-burp-cloths</option>
        <option value="/en-us/registration">/en-us/registration</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/pregnancy-symptoms-during-second-pregnancy">/en-us/pregnancy/pregnancy-symptoms/article/pregnancy-symptoms-during-second-pregnancy</option>
        <option value="/en-us/guides-and-downloadables/your-go-to-pregnancy-guide">/en-us/guides-and-downloadables/your-go-to-pregnancy-guide</option>
        <option value="/en-us/baby/newborn">/en-us/baby/newborn</option>
        <option value="/en-us/thank-you-for-co-registration">/en-us/thank-you-for-co-registration</option>
        <option value="/en-us/pregnancy/baby-names/article/nature-inspired-baby-names">/en-us/pregnancy/baby-names/article/nature-inspired-baby-names</option>
        <option value="/en-us/toddler/development/article/when-do-babies-start-talking">/en-us/toddler/development/article/when-do-babies-start-talking</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/working-while-pregnant-maintaining-a-healthy-pregnancy">/en-us/pregnancy/prenatal-health-and-wellness/article/working-while-pregnant-maintaining-a-healthy-pregnancy</option>
        <option value="/en-us/best-baby-products/health-safety/best-baby-thermometers">/en-us/best-baby-products/health-safety/best-baby-thermometers</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/pregnancy-trimesters">/en-us/pregnancy/prenatal-health-and-wellness/article/pregnancy-trimesters</option>
        <option value="/en-us/pregnancy/giving-birth/article/stocking-up-on-baby-basics">/en-us/pregnancy/giving-birth/article/stocking-up-on-baby-basics</option>
        <option value="/en-us/baby/newborn/article/common-nicu-conditions-and-treatments">/en-us/baby/newborn/article/common-nicu-conditions-and-treatments</option>
        <option value="/en-us/quizzes/pregnancy-personality">/en-us/quizzes/pregnancy-personality</option>
        <option value="/en-us/about-us/authors/serena-norr">/en-us/about-us/authors/serena-norr</option>
        <option value="/en-us/pregnancy/giving-birth/article/epidural-what-is-it-and-how-does-it-work">/en-us/pregnancy/giving-birth/article/epidural-what-is-it-and-how-does-it-work</option>
        <option value="/en-us">/en-us</option>
        <option value="/en-us/pregnancy/pregnancy-calendar">/en-us/pregnancy/pregnancy-calendar</option>
        <option value="/en-us/best-baby-products/nursery/best-baby-crib">/en-us/best-baby-products/nursery/best-baby-crib</option>
        <option value="/en-us/contact-us">/en-us/contact-us</option>
        <option value="/en-us/best-baby-products/strollers/best-lightweight-strollers">/en-us/best-baby-products/strollers/best-lightweight-strollers</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/pregnancy-fatigue">/en-us/pregnancy/pregnancy-symptoms/article/pregnancy-fatigue</option>
        <option value="/en-us/best-baby-products/pregnancy-essentials">/en-us/best-baby-products/pregnancy-essentials</option>
        <option value="/en-us/rewards/cashback-value">/en-us/rewards/cashback-value</option>
        <option value="/en-us/about-us/diapers-and-wipes/article/diaper-leaks-prevent-leaks-and-blowouts-by-ensuring-the-right-diaper-fit">/en-us/about-us/diapers-and-wipes/article/diaper-leaks-prevent-leaks-and-blowouts-by-ensuring-the-right-diaper-fit</option>
        <option value="/en-us/baby/activities/article/ten-childrens-books-for-your-babys-collection">/en-us/baby/activities/article/ten-childrens-books-for-your-babys-collection</option>
        <option value="/en-us/about-us/diapers-and-wipes/article/diaper-size-and-weight-chart">/en-us/about-us/diapers-and-wipes/article/diaper-size-and-weight-chart</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/pregnancy-dreams">/en-us/pregnancy/pregnancy-symptoms/article/pregnancy-dreams</option>
        <option value="/en-us/pregnancy/giving-birth/article/what-to-pack-in-your-hospital-bag-go-bag-checklist">/en-us/pregnancy/giving-birth/article/what-to-pack-in-your-hospital-bag-go-bag-checklist</option>
        <option value="/en-us/best-baby-products/nursery/best-baby-gifts">/en-us/best-baby-products/nursery/best-baby-gifts</option>
        <option value="/en-us/pregnancy/giving-birth/article/inducing-labor">/en-us/pregnancy/giving-birth/article/inducing-labor</option>
        <option value="/en-us/best-baby-products/health-safety/best-security-blanket">/en-us/best-baby-products/health-safety/best-security-blanket</option>
        <option value="/en-us/about-us/diapers-and-wipes/article/diaper-size-and-weight-chart-page">/en-us/about-us/diapers-and-wipes/article/diaper-size-and-weight-chart-page</option>
        <option value="/en-us/about-us/authors/lauren-jimeson">/en-us/about-us/authors/lauren-jimeson</option>
        <option value="/en-us/pregnancy/giving-birth/article/what-to-expect-after-birth">/en-us/pregnancy/giving-birth/article/what-to-expect-after-birth</option>
        <option value="/en-us/childbirth-education-series">/en-us/childbirth-education-series</option>
        <option value="/en-us/pregnancy/baby-names/article/spanish-girls-names">/en-us/pregnancy/baby-names/article/spanish-girls-names</option>
        <option value="/en-us/pregnancy/baby-names/article/japanese-girl-names">/en-us/pregnancy/baby-names/article/japanese-girl-names</option>
        <option value="/en-us/development-milestones">/en-us/development-milestones</option>
        <option value="/en-us/baby/newborn/article/sponge-bath-how-to-sponge-bathe-a-newborn">/en-us/baby/newborn/article/sponge-bath-how-to-sponge-bathe-a-newborn</option>
        <option value="/en-us/pregnancy/baby-names/article/middle-names-for-boys">/en-us/pregnancy/baby-names/article/middle-names-for-boys</option>
        <option value="/en-us/best-baby-products/nursery/best-crib-mattress">/en-us/best-baby-products/nursery/best-crib-mattress</option>
        <option value="/en-us/r-register-to-watch-for-free">/en-us/r-register-to-watch-for-free</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/exercise-during-pregnancy-get-moving">/en-us/pregnancy/prenatal-health-and-wellness/article/exercise-during-pregnancy-get-moving</option>
        <option value="/en-us/about-us/pampers-unicef-partnership/article/pampers-and-unicef-a-life-saving-story-since-2006">/en-us/about-us/pampers-unicef-partnership/article/pampers-and-unicef-a-life-saving-story-since-2006</option>
        <option value="/en-us/quizzes/labor-pain-management-quiz">/en-us/quizzes/labor-pain-management-quiz</option>
        <option value="/en-us/pregnancy/giving-birth/article/healing-after-childbirth">/en-us/pregnancy/giving-birth/article/healing-after-childbirth</option>
        <option value="/en-us/baby/parenting-life/article/grocery-shopping-with-kids-or-babies">/en-us/baby/parenting-life/article/grocery-shopping-with-kids-or-babies</option>
        <option value="/en-us/pregnancy/baby-names/article/royal-baby-names">/en-us/pregnancy/baby-names/article/royal-baby-names</option>
        <option value="/en-us/pregnancy/baby-names/article/long-baby-names">/en-us/pregnancy/baby-names/article/long-baby-names</option>
        <option value="/en-us/set-password">/en-us/set-password</option>
        <option value="/en-us/pregnancy/baby-names/article/french-girl-names">/en-us/pregnancy/baby-names/article/french-girl-names</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/sex-during-pregnancy">/en-us/pregnancy/prenatal-health-and-wellness/article/sex-during-pregnancy</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/how-to-find-a-pediatrician">/en-us/pregnancy/prenatal-health-and-wellness/article/how-to-find-a-pediatrician</option>
        <option value="/en-us/pregnancy/baby-names/article/short-baby-names">/en-us/pregnancy/baby-names/article/short-baby-names</option>
        <option value="/en-us/best-baby-products/health-safety/best-baby-shampoo">/en-us/best-baby-products/health-safety/best-baby-shampoo</option>
        <option value="/en-US">/en-US</option>
        <option value="/en-us/pregnancy/chinese-gender-predictor">/en-us/pregnancy/chinese-gender-predictor</option>
        <option value="/en-us/pregnancy/due-date-calculator">/en-us/pregnancy/due-date-calculator</option>
        <option value="/en-us/pregnancy/due-date-calculator-page">/en-us/pregnancy/due-date-calculator-page</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/0">/en-us/pregnancy/due-date-calculator-result/0</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/1">/en-us/pregnancy/due-date-calculator-result/1</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/10">/en-us/pregnancy/due-date-calculator-result/10</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/11">/en-us/pregnancy/due-date-calculator-result/11</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/12">/en-us/pregnancy/due-date-calculator-result/12</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/13">/en-us/pregnancy/due-date-calculator-result/13</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/14">/en-us/pregnancy/due-date-calculator-result/14</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/15">/en-us/pregnancy/due-date-calculator-result/15</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/16">/en-us/pregnancy/due-date-calculator-result/16</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/17">/en-us/pregnancy/due-date-calculator-result/17</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/18">/en-us/pregnancy/due-date-calculator-result/18</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/19">/en-us/pregnancy/due-date-calculator-result/19</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/2">/en-us/pregnancy/due-date-calculator-result/2</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/20">/en-us/pregnancy/due-date-calculator-result/20</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/21">/en-us/pregnancy/due-date-calculator-result/21</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/22">/en-us/pregnancy/due-date-calculator-result/22</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/23">/en-us/pregnancy/due-date-calculator-result/23</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/24">/en-us/pregnancy/due-date-calculator-result/24</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/25">/en-us/pregnancy/due-date-calculator-result/25</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/26">/en-us/pregnancy/due-date-calculator-result/26</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/27">/en-us/pregnancy/due-date-calculator-result/27</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/28">/en-us/pregnancy/due-date-calculator-result/28</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/29">/en-us/pregnancy/due-date-calculator-result/29</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/3">/en-us/pregnancy/due-date-calculator-result/3</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/30">/en-us/pregnancy/due-date-calculator-result/30</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/31">/en-us/pregnancy/due-date-calculator-result/31</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/32">/en-us/pregnancy/due-date-calculator-result/32</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/33">/en-us/pregnancy/due-date-calculator-result/33</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/34">/en-us/pregnancy/due-date-calculator-result/34</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/35">/en-us/pregnancy/due-date-calculator-result/35</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/36">/en-us/pregnancy/due-date-calculator-result/36</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/37">/en-us/pregnancy/due-date-calculator-result/37</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/38">/en-us/pregnancy/due-date-calculator-result/38</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/39">/en-us/pregnancy/due-date-calculator-result/39</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/4">/en-us/pregnancy/due-date-calculator-result/4</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/40">/en-us/pregnancy/due-date-calculator-result/40</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/5">/en-us/pregnancy/due-date-calculator-result/5</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/6">/en-us/pregnancy/due-date-calculator-result/6</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/7">/en-us/pregnancy/due-date-calculator-result/7</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/8">/en-us/pregnancy/due-date-calculator-result/8</option>
        <option value="/en-us/pregnancy/due-date-calculator-result/9">/en-us/pregnancy/due-date-calculator-result/9</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/1-3-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/1-3-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/1-month-pregnant">/en-us/pregnancy/pregnancy-calendar/1-month-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/10-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/10-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/11-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/11-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/11-weeks-pregnant-page">/en-us/pregnancy/pregnancy-calendar/11-weeks-pregnant-page</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/12-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/12-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/13-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/13-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/2-months-pregnant">/en-us/pregnancy/pregnancy-calendar/2-months-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/3-months-pregnant">/en-us/pregnancy/pregnancy-calendar/3-months-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/3-months-pregnant feels">/en-us/pregnancy/pregnancy-calendar/3-months-pregnant feels</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/4-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/4-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/4-weeks-pregnant-page">/en-us/pregnancy/pregnancy-calendar/4-weeks-pregnant-page</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/5-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/5-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/6-months-pregnant#pregnancy-week-6-400text/html; charset=utf-80404">/en-us/pregnancy/pregnancy-calendar/6-months-pregnant#pregnancy-week-6-400text/html; charset=utf-80404</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/6-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/6-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/6-weeks-pregnant baby safe">/en-us/pregnancy/pregnancy-calendar/6-weeks-pregnant baby safe</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/7-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/7-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/8-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/8-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/9-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/9-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/first-trimester">/en-us/pregnancy/pregnancy-calendar/first-trimester</option>
        <option value="/en-us/pregnancy/giving-birth/article/natural-birth">/en-us/pregnancy/giving-birth/article/natural-birth</option>
        <option value="/en-us/pregnancy/baby-names/find-your-baby-name-page">/en-us/pregnancy/baby-names/find-your-baby-name-page</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/back-pain-during-pregnancy">/en-us/pregnancy/pregnancy-symptoms/article/back-pain-during-pregnancy</option>
        <option value="/en-us/baby/parenting-life/article/dear-baby-what-it-means-to-me-to-be-your-mom">/en-us/baby/parenting-life/article/dear-baby-what-it-means-to-me-to-be-your-mom</option>
        <option value="/en-us/baby/diapering/article/diaper-rash-treatment-and-remedies">/en-us/baby/diapering/article/diaper-rash-treatment-and-remedies</option>
        <option value="/en-us/test-standalone-article">/en-us/test-standalone-article</option>
        <option value="/en-us/toddler/activities/article/crafts-for-toddlers-ideas-and-tips">/en-us/toddler/activities/article/crafts-for-toddlers-ideas-and-tips</option>
        <option value="/en-us/toddler/activities/article/parallel-play-in-toddlers">/en-us/toddler/activities/article/parallel-play-in-toddlers</option>
        <option value="/en-us/toddler/development/article/15-month-old">/en-us/toddler/development/article/15-month-old</option>
        <option value="/en-us/toddler/development/article/16-month-old">/en-us/toddler/development/article/16-month-old</option>
        <option value="/en-us/toddler/development/article/17-month-old">/en-us/toddler/development/article/17-month-old</option>
        <option value="/en-us/toddler/development/article/18-month-old">/en-us/toddler/development/article/18-month-old</option>
        <option value="/en-us/toddler/development/article/19-month-old">/en-us/toddler/development/article/19-month-old</option>
        <option value="/en-us/toddler/development/article/2-5-year-old-what-to-expect">/en-us/toddler/development/article/2-5-year-old-what-to-expect</option>
        <option value="/en-us/toddler/development/article/20-month-old">/en-us/toddler/development/article/20-month-old</option>
        <option value="/en-us/toddler/development/article/22-month-old">/en-us/toddler/development/article/22-month-old</option>
        <option value="/en-us/toddler/development/article/24-month-old">/en-us/toddler/development/article/24-month-old</option>
        <option value="/en-us/toddler/development/article/3-year-olds-behavior-and-development">/en-us/toddler/development/article/3-year-olds-behavior-and-development</option>
        <option value="/en-us/toddler/development/article/babies-and-toddler-boundaries">/en-us/toddler/development/article/babies-and-toddler-boundaries</option>
        <option value="/en-us/toddler/development/article/choosing-a-good-preschool">/en-us/toddler/development/article/choosing-a-good-preschool</option>
        <option value="/en-us/toddler/development/article/crib-talk-baby-talk-at-bedtime">/en-us/toddler/development/article/crib-talk-baby-talk-at-bedtime</option>
        <option value="/en-us/toddler/development/article/crybabies-why-do-some-babies-cry-more-than-others">/en-us/toddler/development/article/crybabies-why-do-some-babies-cry-more-than-others</option>
        <option value="/en-us/toddler/development/article/discipline-how-to-talk-to-toddlers">/en-us/toddler/development/article/discipline-how-to-talk-to-toddlers</option>
        <option value="/en-us/toddler/development/article/eliminate-tantrums-tips">/en-us/toddler/development/article/eliminate-tantrums-tips</option>
        <option value="/en-us/toddler/development/article/how-understanding-power-can-help-with-toddler-behavior">/en-us/toddler/development/article/how-understanding-power-can-help-with-toddler-behavior</option>
        <option value="/en-us/toddler/development/article/is-my-child-really-ready-for-preschool">/en-us/toddler/development/article/is-my-child-really-ready-for-preschool</option>
        <option value="/en-us/toddler/development/article/kids-fighting-how-to-handle-aggressive-behavior">/en-us/toddler/development/article/kids-fighting-how-to-handle-aggressive-behavior</option>
        <option value="/en-us/toddler/development/article/mom-and-toddler-separation-anxiety">/en-us/toddler/development/article/mom-and-toddler-separation-anxiety</option>
        <option value="/en-us/toddler/development/article/potty-talk-curbing-bad-language-in-toddlers">/en-us/toddler/development/article/potty-talk-curbing-bad-language-in-toddlers</option>
        <option value="/en-us/toddler/development/article/shy-children-overcoming-shyness">/en-us/toddler/development/article/shy-children-overcoming-shyness</option>
        <option value="/en-us/toddler/development/article/teaching-toddlers-to-share">/en-us/toddler/development/article/teaching-toddlers-to-share</option>
        <option value="/en-us/toddler/development/article/toddler-development-the-terrible-twos">/en-us/toddler/development/article/toddler-development-the-terrible-twos</option>
        <option value="/en-us/toddler/development/article/toddler-language-development-milestones">/en-us/toddler/development/article/toddler-language-development-milestones</option>
        <option value="/en-us/toddler/development/article/toddler-pronunciation-helping-toddlers-speak-properly">/en-us/toddler/development/article/toddler-pronunciation-helping-toddlers-speak-properly</option>
        <option value="/en-us/toddler/development/article/why-does-my-toddler-hit">/en-us/toddler/development/article/why-does-my-toddler-hit</option>
        <option value="/en-us/toddler/nutrition/article/good-eating-habits-for-a-3-year-old">/en-us/toddler/nutrition/article/good-eating-habits-for-a-3-year-old</option>
        <option value="/en-us/toddler/nutrition/article/ideas-for-a-2-year-old-who-wont-eat-dinner">/en-us/toddler/nutrition/article/ideas-for-a-2-year-old-who-wont-eat-dinner</option>
        <option value="/en-us/toddler/nutrition/article/picky-eaters-toddlers-and-preschoolers-at-mealtimes">/en-us/toddler/nutrition/article/picky-eaters-toddlers-and-preschoolers-at-mealtimes</option>
        <option value="/en-us/toddler/nutrition/article/picky-toddler-feeding">/en-us/toddler/nutrition/article/picky-toddler-feeding</option>
        <option value="/en-us/toddler/nutrition/article/toddler-breakfast-ideas">/en-us/toddler/nutrition/article/toddler-breakfast-ideas</option>
        <option value="/en-us/toddler/nutrition/article/what-to-feed-a-2-year-old">/en-us/toddler/nutrition/article/what-to-feed-a-2-year-old</option>
        <option value="/en-us/toddler/nutrition/article/why-does-my-16-month-old-choke-when-he-eats">/en-us/toddler/nutrition/article/why-does-my-16-month-old-choke-when-he-eats</option>
        <option value="/en-us/toddler/parenting-life/article/heartfelt-compliments-that-parents-received">/en-us/toddler/parenting-life/article/heartfelt-compliments-that-parents-received</option>
        <option value="/en-us/toddler/potty-training/article/6-smart-ways-to-make-potty-training-fun">/en-us/toddler/potty-training/article/6-smart-ways-to-make-potty-training-fun</option>
        <option value="/en-us/toddler/potty-training/article/7-must-have-items-for-potty-training">/en-us/toddler/potty-training/article/7-must-have-items-for-potty-training</option>
        <option value="/en-us/toddler/potty-training/article/benefits-of-training-pants-pampers">/en-us/toddler/potty-training/article/benefits-of-training-pants-pampers</option>
        <option value="/en-us/toddler/potty-training/article/my-preschooler-isnt-fully-potty-trained-and-its-ok">/en-us/toddler/potty-training/article/my-preschooler-isnt-fully-potty-trained-and-its-ok</option>
        <option value="/en-us/toddler/potty-training/article/potty-trained-toddlers-and-accidents">/en-us/toddler/potty-training/article/potty-trained-toddlers-and-accidents</option>
        <option value="/en-us/toddler/potty-training/article/potty-training-at-day-care">/en-us/toddler/potty-training/article/potty-training-at-day-care</option>
        <option value="/en-us/toddler/potty-training/article/potty-training-stories">/en-us/toddler/potty-training/article/potty-training-stories</option>
        <option value="/en-us/toddler/potty-training/article/potty-training-tips-step-by-step-potty-training">/en-us/toddler/potty-training/article/potty-training-tips-step-by-step-potty-training</option>
        <option value="/en-us/toddler/potty-training/article/potty-training-while-traveling">/en-us/toddler/potty-training/article/potty-training-while-traveling</option>
        <option value="/en-us/toddler/potty-training/article/rewards-to-help-your-child-potty-train">/en-us/toddler/potty-training/article/rewards-to-help-your-child-potty-train</option>
        <option value="/en-us/toddler/potty-training/article/tips-and-tricks-for-night-time-potty-training">/en-us/toddler/potty-training/article/tips-and-tricks-for-night-time-potty-training</option>
        <option value="/en-us/toddler/potty-training/article/when-to-potty-train-useful-tips-for-potty-training">/en-us/toddler/potty-training/article/when-to-potty-train-useful-tips-for-potty-training</option>
        <option value="/en-us/toddler/potty-training/article/when-to-start-potty-training-signs-your-child-is-ready">/en-us/toddler/potty-training/article/when-to-start-potty-training-signs-your-child-is-ready</option>
        <option value="/en-us/toddler/sleep/article/bed-wetting-causes-and-solutions">/en-us/toddler/sleep/article/bed-wetting-causes-and-solutions</option>
        <option value="/en-us/toddler/sleep/article/bedwetting-causes-and-solutions">/en-us/toddler/sleep/article/bedwetting-causes-and-solutions</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/frequent-urination-during-pregnancy">/en-us/pregnancy/pregnancy-symptoms/article/frequent-urination-during-pregnancy</option>
        <option value="/en-us/best-baby-products/travel-gear/best-backpack-diaper-bag">/en-us/best-baby-products/travel-gear/best-backpack-diaper-bag</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/pregnancy-diet-plan-eating-carbs">/en-us/pregnancy/prenatal-health-and-wellness/article/pregnancy-diet-plan-eating-carbs</option>
        <option value="/en-us/best-baby-products/nursery/best-baby-sound-machine">/en-us/best-baby-products/nursery/best-baby-sound-machine</option>
        <option value="/en-us/quizzes/tell-us-about-your-style-and-we-will-help-decorate-your-nursery">/en-us/quizzes/tell-us-about-your-style-and-we-will-help-decorate-your-nursery</option>
        <option value="/en-us/toddler/health">/en-us/toddler/health</option>
        <option value="/en-us/toddler/health/article/2-year-checkup-your-toddler-at-2-years">/en-us/toddler/health/article/2-year-checkup-your-toddler-at-2-years</option>
        <option value="/en-us/toddler/health/article/knee-scrapes-cuts-and-bruises-treating-minor-injuries">/en-us/toddler/health/article/knee-scrapes-cuts-and-bruises-treating-minor-injuries</option>
        <option value="/en-us/toddler/health/article/signs-of-diabetes-in-toddlers">/en-us/toddler/health/article/signs-of-diabetes-in-toddlers</option>
        <option value="/en-us/toddler/health/article/staying-safe-while-enjoying-the-great-outdoors">/en-us/toddler/health/article/staying-safe-while-enjoying-the-great-outdoors</option>
        <option value="/en-us/toddler/health/article/stomach-pain-in-children-toddler-tummy-aches">/en-us/toddler/health/article/stomach-pain-in-children-toddler-tummy-aches</option>
        <option value="/en-us/toddler/health/article/toddler-bath-time">/en-us/toddler/health/article/toddler-bath-time</option>
        <option value="/en-us/toddler/health/article/toddler-bike-safety-and-baby-bike-seat-safety">/en-us/toddler/health/article/toddler-bike-safety-and-baby-bike-seat-safety</option>
        <option value="/en-us/toddler/health/article/warts-on-kids">/en-us/toddler/health/article/warts-on-kids</option>
        <option value="/en-us/toddler/health/article/well-baby-visit-18-months">/en-us/toddler/health/article/well-baby-visit-18-months</option>
        <option value="/en-us/toddler/health/article/well-baby-visit-3-year-old-check-up">/en-us/toddler/health/article/well-baby-visit-3-year-old-check-up</option>
        <option value="/en-us/toddler/nutrition/article/healthy-snack-ideas-for-your-toddler-and-preschooler">/en-us/toddler/nutrition/article/healthy-snack-ideas-for-your-toddler-and-preschooler</option>
        <option value="/en-us/toddler/nutrition/article/healthy-toddler-lunch-ideas">/en-us/toddler/nutrition/article/healthy-toddler-lunch-ideas</option>
        <option value="/en-us/toddler/nutrition/article/healthy-toddler-lunch-ideas]">/en-us/toddler/nutrition/article/healthy-toddler-lunch-ideas]</option>
        <option value="/en-us/toddler/nutrition/article/healthy-toddler-meal-ideas">/en-us/toddler/nutrition/article/healthy-toddler-meal-ideas</option>
        <option value="/en-us/toddler/parenting-life/article/healthy-birthday-cakes-and-treats-children-love">/en-us/toddler/parenting-life/article/healthy-birthday-cakes-and-treats-children-love</option>
        <option value="/en-us/diapers-wipes/pampers-swaddlers-diapers sizes">/en-us/diapers-wipes/pampers-swaddlers-diapers sizes</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/quickening-fetal-movement">/en-us/pregnancy/pregnancy-symptoms/article/quickening-fetal-movement</option>
        <option value="/en-us/baby/newborn/article/caring-for-your-premature-baby">/en-us/baby/newborn/article/caring-for-your-premature-baby</option>
        <option value="/en-us/best-baby-products/infant-activity/best-toys-for-1-year-olds">/en-us/best-baby-products/infant-activity/best-toys-for-1-year-olds</option>
        <option value="/en-us/baby/teething">/en-us/baby/teething</option>
        <option value="/en-us/about-us/authors/donna-duarte-ladd">/en-us/about-us/authors/donna-duarte-ladd</option>
        <option value="/en-us/baby/development/article/baby-growth-spurts">/en-us/baby/development/article/baby-growth-spurts</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/wishes-for-baby">/en-us/pregnancy/baby-shower/printable-baby-shower-games/wishes-for-baby</option>
        <option value="/en-us/toddler/activities/article/sports-for-toddlers">/en-us/toddler/activities/article/sports-for-toddlers</option>
        <option value="/en-us/about-us/authors">/en-us/about-us/authors</option>
        <option value="/en-us/quizzes/am-i-pregnant">/en-us/quizzes/am-i-pregnant</option>
        <option value="/en-us/baby/parenting-life/article/15-reasons-im-thankful-to-my-kids-for-making-me-a-mom">/en-us/baby/parenting-life/article/15-reasons-im-thankful-to-my-kids-for-making-me-a-mom</option>
        <option value="/en-us/best-baby-products/pregnancy-essentials/best-pregnancy-pillows update the products">/en-us/best-baby-products/pregnancy-essentials/best-pregnancy-pillows update the products</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/nuchal-translucency-test">/en-us/pregnancy/prenatal-health-and-wellness/article/nuchal-translucency-test</option>
        <option value="/en-us/diapers-wipes/pampers-easy-ups-trainers-for-boys-and-girls/reviews">/en-us/diapers-wipes/pampers-easy-ups-trainers-for-boys-and-girls/reviews</option>
        <option value="/en-us/diapers-wipes/pampers-swaddlers-overnights/reviews">/en-us/diapers-wipes/pampers-swaddlers-overnights/reviews</option>
        <option value="/en-us/pregnancy/preparing-for-your-new-baby/article/pregnancy-nesting">/en-us/pregnancy/preparing-for-your-new-baby/article/pregnancy-nesting</option>
        <option value="/en-us/about-us/pampers-purpose/article/pampers-bright-beginnings">/en-us/about-us/pampers-purpose/article/pampers-bright-beginnings</option>
        <option value="/en-us/pregnancy/multiple-pregnancy/article/pregnant-with-triplets">/en-us/pregnancy/multiple-pregnancy/article/pregnant-with-triplets</option>
        <option value="/en-us/baby/newborn/article/3-month-old-baby">/en-us/baby/newborn/article/3-month-old-baby</option>
        <option value="/en-us/toddler/activities/article/optimal-screen-time-for-babies-and-children">/en-us/toddler/activities/article/optimal-screen-time-for-babies-and-children</option>
        <option value="/en-us/about-us/authors/tanya-remer-altmann">/en-us/about-us/authors/tanya-remer-altmann</option>
        <option value="/en-us/login">/en-us/login</option>
        <option value="/en-us/baby/parenting-life">/en-us/baby/parenting-life</option>
        <option value="/en-us/pregnancy/preparing-for-your-new-baby/article/top-preparation-tips-bringing-your-newborn-baby-home">/en-us/pregnancy/preparing-for-your-new-baby/article/top-preparation-tips-bringing-your-newborn-baby-home</option>
        <option value="/en-us/baby/development/article/12-month-old-baby">/en-us/baby/development/article/12-month-old-baby</option>
        <option value="/en-us/baby/development/article/baby-firsts-rolling-over">/en-us/baby/development/article/baby-firsts-rolling-over</option>
        <option value="/en-us/diaper-wipes-pure/pampers-aqua-pure-wipes/reviews">/en-us/diaper-wipes-pure/pampers-aqua-pure-wipes/reviews</option>
        <option value="/en-us/pregnancy/baby-names/article/spanish-boys-names">/en-us/pregnancy/baby-names/article/spanish-boys-names</option>
        <option value="/en-us/pregnancy/baby-names/article/how-to-throw-a-baby-naming-party">/en-us/pregnancy/baby-names/article/how-to-throw-a-baby-naming-party</option>
        <option value="/en-us/baby/parenting-life/article/parenting-words-new-meaning">/en-us/baby/parenting-life/article/parenting-words-new-meaning</option>
        <option value="/en-us/baby/development/article/moro-reflex">/en-us/baby/development/article/moro-reflex</option>
        <option value="/en-us/pregnancy/pregnancy-announcement/videos/share-your-big-news-with-style-old">/en-us/pregnancy/pregnancy-announcement/videos/share-your-big-news-with-style-old</option>
        <option value="/en-us/pregnancy/baby-names/article/hawaiian-boy-names">/en-us/pregnancy/baby-names/article/hawaiian-boy-names</option>
        <option value="/en-us/pregnancy/pregnancy-announcement/article/pregnancy-announcements-telling-your-coworkers">/en-us/pregnancy/pregnancy-announcement/article/pregnancy-announcements-telling-your-coworkers</option>
        <option value="/en-us/baby/development/article/5-month-old-baby">/en-us/baby/development/article/5-month-old-baby</option>
        <option value="/en-us/baby/development/article/surprise-babies-understand-more-than-we-think">/en-us/baby/development/article/surprise-babies-understand-more-than-we-think</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/pregnancy-warning-signs">/en-us/pregnancy/pregnancy-symptoms/article/pregnancy-warning-signs</option>
        <option value="/en-us/best-baby-products/strollers/best-travel-systems">/en-us/best-baby-products/strollers/best-travel-systems</option>
        <option value="/en-us/about-us/authors/elaine-zwelling">/en-us/about-us/authors/elaine-zwelling</option>
        <option value="/en-us/quizzes/babymoon-destination">/en-us/quizzes/babymoon-destination</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/whats-in-your-purse">/en-us/pregnancy/baby-shower/printable-baby-shower-games/whats-in-your-purse</option>
        <option value="/en-us/pregnancy/baby-names/article/boy-names-that-start-with-b">/en-us/pregnancy/baby-names/article/boy-names-that-start-with-b</option>
        <option value="/en-us/pregnancy/chinese-gender-predictor-results">/en-us/pregnancy/chinese-gender-predictor-results</option>
        <option value="/en-us/about-us/quality-and-safety/article/materials-and-safety">/en-us/about-us/quality-and-safety/article/materials-and-safety</option>
        <option value="/en-us/best-baby-products/nursery/best-baby-wraps">/en-us/best-baby-products/nursery/best-baby-wraps</option>
        <option value="/en-us/pregnancy/baby-names/article/italian-boy-names">/en-us/pregnancy/baby-names/article/italian-boy-names</option>
        <option value="/en-us/baby/teething/article/baby-teeth-chart">/en-us/baby/teething/article/baby-teeth-chart</option>
        <option value="/en-us/about-us/diapers-and-wipes/article/swaddlers-and-sensitive-wipes-for-a-healthy-skin">/en-us/about-us/diapers-and-wipes/article/swaddlers-and-sensitive-wipes-for-a-healthy-skin</option>
        <option value="/en-us/r-bgc-vortex-scenario">/en-us/r-bgc-vortex-scenario</option>
        <option value="/en-us/pregnancy/pregnancy-announcement/article/ultimate-guide-for-planning-a-gender-reveal-party">/en-us/pregnancy/pregnancy-announcement/article/ultimate-guide-for-planning-a-gender-reveal-party</option>
        <option value="/en-us/baby/newborn/article/when-babies-hold-head-up">/en-us/baby/newborn/article/when-babies-hold-head-up</option>
        <option value="/en-us/best-baby-products/travel-gear/best-toddler-car-seat">/en-us/best-baby-products/travel-gear/best-toddler-car-seat</option>
        <option value="/en-us/baby/development/article/9-month-old-baby">/en-us/baby/development/article/9-month-old-baby</option>
        <option value="/en-us/guides-and-downloadables/fetal-movement-tracker">/en-us/guides-and-downloadables/fetal-movement-tracker</option>
        <option value="/en-us/pregnancy/pregnancy-announcement/article/pregnancy-announcements-telling-your-partner">/en-us/pregnancy/pregnancy-announcement/article/pregnancy-announcements-telling-your-partner</option>
        <option value="/en-us/pregnancy/baby-names/article/french-boy-names">/en-us/pregnancy/baby-names/article/french-boy-names</option>
        <option value="/en-us/pregnancy/baby-names/article/boy-names-that-start-with-a">/en-us/pregnancy/baby-names/article/boy-names-that-start-with-a</option>
        <option value="/en-us/baby/teething/article/guide-to-taking-baby-to-dentist">/en-us/baby/teething/article/guide-to-taking-baby-to-dentist</option>
        <option value="/en-us/thank-you-page-ergobaby">/en-us/thank-you-page-ergobaby</option>
        <option value="/en-us/baby/parenting-life/article/babys-first-year-12-memorable-moments">/en-us/baby/parenting-life/article/babys-first-year-12-memorable-moments</option>
        <option value="/en-us/baby/parenting-life/article/five-moms-share-their-best-babyhacks">/en-us/baby/parenting-life/article/five-moms-share-their-best-babyhacks</option>
        <option value="/en-us/best-baby-products/feeding/best-high-chair">/en-us/best-baby-products/feeding/best-high-chair</option>
        <option value="/en-us/about-us/authors/janssen-bradshaw">/en-us/about-us/authors/janssen-bradshaw</option>
        <option value="/en-us/guides-and-downloadables">/en-us/guides-and-downloadables</option>
        <option value="/en-us/baby/parenting-life/article/daddys-little-one-the-dad-and-baby-bond">/en-us/baby/parenting-life/article/daddys-little-one-the-dad-and-baby-bond</option>
        <option value="/en-us/toddler/development/article/toddler-biting-how-to-stop-children-from-biting">/en-us/toddler/development/article/toddler-biting-how-to-stop-children-from-biting</option>
        <option value="/en-us/r-register-monthiversary">/en-us/r-register-monthiversary</option>
        <option value="/en-us/pregnancy/baby-names/article/old-fashioned-girl-names">/en-us/pregnancy/baby-names/article/old-fashioned-girl-names</option>
        <option value="/en-us/guides-and-downloadables/pregnancy-exercises">/en-us/guides-and-downloadables/pregnancy-exercises</option>
        <option value="/en-us/best-baby-products/feeding/best-breast-pumps">/en-us/best-baby-products/feeding/best-breast-pumps</option>
        <option value="/en-us/pregnancy/giving-birth/article/what-to-pack-in-your-hospital-bag-go-bag-checklist<">/en-us/pregnancy/giving-birth/article/what-to-pack-in-your-hospital-bag-go-bag-checklist<</option>
        <option value="/en-us/about-us/authors/jennifer-salazar-hutcheson">/en-us/about-us/authors/jennifer-salazar-hutcheson</option>
        <option value="/en-us/diapers-wipes/pampers-pure-protection-diapers-hybrid">/en-us/diapers-wipes/pampers-pure-protection-diapers-hybrid</option>
        <option value="/en-us/guides-and-downloadables/pregnancy-nutrition">/en-us/guides-and-downloadables/pregnancy-nutrition</option>
        <option value="/en-us/quizzes/how-do-you-manage-childcare">/en-us/quizzes/how-do-you-manage-childcare</option>
        <option value="/en-us/baby/parenting-life/article/18-best-ways-to-help-out-a-new-mom">/en-us/baby/parenting-life/article/18-best-ways-to-help-out-a-new-mom</option>
        <option value="/en-us/diapers-wipes/pampers-baby-fresh-scent-wipes/reviews">/en-us/diapers-wipes/pampers-baby-fresh-scent-wipes/reviews</option>
        <option value="/en-us/toddler/development/article/how-can-we-help-a-3-half-year-old-who-steals">/en-us/toddler/development/article/how-can-we-help-a-3-half-year-old-who-steals</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/itching-during-pregnancy">/en-us/pregnancy/pregnancy-symptoms/article/itching-during-pregnancy</option>
        <option value="/en-us/pregnancy/pregnancy-weight-gain-calculator-results">/en-us/pregnancy/pregnancy-weight-gain-calculator-results</option>
        <option value="/en-us/best-baby-products/nursery/best-toddler-bed-rails">/en-us/best-baby-products/nursery/best-toddler-bed-rails</option>
        <option value="/en-us/diapers-wipes/baby-products">/en-us/diapers-wipes/baby-products</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/how-far-along-am-i-in-my-pregnancy">/en-us/pregnancy/pregnancy-symptoms/article/how-far-along-am-i-in-my-pregnancy</option>
        <option value="/en-us/coupons-offers">/en-us/coupons-offers</option>
        <option value="/en-us/toddler/development/article/13-month-old">/en-us/toddler/development/article/13-month-old</option>
        <option value="/en-us/quizzes/guess-your-babys-gender-quiz">/en-us/quizzes/guess-your-babys-gender-quiz</option>
        <option value="/en-us/baby/activities/article/day-care-for-infants">/en-us/baby/activities/article/day-care-for-infants</option>
        <option value="/en-us/about-us/authors/jenica-parcell">/en-us/about-us/authors/jenica-parcell</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/pregnancy-test">/en-us/pregnancy/prenatal-health-and-wellness/article/pregnancy-test</option>
        <option value="/en-us/toddler/development">/en-us/toddler/development</option>
        <option value="/en-us/pregnancy/giving-birth/article/how-your-birth-plan-can-quickly-change">/en-us/pregnancy/giving-birth/article/how-your-birth-plan-can-quickly-change</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/prenatal-vitamins">/en-us/pregnancy/prenatal-health-and-wellness/article/prenatal-vitamins</option>
        <option value="/en-us/toddler/parenting-life/article/6-new-years-resolutions-your-baby-would-make">/en-us/toddler/parenting-life/article/6-new-years-resolutions-your-baby-would-make</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/swollen-feet-during-pregnancy">/en-us/pregnancy/pregnancy-symptoms/article/swollen-feet-during-pregnancy</option>
        <option value="/en-us/r-duedatecalculatorlocked">/en-us/r-duedatecalculatorlocked</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/hcg-levels">/en-us/pregnancy/prenatal-health-and-wellness/article/hcg-levels</option>
        <option value="/en-us/registration/completion">/en-us/registration/completion</option>
        <option value="/en-us/ineligible">/en-us/ineligible</option>
        <option value="/en-us/r-weight-gain-calc">/en-us/r-weight-gain-calc</option>
        <option value="/en-us/about-us/authors/laura-falin">/en-us/about-us/authors/laura-falin</option>
        <option value="/en-us/diapers-wipes/pampers-easy-ups-trainers-for-boys-and-girls">/en-us/diapers-wipes/pampers-easy-ups-trainers-for-boys-and-girls</option>
        <option value="/lumi-smart-sleep-coach-1">/lumi-smart-sleep-coach-1</option>
        <option value="/en-us/toddler/sleep/article/what-to-know-when-transitioning-your-child-to-a-toddler-bed">/en-us/toddler/sleep/article/what-to-know-when-transitioning-your-child-to-a-toddler-bed</option>
        <option value="/en-us/r-preregisterabovefooter">/en-us/r-preregisterabovefooter</option>
        <option value="/en-us/about-us/quality-and-safety/article/our-safety-promise">/en-us/about-us/quality-and-safety/article/our-safety-promise</option>
        <option value="/en-us/baby/feeding">/en-us/baby/feeding</option>
        <option value="/en-us/reset-password">/en-us/reset-password</option>
        <option value="/en-us/about-us/authors/courtney-solstad">/en-us/about-us/authors/courtney-solstad</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/baby-animals">/en-us/pregnancy/baby-shower/printable-baby-shower-games/baby-animals</option>
        <option value="/en-us/best-baby-products/strollers/best-double-strollers">/en-us/best-baby-products/strollers/best-double-strollers</option>
        <option value="/en-us/quizzes/perfect-baby-shower-theme">/en-us/quizzes/perfect-baby-shower-theme</option>
        <option value="/en-us/best-baby-products">/en-us/best-baby-products</option>
        <option value="/en-us/best-baby-products/feeding/best-bottle-warmers">/en-us/best-baby-products/feeding/best-bottle-warmers</option>
        <option value="/en-us/pregnancy/giving-birth/article/mucus-plug">/en-us/pregnancy/giving-birth/article/mucus-plug</option>
        <option value="/en-us/childbirth-full-series">/en-us/childbirth-full-series</option>
        <option value="/en-us/quizzes/right-diaper-size-quiz">/en-us/quizzes/right-diaper-size-quiz</option>
        <option value="/en-us/pregnancy/giving-birth/article/water-breaking">/en-us/pregnancy/giving-birth/article/water-breaking</option>
        <option value="/en-us/best-baby-products/nursery/baby-sleeping-sacks">/en-us/best-baby-products/nursery/baby-sleeping-sacks</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/paternity-leave">/en-us/pregnancy/prenatal-health-and-wellness/article/paternity-leave</option>
        <option value="/en-us/baby/parenting-life/article/newborn-baby-development-understanding-your-baby">/en-us/baby/parenting-life/article/newborn-baby-development-understanding-your-baby</option>
        <option value="/en-us/baby/newborn/article/when-do-babies-start-smiling">/en-us/baby/newborn/article/when-do-babies-start-smiling</option>
        <option value="/en-us/baby/activities/article/baby-stimulation-activities-for-your-6-month-old">/en-us/baby/activities/article/baby-stimulation-activities-for-your-6-month-old</option>
        <option value="/en-us/r-register-bsg">/en-us/r-register-bsg</option>
        <option value="/en-us/pregnancy/giving-birth/article/coping-with-preeclampsia-while-carrying-twins">/en-us/pregnancy/giving-birth/article/coping-with-preeclampsia-while-carrying-twins</option>
        <option value="/en-us/best-baby-products/travel-gear/best-car-seats">/en-us/best-baby-products/travel-gear/best-car-seats</option>
        <option value="/en-us/pregnancy/baby-names/article/unique-baby-names">/en-us/pregnancy/baby-names/article/unique-baby-names</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/summer-pregnancy-tips">/en-us/pregnancy/prenatal-health-and-wellness/article/summer-pregnancy-tips</option>
        <option value="/en-us/articles-results">/en-us/articles-results</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/genetic-testing-pregnancy">/en-us/pregnancy/prenatal-health-and-wellness/article/genetic-testing-pregnancy</option>
        <option value="/en-us/rewards-terms-conditions">/en-us/rewards-terms-conditions</option>
        <option value="/en-us/baby/parenting-life/article/home-for-the-holidays-an-adoption-story">/en-us/baby/parenting-life/article/home-for-the-holidays-an-adoption-story</option>
        <option value="/en-us/baby/development/article/baby-sign-language">/en-us/baby/development/article/baby-sign-language</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/hyperemesis-gravidarum">/en-us/pregnancy/pregnancy-symptoms/article/hyperemesis-gravidarum</option>
        <option value="/en-us/pregnancy/giving-birth/article/what-to-include-in-your-birth-plan">/en-us/pregnancy/giving-birth/article/what-to-include-in-your-birth-plan</option>
        <option value="/en-us/baby/activities/article/indoor-activities-for-babies-and-toddlers">/en-us/baby/activities/article/indoor-activities-for-babies-and-toddlers</option>
        <option value="/en-us/best-baby-products/nursery/best-swaddle">/en-us/best-baby-products/nursery/best-swaddle</option>
        <option value="/en-us/about-us/authors/armin-brott">/en-us/about-us/authors/armin-brott</option>
        <option value="/en-us/about-us/authors/carolines-clauss-ehlers">/en-us/about-us/authors/carolines-clauss-ehlers</option>
        <option value="/en-us/diapers-wipes/pampers-cruisers-360/reviews">/en-us/diapers-wipes/pampers-cruisers-360/reviews</option>
        <option value="/en-us/pregnancy/preparing-for-your-new-baby/videos/is-breast-best-tune-in-tune-in">/en-us/pregnancy/preparing-for-your-new-baby/videos/is-breast-best-tune-in-tune-in</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/acne-during-pregnancy">/en-us/pregnancy/pregnancy-symptoms/article/acne-during-pregnancy</option>
        <option value="/en-us/baby/care">/en-us/baby/care</option>
        <option value="/en-us/search-results">/en-us/search-results</option>
        <option value="/en-us/toddler/development/article/baby-thumb-sucking">/en-us/toddler/development/article/baby-thumb-sucking</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/constipation-during-pregnancy">/en-us/pregnancy/pregnancy-symptoms/article/constipation-during-pregnancy</option>
        <option value="/en-us/diapers-wipes/newborn-products">/en-us/diapers-wipes/newborn-products</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/signs-of-pregnancy (will be update in january)">/en-us/pregnancy/pregnancy-symptoms/article/signs-of-pregnancy (will be update in january)</option>
        <option value="/en-us/baby/activities/article/baby-nursery-rhymes">/en-us/baby/activities/article/baby-nursery-rhymes</option>
        <option value="/en-us/pregnancy/giving-birth/article/cesarean-section">/en-us/pregnancy/giving-birth/article/cesarean-section</option>
        <option value="/en-us/pregnancy/giving-birth/article/braxton-hicks-contractions-compared-to-real-contractions">/en-us/pregnancy/giving-birth/article/braxton-hicks-contractions-compared-to-real-contractions</option>
        <option value="/en-us/diapers-wipes/pampers-splashers-swim-pants-for-boys-and-girls">/en-us/diapers-wipes/pampers-splashers-swim-pants-for-boys-and-girls</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/symphysis-pubis-dysfunction">/en-us/pregnancy/pregnancy-symptoms/article/symphysis-pubis-dysfunction</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/prenatal-care">/en-us/pregnancy/prenatal-health-and-wellness/article/prenatal-care</option>
        <option value="/en-us/baby/diapering/article/baby-poop">/en-us/baby/diapering/article/baby-poop</option>
        <option value="/en-us/best-baby-products/pregnancy-essentials/best-maternity-clothes">/en-us/best-baby-products/pregnancy-essentials/best-maternity-clothes</option>
        <option value="/en-us/pregnancy/giving-birth/article/comfort-measures-during-labor">/en-us/pregnancy/giving-birth/article/comfort-measures-during-labor</option>
        <option value="/en-us/baby/newborn/article/journey-as-a-nicu-parent">/en-us/baby/newborn/article/journey-as-a-nicu-parent</option>
        <option value="/en-us/pregnancy/baby-names/article/what-to-consider-when-naming-your-child-after-family-members">/en-us/pregnancy/baby-names/article/what-to-consider-when-naming-your-child-after-family-members</option>
        <option value="/en-us/quizzes/how-tech-savvy-are-you-as-a-parent">/en-us/quizzes/how-tech-savvy-are-you-as-a-parent</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/breast-changes-during-pregnancy">/en-us/pregnancy/pregnancy-symptoms/article/breast-changes-during-pregnancy</option>
        <option value="/en-us/baby/newborn/article/nicu-neonatal-intensive-care-unit-staff">/en-us/baby/newborn/article/nicu-neonatal-intensive-care-unit-staff</option>
        <option value="/en-us/baby/development/article/8-month-old-baby">/en-us/baby/development/article/8-month-old-baby</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/kegel-exercises">/en-us/pregnancy/prenatal-health-and-wellness/article/kegel-exercises</option>
        <option value="/en-us/baby/newborn/article/flat-head-syndrome">/en-us/baby/newborn/article/flat-head-syndrome</option>
        <option value="/en-us/best-baby-products/infant-activity/baby-costumes">/en-us/best-baby-products/infant-activity/baby-costumes</option>
        <option value="/en-us/about-us/big-acts-of-love/big-love-for-every-baby">/en-us/about-us/big-acts-of-love/big-love-for-every-baby</option>
        <option value="/en-us/baby/activities">/en-us/baby/activities</option>
        <option value="/en-us/about-us/authors/suzanne-dixon">/en-us/about-us/authors/suzanne-dixon</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/maternal-serum-alpha-fetoprotein">/en-us/pregnancy/prenatal-health-and-wellness/article/maternal-serum-alpha-fetoprotein</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/mood-swings-during-pregnancy">/en-us/pregnancy/pregnancy-symptoms/article/mood-swings-during-pregnancy</option>
        <option value="/en-us/pregnancy/giving-birth/article/vbac">/en-us/pregnancy/giving-birth/article/vbac</option>
        <option value="/en-us/pregnancy/giving-birth/article/skin-to-skin-contact">/en-us/pregnancy/giving-birth/article/skin-to-skin-contact</option>
        <option value="/en-us/diapers-wipes/pampers-baby-dry-diapers">/en-us/diapers-wipes/pampers-baby-dry-diapers</option>
        <option value="/en-us/baby/development/article/6-month-old-baby">/en-us/baby/development/article/6-month-old-baby</option>
        <option value="/en-us/baby/teething/article/teething-symptoms-for-babies">/en-us/baby/teething/article/teething-symptoms-for-babies</option>
        <option value="/en-us/about-us/diapers-and-wipes/article/choosing-the-best-diapers-for-your-baby">/en-us/about-us/diapers-and-wipes/article/choosing-the-best-diapers-for-your-baby</option>
        <option value="/en-us/baby/development/article/proactive-discipline">/en-us/baby/development/article/proactive-discipline</option>
        <option value="/en-us/baby/monthiversary-printables">/en-us/baby/monthiversary-printables</option>
        <option value="/en-us/baby/monthiversary-printables/corgis-theme">/en-us/baby/monthiversary-printables/corgis-theme</option>
        <option value="/en-us/baby/monthiversary-printables/koalas-theme">/en-us/baby/monthiversary-printables/koalas-theme</option>
        <option value="/en-us/baby/monthiversary-printables/llamas-theme">/en-us/baby/monthiversary-printables/llamas-theme</option>
        <option value="/en-us/baby/monthiversary-printables/woodland-animals-theme">/en-us/baby/monthiversary-printables/woodland-animals-theme</option>
        <option value="/en-us/baby/parenting-life/article/how-to-find-a-good-babysitter">/en-us/baby/parenting-life/article/how-to-find-a-good-babysitter</option>
        <option value="/en-us/pregnancy/baby-names/find-your-baby-name">/en-us/pregnancy/baby-names/find-your-baby-name</option>
        <option value="/lumi-smart-sleep-coach">/lumi-smart-sleep-coach</option>
        <option value="/en-us/baby/development/article/baby-separation-anxiety">/en-us/baby/development/article/baby-separation-anxiety</option>
        <option value="/en-us/baby/newborn/article/vernix">/en-us/baby/newborn/article/vernix</option>
        <option value="/en-us/registration#utm_source=segmenta_5e5z4f&utm_medium=poll&utm_campaign=early_signs">/en-us/registration#utm_source=segmenta_5e5z4f&utm_medium=poll&utm_campaign=early_signs</option>
        <option value="/en-us/about-us/sustainability/article/changing-tables">/en-us/about-us/sustainability/article/changing-tables</option>
        <option value="/en-us/products-results">/en-us/products-results</option>
        <option value="/en-us/about-us/pampers-unicef-partnership/article/pampers-and-unicef-the-journey-so-far-a-decade-of-achievement">/en-us/about-us/pampers-unicef-partnership/article/pampers-and-unicef-the-journey-so-far-a-decade-of-achievement</option>
        <option value="/en-us/toddler/sleep/article/crib-to-toddler-bed">/en-us/toddler/sleep/article/crib-to-toddler-bed</option>
        <option value="/en-us/baby/sleep">/en-us/baby/sleep</option>
        <option value="/en-us/set-activate-password/thank-you">/en-us/set-activate-password/thank-you</option>
        <option value="/en-us/pregnancy/giving-birth/article/what-is-a-doula">/en-us/pregnancy/giving-birth/article/what-is-a-doula</option>
        <option value="/en-us/baby/diapering/article/why-does-my-10-month-olds-urine-have-a-strong-smell">/en-us/baby/diapering/article/why-does-my-10-month-olds-urine-have-a-strong-smell</option>
        <option value="/en-us/best-baby-products/feeding">/en-us/best-baby-products/feeding</option>
        <option value="/en-us/baby/development/article/baby-soft-spot">/en-us/baby/development/article/baby-soft-spot</option>
        <option value="/en-us/r-hospital-checklist">/en-us/r-hospital-checklist</option>
        <option value="/en-us/toddler/sleep/article/how-to-help-your-child-through-night-terrors">/en-us/toddler/sleep/article/how-to-help-your-child-through-night-terrors</option>
        <option value="/en-us/toddler/sleep/article/teaching-sleeping-habits-toddler-sleep-training">/en-us/toddler/sleep/article/teaching-sleeping-habits-toddler-sleep-training</option>
        <option value="/en-us/toddler/sleep/article/understanding-toddler-sleep">/en-us/toddler/sleep/article/understanding-toddler-sleep</option>
        <option value="/en-us/toddler/sleep/article/when-do-kids-stop-napping">/en-us/toddler/sleep/article/when-do-kids-stop-napping</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/hip-and-pelvic-pain-during-pregnancy">/en-us/pregnancy/pregnancy-symptoms/article/hip-and-pelvic-pain-during-pregnancy</option>
        <option value="/en-us/toddler/activities/article/playdates-for-toddlers">/en-us/toddler/activities/article/playdates-for-toddlers</option>
        <option value="/en-us/pregnancy/baby-names/article/top-baby-girl-names">/en-us/pregnancy/baby-names/article/top-baby-girl-names</option>
        <option value="/en-us/best-baby-products/infant-activity/best-baby-bouncers">/en-us/best-baby-products/infant-activity/best-baby-bouncers</option>
        <option value="/en-us/diapers-wipes/pampers-cruisers-diapers/reviews">/en-us/diapers-wipes/pampers-cruisers-diapers/reviews</option>
        <option value="/en-us/pregnancy/baby-names/article/fairy-tale-baby-names">/en-us/pregnancy/baby-names/article/fairy-tale-baby-names</option>
        <option value="/en-us/toddler/nutrition">/en-us/toddler/nutrition</option>
        <option value="/en-us/best-baby-products/travel-gear/best-baby-carrier">/en-us/best-baby-products/travel-gear/best-baby-carrier</option>
        <option value="/en-us/diapers-wipes/pampers-swaddlers-diapers">/en-us/diapers-wipes/pampers-swaddlers-diapers</option>
        <option value="/en-us/pregnancy/giving-birth/article/episiotomy">/en-us/pregnancy/giving-birth/article/episiotomy</option>
        <option value="/en-us/best-baby-products/nursery/best-humidifier-for-babies">/en-us/best-baby-products/nursery/best-humidifier-for-babies</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/when-do-you-start-showing">/en-us/pregnancy/prenatal-health-and-wellness/article/when-do-you-start-showing</option>
        <option value="/en-us/toddler/development/videos/learning-to-say-i-m-sorry">/en-us/toddler/development/videos/learning-to-say-i-m-sorry</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/folic-acid-during-pregnancy">/en-us/pregnancy/prenatal-health-and-wellness/article/folic-acid-during-pregnancy</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/expectant-fathers-symptoms-and-challenges">/en-us/pregnancy/pregnancy-symptoms/article/expectant-fathers-symptoms-and-challenges</option>
        <option value="/en-us/diapers-wipes/pampers-sensitive-wipes">/en-us/diapers-wipes/pampers-sensitive-wipes</option>
        <option value="/en-us/toddler/development/article/how-to-teach-manners-to-children">/en-us/toddler/development/article/how-to-teach-manners-to-children</option>
        <option value="/en-us/pregnancy/baby-shower/article/how-to-choose-a-diaper-bag">/en-us/pregnancy/baby-shower/article/how-to-choose-a-diaper-bag</option>
        <option value="/en-us/pregnancy">/en-us/pregnancy</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-gift-ideas">/en-us/pregnancy/baby-shower/article/baby-shower-gift-ideas</option>
        <option value="/en-us/pregnancy/giving-birth/article/fifteen-moms-reveal-comforting-hospital-items">/en-us/pregnancy/giving-birth/article/fifteen-moms-reveal-comforting-hospital-items</option>
        <option value="/en-us/diapers-wipes/pampers-baby-fresh-scent-wipes">/en-us/diapers-wipes/pampers-baby-fresh-scent-wipes</option>
        <option value="/en-us/best-baby-products/nursery/best-bassinets">/en-us/best-baby-products/nursery/best-bassinets</option>
        <option value="/en-us/baby/activities/article/baby-stimulation-activities-for-your-4-month-old">/en-us/baby/activities/article/baby-stimulation-activities-for-your-4-month-old</option>
        <option value="/en-us/send-sms">/en-us/send-sms</option>
        <option value="/en-us/quizzes/wild-child-or-cool-as-cucumber">/en-us/quizzes/wild-child-or-cool-as-cucumber</option>
        <option value="/en-us/r-registry-checklist">/en-us/r-registry-checklist</option>
        <option value="/en-us/pregnancy/giving-birth/article/labor-tips-labor-advice-and-tips-for-childbirth">/en-us/pregnancy/giving-birth/article/labor-tips-labor-advice-and-tips-for-childbirth</option>
        <option value="/en-us/pregnancy/giving-birth/article/birth-announcements">/en-us/pregnancy/giving-birth/article/birth-announcements</option>
        <option value="/en-us/toddler/development/article/well-baby-visit-18-months">/en-us/toddler/development/article/well-baby-visit-18-months</option>
        <option value="/en-us/quizzes/fact-or-fiction">/en-us/quizzes/fact-or-fiction</option>
        <option value="/en-us/rewards/contact-us">/en-us/rewards/contact-us</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/full-term-pregnancy">/en-us/pregnancy/prenatal-health-and-wellness/article/full-term-pregnancy</option>
        <option value="/en-us/baby/development/article/11-month-old-baby">/en-us/baby/development/article/11-month-old-baby</option>
        <option value="/en-us/pregnancy/pregnancy-announcement/article/10-fun-ideas-for-pregnancy-announcement-cards">/en-us/pregnancy/pregnancy-announcement/article/10-fun-ideas-for-pregnancy-announcement-cards</option>
        <option value="/en-us/toddler/parenting-life/article/stress-free-birthdays-tips-to-keep-kids-happy-and-safe-at-birthday-parties">/en-us/toddler/parenting-life/article/stress-free-birthdays-tips-to-keep-kids-happy-and-safe-at-birthday-parties</option>
        <option value="/en-us/baby/activities/article/turn-your-baby-carrier-into-a-fun-halloween-costume">/en-us/baby/activities/article/turn-your-baby-carrier-into-a-fun-halloween-costume</option>
        <option value="/en-us/rewards">/en-us/rewards</option>
        <option value="/en-us/toddler/parenting-life/article/creating-a-room-for-two-toddler-and-older-child">/en-us/toddler/parenting-life/article/creating-a-room-for-two-toddler-and-older-child</option>
        <option value="/en-us/toddler/development/article/21-month-old">/en-us/toddler/development/article/21-month-old</option>
        <option value="/en-us/baby/diapering/article/3-diaper-bag-checklists-you-cant-live-without">/en-us/baby/diapering/article/3-diaper-bag-checklists-you-cant-live-without</option>
        <option value="/en-us/quizzes/maternity-photoshoot-style">/en-us/quizzes/maternity-photoshoot-style</option>
        <option value="/en-us/best-baby-products/infant-activity/best-baby-swings">/en-us/best-baby-products/infant-activity/best-baby-swings</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/signs-of-preterm-labor">/en-us/pregnancy/prenatal-health-and-wellness/article/signs-of-preterm-labor</option>
        <option value="/en-us/best-baby-products/nursery/best-diaper-pail">/en-us/best-baby-products/nursery/best-diaper-pail</option>
        <option value="/en-us/baby/parenting-life/article/parenting-tips">/en-us/baby/parenting-life/article/parenting-tips</option>
        <option value="/en-us/pregnancy/baby-shower/article/when-to-start-baby-registry">/en-us/pregnancy/baby-shower/article/when-to-start-baby-registry</option>
        <option value="/en-us/pregnancy/giving-birth/article/epidural-what-is-it-and-how-does-it-work], for example">/en-us/pregnancy/giving-birth/article/epidural-what-is-it-and-how-does-it-work], for example</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/27-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/27-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/28-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/28-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/29-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/29-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/30-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/30-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/31-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/31-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/32-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/32-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/33-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/33-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/34-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/34-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/35-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/35-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/36-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/36-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/36-weeks-pregnant position of baby">/en-us/pregnancy/pregnancy-calendar/36-weeks-pregnant position of baby</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/37-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/37-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/38-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/38-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/39-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/39-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/40-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/40-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/7-months-pregnant">/en-us/pregnancy/pregnancy-calendar/7-months-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/7-months-pregnant in kannada">/en-us/pregnancy/pregnancy-calendar/7-months-pregnant in kannada</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/7-months-pregnant tamil">/en-us/pregnancy/pregnancy-calendar/7-months-pregnant tamil</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/8-months-pregnant">/en-us/pregnancy/pregnancy-calendar/8-months-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/9-months-pregnant">/en-us/pregnancy/pregnancy-calendar/9-months-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/third-trimester">/en-us/pregnancy/pregnancy-calendar/third-trimester</option>
        <option value="/en-us/pregnancy/your-free-birthing-classes">/en-us/pregnancy/your-free-birthing-classes</option>
        <option value="/r-babynamegeneratorvortex">/r-babynamegeneratorvortex</option>
        <option value="/en-us/best-baby-products/health-safety/best-child-locks">/en-us/best-baby-products/health-safety/best-child-locks</option>
        <option value="/en-us/toddler">/en-us/toddler</option>
        <option value="/en-us/baby/development/article/when-do-babies-start-walking-your-babys-first-steps">/en-us/baby/development/article/when-do-babies-start-walking-your-babys-first-steps</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/baby-bump">/en-us/pregnancy/pregnancy-symptoms/article/baby-bump</option>
        <option value="/en-us/baby/care/article/baby-birthmarks-port-wine-stain-or-hemangiomas">/en-us/baby/care/article/baby-birthmarks-port-wine-stain-or-hemangiomas</option>
        <option value="/en-us/baby/activities/article/traveling-with-babies">/en-us/baby/activities/article/traveling-with-babies</option>
        <option value="/en-us/baby/development">/en-us/baby/development</option>
        <option value="/en-us/pregnancy/baby-shower/videos/how-to-make-a-train-diaper-cake">/en-us/pregnancy/baby-shower/videos/how-to-make-a-train-diaper-cake</option>
        <option value="/en-us/best-baby-products/feeding/best-nursing-bras">/en-us/best-baby-products/feeding/best-nursing-bras</option>
        <option value="/en-us/best-baby-products/nursery/best-nursery-gliders">/en-us/best-baby-products/nursery/best-nursery-gliders</option>
        <option value="/en-us/quizzes/celebrating-babys-first-birthday">/en-us/quizzes/celebrating-babys-first-birthday</option>
        <option value="/en-us/baby/activities/article/9-holiday-activities-to-do-with-your-baby">/en-us/baby/activities/article/9-holiday-activities-to-do-with-your-baby</option>
        <option value="/en-us/baby/parenting-life/article/sip-and-see">/en-us/baby/parenting-life/article/sip-and-see</option>
        <option value="/en-us/best-baby-products/infant-activity/best-baby-play-mats">/en-us/best-baby-products/infant-activity/best-baby-play-mats</option>
        <option value="/en-us/toddler/parenting-life/article/expectant-fathers-becoming-a-dad-for-the-second-time">/en-us/toddler/parenting-life/article/expectant-fathers-becoming-a-dad-for-the-second-time</option>
        <option value="/en-us/pregnancy/baby-names/article/international-baby-names">/en-us/pregnancy/baby-names/article/international-baby-names</option>
        <option value="/en-us/diapers-wipes/pampers-swaddlers-overnights">/en-us/diapers-wipes/pampers-swaddlers-overnights</option>
        <option value="/en-us/about-us/authors/linda-jonides">/en-us/about-us/authors/linda-jonides</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/pregnancy-stretch-marks">/en-us/pregnancy/pregnancy-symptoms/article/pregnancy-stretch-marks</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/chorionic-villus-sampling">/en-us/pregnancy/prenatal-health-and-wellness/article/chorionic-villus-sampling</option>
        <option value="/en-us/best-baby-products/strollers">/en-us/best-baby-products/strollers</option>
        <option value="/en-us/baby/parenting-life/article/reasons-to-love-the-newborn-baby-stage">/en-us/baby/parenting-life/article/reasons-to-love-the-newborn-baby-stage</option>
        <option value="/en-us/best-baby-products/nursery/best-mini-crib">/en-us/best-baby-products/nursery/best-mini-crib</option>
        <option value="/en-us/pregnancy/giving-birth/article/signs-symptoms-of-labor">/en-us/pregnancy/giving-birth/article/signs-symptoms-of-labor</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/placenta-accreta">/en-us/pregnancy/prenatal-health-and-wellness/article/placenta-accreta</option>
        <option value="/en-us/parents/health-and-lifestyle/article/pampers-pick-get-a-salon-treatment-without-getting-a-babysitter">/en-us/parents/health-and-lifestyle/article/pampers-pick-get-a-salon-treatment-without-getting-a-babysitter</option>
        <option value="/en-us/about-us/diapers-and-wipes/article/pampers-baby-wipes-how-do-they-work">/en-us/about-us/diapers-and-wipes/article/pampers-baby-wipes-how-do-they-work</option>
        <option value="/en-us/toddler/parenting-life/article/first-haircut-your-childs-first-trip-to-the-hairdresser">/en-us/toddler/parenting-life/article/first-haircut-your-childs-first-trip-to-the-hairdresser</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/14-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/14-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/15-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/15-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/15-weeks-pregnant hindi">/en-us/pregnancy/pregnancy-calendar/15-weeks-pregnant hindi</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/16-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/16-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/17-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/17-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/18-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/18-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/19-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/19-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/20-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/20-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/20-weeks-pregnant weight">/en-us/pregnancy/pregnancy-calendar/20-weeks-pregnant weight</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/21-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/21-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/22-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/22-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/23-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/23-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/24-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/24-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/25-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/25-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/26-weeks-pregnant">/en-us/pregnancy/pregnancy-calendar/26-weeks-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/4-months-pregnant">/en-us/pregnancy/pregnancy-calendar/4-months-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/5-months-pregnant">/en-us/pregnancy/pregnancy-calendar/5-months-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/5-months-pregnant food">/en-us/pregnancy/pregnancy-calendar/5-months-pregnant food</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/5-months-pregnant picturess">/en-us/pregnancy/pregnancy-calendar/5-months-pregnant picturess</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/6-months-pregnant">/en-us/pregnancy/pregnancy-calendar/6-months-pregnant</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/6-months-pregnant youtube">/en-us/pregnancy/pregnancy-calendar/6-months-pregnant youtube</option>
        <option value="/en-us/pregnancy/pregnancy-calendar/second-trimester">/en-us/pregnancy/pregnancy-calendar/second-trimester</option>
        <option value="/en-us/r-register">/en-us/r-register</option>
        <option value="/en-us/best-baby-products/strollers/best-stroller">/en-us/best-baby-products/strollers/best-stroller</option>
        <option value="/en-us/baby/newborn/article/newborn-umbilical-cord-care">/en-us/baby/newborn/article/newborn-umbilical-cord-care</option>
        <option value="/en-us/toddler/activities/article/toddler-activities-toddler-games-and-types-of-play">/en-us/toddler/activities/article/toddler-activities-toddler-games-and-types-of-play</option>
        <option value="/en-us/pampers-sleep-school">/en-us/pampers-sleep-school</option>
        <option value="/en-us/about-us">/en-us/about-us</option>
        <option value="/en-us/pregnancy/baby-names/article/girl-names-that-start-with-c">/en-us/pregnancy/baby-names/article/girl-names-that-start-with-c</option>
        <option value="/en-us/baby/parenting-life/article/5-things-to-know-before-having-a-baby">/en-us/baby/parenting-life/article/5-things-to-know-before-having-a-baby</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/sleeping-while-pregnant">/en-us/pregnancy/prenatal-health-and-wellness/article/sleeping-while-pregnant</option>
        <option value="/en-us/baby/newborn/article/baby-acne">/en-us/baby/newborn/article/baby-acne</option>
        <option value="/en-us/baby/diapering/article/changing-newborn-diapers-umbilical-cord-care">/en-us/baby/diapering/article/changing-newborn-diapers-umbilical-cord-care</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/what-causes-heartburn">/en-us/pregnancy/pregnancy-symptoms/article/what-causes-heartburn</option>
        <option value="/en-us/pregnancy/pregnancy-announcement/article/creative-pregnancy-announcement-ideas">/en-us/pregnancy/pregnancy-announcement/article/creative-pregnancy-announcement-ideas</option>
        <option value="/en-us/pregnancy/giving-birth/article/what-i-wish-id-said-to-the-nurse-who-cared-for-my-baby">/en-us/pregnancy/giving-birth/article/what-i-wish-id-said-to-the-nurse-who-cared-for-my-baby</option>
        <option value="/en-us/baby/activities/article/how-to-play-with-toddlers-learning-through-play-and-games">/en-us/baby/activities/article/how-to-play-with-toddlers-learning-through-play-and-games</option>
        <option value="/en-us/diapers-wipes/pampers-cruisers-360">/en-us/diapers-wipes/pampers-cruisers-360</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/prenatal-yoga">/en-us/pregnancy/prenatal-health-and-wellness/article/prenatal-yoga</option>
        <option value="/en-us/pregnancy/baby-names/article/top-baby-names-for-boys">/en-us/pregnancy/baby-names/article/top-baby-names-for-boys</option>
        <option value="/en-us/about-us/quality-and-safety">/en-us/about-us/quality-and-safety</option>
        <option value="/diaper-fit-finder-android">/diaper-fit-finder-android</option>
        <option value="/en-us/pregnancy/baby-shower">/en-us/pregnancy/baby-shower</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-cakes">/en-us/pregnancy/baby-shower/article/baby-shower-cakes</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-cakes-for-boys">/en-us/pregnancy/baby-shower/article/baby-shower-cakes-for-boys</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-cakes-for-girls">/en-us/pregnancy/baby-shower/article/baby-shower-cakes-for-girls</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-checklist">/en-us/pregnancy/baby-shower/article/baby-shower-checklist</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-etiquette">/en-us/pregnancy/baby-shower/article/baby-shower-etiquette</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-food-ideas">/en-us/pregnancy/baby-shower/article/baby-shower-food-ideas</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-games crossword puzzle">/en-us/pregnancy/baby-shower/article/baby-shower-games crossword puzzle</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-gifts-for-mom">/en-us/pregnancy/baby-shower/article/baby-shower-gifts-for-mom</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-ideas-throw-a-daddy-diaper-party">/en-us/pregnancy/baby-shower/article/baby-shower-ideas-throw-a-daddy-diaper-party</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-invitations">/en-us/pregnancy/baby-shower/article/baby-shower-invitations</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-prizes">/en-us/pregnancy/baby-shower/article/baby-shower-prizes</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-registry-list">/en-us/pregnancy/baby-shower/article/baby-shower-registry-list</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-themes-elephant">/en-us/pregnancy/baby-shower/article/baby-shower-themes-elephant</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-themes-for-boys">/en-us/pregnancy/baby-shower/article/baby-shower-themes-for-boys</option>
        <option value="/en-us/pregnancy/baby-shower/article/baby-shower-themes-for-girls">/en-us/pregnancy/baby-shower/article/baby-shower-themes-for-girls</option>
        <option value="/en-us/pregnancy/baby-shower/article/diaper-cake">/en-us/pregnancy/baby-shower/article/diaper-cake</option>
        <option value="/en-us/pregnancy/baby-shower/article/how-to-plan-a-baby-shower">/en-us/pregnancy/baby-shower/article/how-to-plan-a-baby-shower</option>
        <option value="/en-us/pregnancy/baby-shower/article/what-to-write-in-a-baby-shower-card">/en-us/pregnancy/baby-shower/article/what-to-write-in-a-baby-shower-card</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games">/en-us/pregnancy/baby-shower/printable-baby-shower-games</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/baby-bingo">/en-us/pregnancy/baby-shower/printable-baby-shower-games/baby-bingo</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/baby-food">/en-us/pregnancy/baby-shower/printable-baby-shower-games/baby-food</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/blow-and-pop-baby-race">/en-us/pregnancy/baby-shower/printable-baby-shower-games/blow-and-pop-baby-race</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/dont-say-baby">/en-us/pregnancy/baby-shower/printable-baby-shower-games/dont-say-baby</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/guess-how-many">/en-us/pregnancy/baby-shower/printable-baby-shower-games/guess-how-many</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/guess-who">/en-us/pregnancy/baby-shower/printable-baby-shower-games/guess-who</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/parent-trivia">/en-us/pregnancy/baby-shower/printable-baby-shower-games/parent-trivia</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/price-is-right">/en-us/pregnancy/baby-shower/printable-baby-shower-games/price-is-right</option>
        <option value="/en-us/pregnancy/baby-shower/videos/baby-shower-themes-jungle">/en-us/pregnancy/baby-shower/videos/baby-shower-themes-jungle</option>
        <option value="/en-us/pregnancy/baby-shower/videos/baby-shower-themes-princess">/en-us/pregnancy/baby-shower/videos/baby-shower-themes-princess</option>
        <option value="/en-us/pregnancy/baby-shower/videos/baby-shower-themes-stroller">/en-us/pregnancy/baby-shower/videos/baby-shower-themes-stroller</option>
        <option value="/en-us/pregnancy/baby-shower/videos/gender-reveal-baby-shower">/en-us/pregnancy/baby-shower/videos/gender-reveal-baby-shower</option>
        <option value="/en-us/pregnancy/baby-shower/videos/how-to-make-a-bathtub-diaper-cake">/en-us/pregnancy/baby-shower/videos/how-to-make-a-bathtub-diaper-cake</option>
        <option value="/en-us/pregnancy/baby-shower/videos/how-to-make-a-jungle-themed-diaper-cake">/en-us/pregnancy/baby-shower/videos/how-to-make-a-jungle-themed-diaper-cake</option>
        <option value="/en-us/pregnancy/baby-shower/videos/how-to-make-a-motorcycle-diaper-cake">/en-us/pregnancy/baby-shower/videos/how-to-make-a-motorcycle-diaper-cake</option>
        <option value="/en-us/pregnancy/baby-shower/videos/how-to-make-a-princess-castle-diaper-cake">/en-us/pregnancy/baby-shower/videos/how-to-make-a-princess-castle-diaper-cake</option>
        <option value="/en-us/pregnancy/baby-shower/videos/how-to-make-a-stroller-diaper-cake">/en-us/pregnancy/baby-shower/videos/how-to-make-a-stroller-diaper-cake</option>
        <option value="/en-us/pregnancy/baby-shower/videos/how-to-make-a-stroller-diaper-cake#main-content00text/html; charset=utf-80404">/en-us/pregnancy/baby-shower/videos/how-to-make-a-stroller-diaper-cake#main-content00text/html; charset=utf-80404</option>
        <option value="/en-us/pregnancy/birthing-classes">/en-us/pregnancy/birthing-classes</option>
        <option value="/en-us/pregnancy/birthing-classes spanish">/en-us/pregnancy/birthing-classes spanish</option>
        <option value="/en-us/pregnancy/birthing-classes/class-1">/en-us/pregnancy/birthing-classes/class-1</option>
        <option value="/en-us/pregnancy/birthing-classes/class-2">/en-us/pregnancy/birthing-classes/class-2</option>
        <option value="/en-us/pregnancy/birthing-classes/class-3">/en-us/pregnancy/birthing-classes/class-3</option>
        <option value="/en-us/pregnancy/birthing-classes/class-4">/en-us/pregnancy/birthing-classes/class-4</option>
        <option value="/en-us/pregnancy/birthing-classes/class-5">/en-us/pregnancy/birthing-classes/class-5</option>
        <option value="/en-us/pregnancy/birthing-classes/class-6">/en-us/pregnancy/birthing-classes/class-6</option>
        <option value="/en-us/pregnancy/birthing-classes/class-7">/en-us/pregnancy/birthing-classes/class-7</option>
        <option value="/en-us/pregnancy/birthing-classes/class-8">/en-us/pregnancy/birthing-classes/class-8</option>
        <option value="/en-us/pregnancy/birthing-classes/class-9">/en-us/pregnancy/birthing-classes/class-9</option>
        <option value="/en-us/pregnancy/hospital-bag-checklist-tool-page">/en-us/pregnancy/hospital-bag-checklist-tool-page</option>
        <option value="/en-us/rewards-page">/en-us/rewards-page</option>
        <option value="/en-us/quizzes/diaper-bag-essentials">/en-us/quizzes/diaper-bag-essentials</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/preeclampsia">/en-us/pregnancy/prenatal-health-and-wellness/article/preeclampsia</option>
        <option value="/en-us/best-baby-products/travel-gear/best-jogging-strollers">/en-us/best-baby-products/travel-gear/best-jogging-strollers</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/fetal-development">/en-us/pregnancy/prenatal-health-and-wellness/article/fetal-development</option>
        <option value="/diaper-fit-finder-ios">/diaper-fit-finder-ios</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/pregnancy-cravings">/en-us/pregnancy/prenatal-health-and-wellness/article/pregnancy-cravings</option>
        <option value="/en-us/about-us/authors/peter-gorski">/en-us/about-us/authors/peter-gorski</option>
        <option value="/en-us/about-us/authors/shalom-fisch">/en-us/about-us/authors/shalom-fisch</option>
        <option value="/en-us/pregnancy/multiple-pregnancy">/en-us/pregnancy/multiple-pregnancy</option>
        <option value="/en-us/pregnancy/giving-birth/article/effacement">/en-us/pregnancy/giving-birth/article/effacement</option>
        <option value="/en-us/baby/development/article/when-do-babies-sit-up">/en-us/baby/development/article/when-do-babies-sit-up</option>
        <option value="/en-us/baby/development/article/true-grit-how-babies-learn-to-be-persistent">/en-us/baby/development/article/true-grit-how-babies-learn-to-be-persistent</option>
        <option value="/en-us/pregnancy/baby-names/article/popular-baby-name-predictions-for-2018">/en-us/pregnancy/baby-names/article/popular-baby-name-predictions-for-2018</option>
        <option value="/en-us/best-baby-products/pregnancy-essentials/best-baby-shower-decorations">/en-us/best-baby-products/pregnancy-essentials/best-baby-shower-decorations</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness">/en-us/pregnancy/prenatal-health-and-wellness</option>
        <option value="/en-us/pregnancy/giving-birth/article/castor-oil-to-induce-labor">/en-us/pregnancy/giving-birth/article/castor-oil-to-induce-labor</option>
        <option value="/en-us/pregnancy/baby-shower/article/practical-baby-shower-gifts">/en-us/pregnancy/baby-shower/article/practical-baby-shower-gifts</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/healthy-diet-how-much-iron-and-calcium-is-too-much">/en-us/pregnancy/prenatal-health-and-wellness/article/healthy-diet-how-much-iron-and-calcium-is-too-much</option>
        <option value="/en-us/about-us/authors/katie-cassman">/en-us/about-us/authors/katie-cassman</option>
        <option value="/en-us/baby/newborn/article/baby-reflexes">/en-us/baby/newborn/article/baby-reflexes</option>
        <option value="/en-us/quizzes/maternity-style">/en-us/quizzes/maternity-style</option>
        <option value="/en-us/best-baby-products/nursery/best-baby-night-light">/en-us/best-baby-products/nursery/best-baby-night-light</option>
        <option value="/en-us/pregnancy/baby-shower/printable-baby-shower-games/guess-the-candy-bar">/en-us/pregnancy/baby-shower/printable-baby-shower-games/guess-the-candy-bar</option>
        <option value="/en-us/baby/newborn/article/preemie-clothes-and-diapers">/en-us/baby/newborn/article/preemie-clothes-and-diapers</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/your-guide-to-all-the-pregnancy-hormones">/en-us/pregnancy/prenatal-health-and-wellness/article/your-guide-to-all-the-pregnancy-hormones</option>
        <option value="/en-us/pregnancy/hospital-bag-checklist-tool">/en-us/pregnancy/hospital-bag-checklist-tool</option>
        <option value="/en-us/toddler/potty-training">/en-us/toddler/potty-training</option>
        <option value="/en-us/about-us/diapers-and-wipes/article/diaper-rash-prevention-why-you-should-use-baby-wipes">/en-us/about-us/diapers-and-wipes/article/diaper-rash-prevention-why-you-should-use-baby-wipes</option>
        <option value="/en-us/pregnancy/baby-shower/article/how-to-register-for-diapers">/en-us/pregnancy/baby-shower/article/how-to-register-for-diapers</option>
        <option value="/en-us/toddler/development/article/preparing-for-preschool">/en-us/toddler/development/article/preparing-for-preschool</option>
        <option value="/en-us/quizzes/spirit-animal-parent">/en-us/quizzes/spirit-animal-parent</option>
        <option value="/en-us/r-chinesegenderpredictor-v1">/en-us/r-chinesegenderpredictor-v1</option>
        <option value="/en-us/baby/activities/article/exercises-with-your-baby">/en-us/baby/activities/article/exercises-with-your-baby</option>
        <option value="/en-us/baby/care/article/how-to-hold-a-baby">/en-us/baby/care/article/how-to-hold-a-baby</option>
        <option value="/en-us/baby/newborn/article/0-month-old-baby">/en-us/baby/newborn/article/0-month-old-baby</option>
        <option value="/en-us/baby/newborn/article/1-month-old-baby">/en-us/baby/newborn/article/1-month-old-baby</option>
        <option value="/en-us/baby/newborn/article/all-about-premature-birth">/en-us/baby/newborn/article/all-about-premature-birth</option>
        <option value="/en-us/baby/newborn/article/average-baby-weight">/en-us/baby/newborn/article/average-baby-weight</option>
        <option value="/en-us/baby/parenting-life/article/coping-with-being-a-mom-the-physical-challenge">/en-us/baby/parenting-life/article/coping-with-being-a-mom-the-physical-challenge</option>
        <option value="/en-us/baby/parenting-life/article/healing-after-childbirth">/en-us/baby/parenting-life/article/healing-after-childbirth</option>
        <option value="/en-us/baby/parenting-life/article/lochia">/en-us/baby/parenting-life/article/lochia</option>
        <option value="/en-us/baby/parenting-life/article/postpartum-hair-loss">/en-us/baby/parenting-life/article/postpartum-hair-loss</option>
        <option value="/en-us/baby/parenting-life/article/postpartum-workout">/en-us/baby/parenting-life/article/postpartum-workout</option>
        <option value="/en-us/baby/parenting-life/article/puerperium-postpartum-period">/en-us/baby/parenting-life/article/puerperium-postpartum-period</option>
        <option value="/en-us/baby/parenting-life/article/weight-loss-after-pregnancy">/en-us/baby/parenting-life/article/weight-loss-after-pregnancy</option>
        <option value="/en-us/quizzes/maternity-halloween-costume">/en-us/quizzes/maternity-halloween-costume</option>
        <option value="/en-us/pregnancy/baby-shower/registry-checklist">/en-us/pregnancy/baby-shower/registry-checklist</option>
        <option value="/en-us/pregnancy/giving-birth">/en-us/pregnancy/giving-birth</option>
        <option value="/en-us/r-babynamegeneratorvortex">/en-us/r-babynamegeneratorvortex</option>
        <option value="/en-us/pregnancy/baby-names/article/unisex-baby-names">/en-us/pregnancy/baby-names/article/unisex-baby-names</option>
        <option value="/en-us/pregnancy/baby-names/article/girl-names-that-start-with-b">/en-us/pregnancy/baby-names/article/girl-names-that-start-with-b</option>
        <option value="/en-us/about-us/sustainability">/en-us/about-us/sustainability</option>
        <option value="/en-us/baby/diapering">/en-us/baby/diapering</option>
        <option value="/en-us/baby/activities/article/literacy-tips-for-babies-teaching-your-baby-to-love-reading">/en-us/baby/activities/article/literacy-tips-for-babies-teaching-your-baby-to-love-reading</option>
        <option value="/en-us/pregnancy/baby-names/article/old-fashioned-baby-names">/en-us/pregnancy/baby-names/article/old-fashioned-baby-names</option>
        <option value="/en-us/guides-and-downloadables/your-go-to-breastfeeding-guide">/en-us/guides-and-downloadables/your-go-to-breastfeeding-guide</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/trimester-by-trimester-guide-to-sleep">/en-us/pregnancy/prenatal-health-and-wellness/article/trimester-by-trimester-guide-to-sleep</option>
        <option value="/en-us/pregnancy/baby-names">/en-us/pregnancy/baby-names</option>
        <option value="/en-us/best-baby-products/health-safety/best-baby-sunscreen">/en-us/best-baby-products/health-safety/best-baby-sunscreen</option>
        <option value="/en-us/baby/development/article/object-permanence">/en-us/baby/development/article/object-permanence</option>
        <option value="/en-us/baby/care/article/babies-and-toddlers-in-cars-top-safety-tips">/en-us/baby/care/article/babies-and-toddlers-in-cars-top-safety-tips</option>
        <option value="/en-us/pregnancy/pregnancy-announcement/article/fun-ways-to-predict-your-baby-gender">/en-us/pregnancy/pregnancy-announcement/article/fun-ways-to-predict-your-baby-gender</option>
        <option value="/en-us/about-us/sustainability/article/pampers-helps-communities-to-thrive">/en-us/about-us/sustainability/article/pampers-helps-communities-to-thrive</option>
        <option value="/en-us/baby/activities/article/ten-spooktacular-halloween-baby-costumes">/en-us/baby/activities/article/ten-spooktacular-halloween-baby-costumes</option>
        <option value="/en-us/best-baby-products/feeding/best-baby-food-makers">/en-us/best-baby-products/feeding/best-baby-food-makers</option>
        <option value="/en-us/baby/teething/article/how-to-solve-baby-tooth-problems-and-injuries">/en-us/baby/teething/article/how-to-solve-baby-tooth-problems-and-injuries</option>
        <option value="/en-us/pregnancy/multiple-pregnancy/article/types-of-twins">/en-us/pregnancy/multiple-pregnancy/article/types-of-twins</option>
        <option value="/en-us/about-us/big-acts-of-love">/en-us/about-us/big-acts-of-love</option>
        <option value="/en-us/baby/sleep/article/five-ways-to-help-you-get-through-the-witching-hour">/en-us/baby/sleep/article/five-ways-to-help-you-get-through-the-witching-hour</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/when-does-morning-sickness-start">/en-us/pregnancy/pregnancy-symptoms/article/when-does-morning-sickness-start</option>
        <option value="/en-us/baby/development/article/when-do-babies-crawl">/en-us/baby/development/article/when-do-babies-crawl</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/pregnancy-diet">/en-us/pregnancy/prenatal-health-and-wellness/article/pregnancy-diet</option>
        <option value="/en-us/toddler/parenting-life/article/your-childs-first-birthday-building-lasting-memories">/en-us/toddler/parenting-life/article/your-childs-first-birthday-building-lasting-memories</option>
        <option value="/en-us/pregnancy/baby-names/article/old-fashioned-boy-names">/en-us/pregnancy/baby-names/article/old-fashioned-boy-names</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/ectopic-pregnancy">/en-us/pregnancy/prenatal-health-and-wellness/article/ectopic-pregnancy</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/diarrhea-during-pregnancy">/en-us/pregnancy/pregnancy-symptoms/article/diarrhea-during-pregnancy</option>
        <option value="/en-us/pregnancy/pregnancy-announcement">/en-us/pregnancy/pregnancy-announcement</option>
        <option value="/en-us/pregnancy/baby-names/article/mexican-baby-names">/en-us/pregnancy/baby-names/article/mexican-baby-names</option>
        <option value="/en-us/pregnancy/giving-birth/article/diastasis-recti">/en-us/pregnancy/giving-birth/article/diastasis-recti</option>
        <option value="/en-us/baby/feeding/article/finger-foods-for-baby">/en-us/baby/feeding/article/finger-foods-for-baby</option>
        <option value="/smart-baby-monitor-promocode15-thank-you">/smart-baby-monitor-promocode15-thank-you</option>
        <option value="/en-us/diapers-wipes/pampers-pure-protection-diapers-hybrid/reviews">/en-us/diapers-wipes/pampers-pure-protection-diapers-hybrid/reviews</option>
        <option value="/en-us/about-us/pampers-purpose/article/lumi">/en-us/about-us/pampers-purpose/article/lumi</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/travel-during-pregnancy">/en-us/pregnancy/prenatal-health-and-wellness/article/travel-during-pregnancy</option>
        <option value="/en-us/baby/activities/article/holiday-travel-with-babies-expert-tips">/en-us/baby/activities/article/holiday-travel-with-babies-expert-tips</option>
        <option value="/en-us/baby/diapering/article/how-to-change-a-diaper">/en-us/baby/diapering/article/how-to-change-a-diaper</option>
        <option value="/en-us/pregnancy/pregnancy-weight-gain-calculator">/en-us/pregnancy/pregnancy-weight-gain-calculator</option>
        <option value="/en-us/baby/activities/article/how-to-read-to-babies-under-2">/en-us/baby/activities/article/how-to-read-to-babies-under-2</option>
        <option value="/en-us/pregnancy/baby-names/article/girl-names-that-start-with-a">/en-us/pregnancy/baby-names/article/girl-names-that-start-with-a</option>
        <option value="/en-us/toddler/development/article/14-month-old">/en-us/toddler/development/article/14-month-old</option>
        <option value="/en-us/about-us/authors/jenna-greenspoon">/en-us/about-us/authors/jenna-greenspoon</option>
        <option value="/en-us/about-us/authors/natalie-diaz">/en-us/about-us/authors/natalie-diaz</option>
        <option value="/en-us/best-baby-products/pregnancy-essentials/best-pregnancy-pillows">/en-us/best-baby-products/pregnancy-essentials/best-pregnancy-pillows</option>
        <option value="/en-us/about-us/sustainability/article/pampers-commitment-to-babys-whole-wide-world">/en-us/about-us/sustainability/article/pampers-commitment-to-babys-whole-wide-world</option>
        <option value="/en-us/baby/parenting-life/article/introducing-your-new-baby-to-family">/en-us/baby/parenting-life/article/introducing-your-new-baby-to-family</option>
        <option value="/en-us/baby">/en-us/baby</option>
        <option value="/en-us/best-baby-products/travel-gear/best-booster-seats">/en-us/best-baby-products/travel-gear/best-booster-seats</option>
        <option value="/en-us/pregnancy/giving-birth/videos/from-i-do-to-i-m-pregnant-follow-suzie-and-steve-s-journey">/en-us/pregnancy/giving-birth/videos/from-i-do-to-i-m-pregnant-follow-suzie-and-steve-s-journey</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms">/en-us/pregnancy/pregnancy-symptoms</option>
        <option value="/en-us/quizzes/how-to-approach-your-baby-registry">/en-us/quizzes/how-to-approach-your-baby-registry</option>
        <option value="/en-us/r-planning-checklist">/en-us/r-planning-checklist</option>
        <option value="/en-us/pregnancy/baby-names/article/best-unique-baby-girl-names">/en-us/pregnancy/baby-names/article/best-unique-baby-girl-names</option>
        <option value="/en-us/baby/newborn/article/kangaroo-care-benefits">/en-us/baby/newborn/article/kangaroo-care-benefits</option>
        <option value="/en-us/r-ddc">/en-us/r-ddc</option>
        <option value="/en-us/baby/development/article/well-baby-visits">/en-us/baby/development/article/well-baby-visits</option>
        <option value="/en-us/best-baby-products/infant-activity/best-baby-toys">/en-us/best-baby-products/infant-activity/best-baby-toys</option>
        <option value="/en-us/best-baby-products/infant-activity/best-baby-activity-center">/en-us/best-baby-products/infant-activity/best-baby-activity-center</option>
        <option value="/en-us/best-baby-products/health-safety/best-bath-tubs-seats">/en-us/best-baby-products/health-safety/best-bath-tubs-seats</option>
        <option value="/en-us/pregnancy/giving-birth/videos/a-parent-is-born-preparing-a-nursery">/en-us/pregnancy/giving-birth/videos/a-parent-is-born-preparing-a-nursery</option>
        <option value="/en-us/diapers-wipes/kids-products">/en-us/diapers-wipes/kids-products</option>
        <option value="/en-us/baby/newborn/article/baby-bath-time-fun-for-you-and-your-baby">/en-us/baby/newborn/article/baby-bath-time-fun-for-you-and-your-baby</option>
        <option value="/en-us/about-us/big-acts-of-love/big-acts-of-love">/en-us/about-us/big-acts-of-love/big-acts-of-love</option>
        <option value="/en-us/baby/activities/article/newborn-activities">/en-us/baby/activities/article/newborn-activities</option>
        <option value="/en-us/pregnancy/healthy-pregnancy/article/exercise-during-pregnancy-get-moving">/en-us/pregnancy/healthy-pregnancy/article/exercise-during-pregnancy-get-moving</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/ultrasounds-during-pregnancy">/en-us/pregnancy/prenatal-health-and-wellness/article/ultrasounds-during-pregnancy</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/stress-during-pregnancy">/en-us/pregnancy/prenatal-health-and-wellness/article/stress-during-pregnancy</option>
        <option value="/en-us/baby/teething/videos/how-to-soothe-a-teething-baby">/en-us/baby/teething/videos/how-to-soothe-a-teething-baby</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/hyperemesis-gravidarum-how-i-dealt-with-it">/en-us/pregnancy/pregnancy-symptoms/article/hyperemesis-gravidarum-how-i-dealt-with-it</option>
        <option value="/en-us/toddler/parenting-life/article/introducing-your-newborn-to-their-older-siblings">/en-us/toddler/parenting-life/article/introducing-your-newborn-to-their-older-siblings</option>
        <option value="/en-us/about-us/pampers-heritage/article/pampers-history">/en-us/about-us/pampers-heritage/article/pampers-history</option>
        <option value="/en-us/pregnancy/baby-names/article/best-unique-baby-girl-names-old">/en-us/pregnancy/baby-names/article/best-unique-baby-girl-names-old</option>
        <option value="/en-us/baby/teething/article/dental-care-for-children-faqs">/en-us/baby/teething/article/dental-care-for-children-faqs</option>
        <option value="/en-us/pregnancy/giving-birth/article/tips-for-taking-great-baby-photos-in-the-hospital">/en-us/pregnancy/giving-birth/article/tips-for-taking-great-baby-photos-in-the-hospital</option>
        <option value="/en-us/diaper-wipes-pure/pampers-pure-protection-diapers/reviews">/en-us/diaper-wipes-pure/pampers-pure-protection-diapers/reviews</option>
        <option value="/en-us/about-us/big-acts-of-love/big-love-for-less-waste">/en-us/about-us/big-acts-of-love/big-love-for-less-waste</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/obgyn-finding-a-good-obstetrician">/en-us/pregnancy/prenatal-health-and-wellness/article/obgyn-finding-a-good-obstetrician</option>
        <option value="/en-us/tools-guides-quizzes-results">/en-us/tools-guides-quizzes-results</option>
        <option value="/en-us/about-us/authors/kylee-money">/en-us/about-us/authors/kylee-money</option>
        <option value="/en-us/about-us/pampers-purpose">/en-us/about-us/pampers-purpose</option>
        <option value="/en-us/baby/newborn/article/coping-with-worry-of-premature-baby">/en-us/baby/newborn/article/coping-with-worry-of-premature-baby</option>
        <option value="/en-us/toddler/activities/article/halloween-crafts-for-toddlers-and-preschoolers">/en-us/toddler/activities/article/halloween-crafts-for-toddlers-and-preschoolers</option>
        <option value="/en-us/baby/development/article/4-month-old-baby">/en-us/baby/development/article/4-month-old-baby</option>
        <option value="/en-us/diapers-wipes">/en-us/diapers-wipes</option>
        <option value="/en-us/pregnancy/baby-names/article/last-names-that-make-great-first-names">/en-us/pregnancy/baby-names/article/last-names-that-make-great-first-names</option>
        <option value="/en-us/baby/feeding/article/eating-out-dining-at-a-restaurant-with-your-baby">/en-us/baby/feeding/article/eating-out-dining-at-a-restaurant-with-your-baby</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/pregnancy-discharge">/en-us/pregnancy/pregnancy-symptoms/article/pregnancy-discharge</option>
        <option value="/en-us/diapers-wipes/pampers-baby-dry-diapers/reviews">/en-us/diapers-wipes/pampers-baby-dry-diapers/reviews</option>
        <option value="/en-us/diapers-wipes/pampers-swaddlers-diapers/reviews">/en-us/diapers-wipes/pampers-swaddlers-diapers/reviews</option>
        <option value="/en-us/about-us/pampers-purpose/article/clinical-resources-baby-skin-care">/en-us/about-us/pampers-purpose/article/clinical-resources-baby-skin-care</option>
        <option value="/en-us/quizzes/baby-arrival-checklist-quiz">/en-us/quizzes/baby-arrival-checklist-quiz</option>
        <option value="/en-us/toddler/parenting-life/article/what-moms-of-babies-close-in-age-need-to-know">/en-us/toddler/parenting-life/article/what-moms-of-babies-close-in-age-need-to-know</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/tdap-pregnancy">/en-us/pregnancy/prenatal-health-and-wellness/article/tdap-pregnancy</option>
        <option value="/en-us/best-baby-products/infant-activity/best-baby-books">/en-us/best-baby-products/infant-activity/best-baby-books</option>
        <option value="/en-us/baby/diapering/article/baby-pee-and-wet-diapers">/en-us/baby/diapering/article/baby-pee-and-wet-diapers</option>
        <option value="/en-us/baby/activities/article/baby-swimming">/en-us/baby/activities/article/baby-swimming</option>
        <option value="/en-us/toddler/sleep">/en-us/toddler/sleep</option>
        <option value="/en-us/about-us/authors/kim-galeaz">/en-us/about-us/authors/kim-galeaz</option>
        <option value="/en-us/baby/feeding/article/baby-feeding-schedule">/en-us/baby/feeding/article/baby-feeding-schedule</option>
        <option value="/en-us/baby/feeding/article/baby-food-recipes">/en-us/baby/feeding/article/baby-food-recipes</option>
        <option value="/en-us/baby/feeding/article/baby-led-weaning">/en-us/baby/feeding/article/baby-led-weaning</option>
        <option value="/en-us/baby/feeding/article/breast-engorgement">/en-us/baby/feeding/article/breast-engorgement</option>
        <option value="/en-us/baby/feeding/article/breast-feeding-101">/en-us/baby/feeding/article/breast-feeding-101</option>
        <option value="/en-us/baby/feeding/article/breast-milk-storage">/en-us/baby/feeding/article/breast-milk-storage</option>
        <option value="/en-us/baby/feeding/article/breastfeeding-diet">/en-us/baby/feeding/article/breastfeeding-diet</option>
        <option value="/en-us/baby/feeding/article/breastfeeding-facts">/en-us/baby/feeding/article/breastfeeding-facts</option>
        <option value="/en-us/baby/feeding/article/breastfeeding-in-public">/en-us/baby/feeding/article/breastfeeding-in-public</option>
        <option value="/en-us/baby/feeding/article/breastfeeding-tips">/en-us/baby/feeding/article/breastfeeding-tips</option>
        <option value="/en-us/baby/feeding/article/caffeine-and-breastfeeding">/en-us/baby/feeding/article/caffeine-and-breastfeeding</option>
        <option value="/en-us/baby/feeding/article/caffeine-and-breastfeeding-old">/en-us/baby/feeding/article/caffeine-and-breastfeeding-old</option>
        <option value="/en-us/baby/feeding/article/can-i-spoon-feed-rice-cereal-to-my-three-month-old">/en-us/baby/feeding/article/can-i-spoon-feed-rice-cereal-to-my-three-month-old</option>
        <option value="/en-us/baby/feeding/article/clogged-milk-duct">/en-us/baby/feeding/article/clogged-milk-duct</option>
        <option value="/en-us/baby/feeding/article/expressing-milk-at-work-going-back-to-work-while-breastfeeding">/en-us/baby/feeding/article/expressing-milk-at-work-going-back-to-work-while-breastfeeding</option>
        <option value="/en-us/baby/feeding/article/formula-feeding-guidelines">/en-us/baby/feeding/article/formula-feeding-guidelines</option>
        <option value="/en-us/baby/feeding/article/how-to-increase-your-breast-milk-supply">/en-us/baby/feeding/article/how-to-increase-your-breast-milk-supply</option>
        <option value="/en-us/baby/feeding/article/lactation-consultant-breastfeeding-tips">/en-us/baby/feeding/article/lactation-consultant-breastfeeding-tips</option>
        <option value="/en-us/baby/feeding/article/lactation-consultant-what-you-need-to-know">/en-us/baby/feeding/article/lactation-consultant-what-you-need-to-know</option>
        <option value="/en-us/baby/feeding/article/nipple-bleeding-while-breastfeeding">/en-us/baby/feeding/article/nipple-bleeding-while-breastfeeding</option>
        <option value="/en-us/baby/feeding/article/sore-nipples">/en-us/baby/feeding/article/sore-nipples</option>
        <option value="/en-us/baby/feeding/article/starting-solid-foods-one-at-a-time">/en-us/baby/feeding/article/starting-solid-foods-one-at-a-time</option>
        <option value="/en-us/baby/feeding/article/weaning-when-to-wean-a-baby-off-breastmilk">/en-us/baby/feeding/article/weaning-when-to-wean-a-baby-off-breastmilk</option>
        <option value="/en-us/baby/feeding/article/what-is-mastitis-symptoms-and-how-to-treat-it">/en-us/baby/feeding/article/what-is-mastitis-symptoms-and-how-to-treat-it</option>
        <option value="/en-us/baby/feeding/article/when-can-babies-drink-water">/en-us/baby/feeding/article/when-can-babies-drink-water</option>
        <option value="/en-us/baby/feeding/article/when-do-babies-stop-drinking-formula">/en-us/baby/feeding/article/when-do-babies-stop-drinking-formula</option>
        <option value="/en-us/baby/newborn/article/breastfeeding-premature-babies">/en-us/baby/newborn/article/breastfeeding-premature-babies</option>
        <option value="/en-us/baby/newborn/article/cluster-feeding">/en-us/baby/newborn/article/cluster-feeding</option>
        <option value="/en-us/baby/newborn/article/colostrum">/en-us/baby/newborn/article/colostrum</option>
        <option value="/en-us/baby/newborn/article/how-to-burp-a-baby">/en-us/baby/newborn/article/how-to-burp-a-baby</option>
        <option value="/en-us/baby/newborn/article/newborn-feeding-the-first-feed">/en-us/baby/newborn/article/newborn-feeding-the-first-feed</option>
        <option value="/en-us/baby/newborn/article/paced-bottle-feeding">/en-us/baby/newborn/article/paced-bottle-feeding</option>
        <option value="/en-us/baby/newborn/article/vitamin-d-for-babies">/en-us/baby/newborn/article/vitamin-d-for-babies</option>
        <option value="/en-us/about-us/diapers-and-wipes">/en-us/about-us/diapers-and-wipes</option>
        <option value="/en-us/pregnancy/baby-names/article/unique-baby-boy-names">/en-us/pregnancy/baby-names/article/unique-baby-boy-names</option>
        <option value="/en-us/pregnancy/prenatal-health-and-wellness/article/glucose-test-pregnancy">/en-us/pregnancy/prenatal-health-and-wellness/article/glucose-test-pregnancy</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/pregnancy-brain-fact-or-fiction">/en-us/pregnancy/pregnancy-symptoms/article/pregnancy-brain-fact-or-fiction</option>
        <option value="/en-us/pregnancy/pregnancy-symptoms/article/spotting-early-pregnancy">/en-us/pregnancy/pregnancy-symptoms/article/spotting-early-pregnancy</option>
        <option value="/en-us/toddler/development/article/pincer-grasp">/en-us/toddler/development/article/pincer-grasp</option>
      </select><br>
      <input type="submit" value="Submit">
    </form>
<?php
if (!empty($user) && !empty($path)) {
?>
  <form method="get">
    <input type="hidden" name="userId" value="<?php echo $user; ?>">
    <input type="hidden" name="reset" value="1">
    <input type="submit" value="Reset user data">
  </form>
<?php
  $cluster = fetchCluster($event, $settings);
  pickPagesInCluster($cluster['websiteClusterName']);
?>
  <br><br>
  <form method="get">
    <input type="hidden" name="userId" value="<?php echo $user; ?>">
    <input type="hidden" name="websitePageVisitCount" value="<?php echo $cluster['websitePageVisitCount']; ?>">
    <input type="hidden" name="websiteScoreAverageUser" value="<?php echo json_encode($cluster['websiteScoreAverageUser']); ?>">
    <input type="hidden" name="websiteScoreAbsoluteUser" value="<?php echo json_encode($cluster['websiteScoreAbsoluteUser']); ?>">
    <input type="hidden" name="websiteClusterName" value="<?php echo $cluster['websiteClusterName']; ?>">
    <input type="submit" value="Push to Segment">
  </form>
<?php
}
?>
  </body>
</html>