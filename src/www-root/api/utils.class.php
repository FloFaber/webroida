<?php

$conf = json_decode(file_get_contents(__DIR__."/config.json"), true);
if(!empty($conf["proxy"]["host"])){
  $proxy = $conf["proxy"]["host"].":".$conf["proxy"]["port"];
  stream_context_set_default(
    ['http'=>['proxy'=>$proxy]]
  );
}else{
  $proxy = "";
}

function random($lenght){
  $final = "";
  $random = array();
  for($i = 0; $i < $lenght; $i++){
    $charS = "abcdefghijklmnopqrstuvwxyz";
    $charL = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $rand1 = $charS[rand(0, strlen($charS)-1)];
    $rand2 = $charL[rand(0, strlen($charL)-1)];
    $rand3 = rand(0, 9);

    $rand_sel = rand(1, 3);
    if($rand_sel == 1){
    array_push($random, $rand1);
    }elseif($rand_sel == 2){
    array_push($random, $rand2);
    }elseif($rand_sel == 3){
    array_push($random, $rand3);
    }else{
    array_push($random, "_");
    }
  }

  for($i = 0; $i < count($random); $i++){
    $final = $final.$random[$i];
  }
  return $final;
}

function startsWith($haystack, $needle){
  $length = strlen($needle);
  return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle){
  $length = strlen($needle);
  if($length == 0){
    return true;
  }
  return (substr($haystack, -$length) === $needle);
}

function findLine($file, $str){

  $lines = text2ArrayAndSo($file);

  $current = 0;
  foreach($lines as $line){
    $line = trim($line);
    $current++;
    if($line == $str){
      return $current;
    }
  }
  return false;
}

function getLine($file, $line){
  
  $lines = text2ArrayAndSo($file);

  if(count($lines) >= $line and $line > 0){
    return $lines[$line-1]; // minus one because arrays start at 0 kappa
  }else{
    return false;
  }
}

function array_insert($array, $position, $insert){
	array_splice($array, $position, 0, $insert);
	return $array;
}

function remLine($file, $line){
  $lines = text2ArrayAndSo($file);

  array_splice($lines, $line - 1, 1);
  return $lines;
}





function moveLine($file, $from, $to){
  $file = text2ArrayAndSo($file);

  // get the source line
  $sourceline = getLine($file, $from);

  

  // remove the source line
  $file = remLine($file, $from);

  // insert source line into destination

  $file = array_insert($file, $to - 1, $sourceline);
  //error_log("moving line ".$from." to ".$to." | from: ".json_encode($sourceline)." | TEXT: ".json_encode($file));

  return implode("\n", $file);

}




function text2ArrayAndSo($str){
  if(!is_array($str)){
    if(file_exists($str)){
      $content = file_get_contents($str);
    }else{
      $content = trim($str);
    }
    $lines = explode("\n", $content);
  }else{
    $lines = $str;
  }
  return $lines;
}


function shuffleLine($file){
  $lines = text2ArrayAndSo($file);
	shuffle($lines);
	return $lines;	
}


function youtubeSearch($keyword, $duration){

  global $proxy;

  if($duration != "any" and $duration != "short" and $duration != "medium" and $duration != "long"){
    $duration = "any";
  }

  $keyword = urlencode($keyword);
  $ch = curl_init();          // https://www.googleapis.com/youtube/v3/search?part=snippet&q=sofa+surfers&type=video&eventType=completed&maxResults=10&videoDuration=any&key=AIzaSyBAyf_ElJ8tG5os3_-dIoooPhCIr1mNiec
  curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/youtube/v3/search?part=snippet&q='.$keyword.'&order=viewCount&type=video&maxResults=10&videoDuration='.$duration.'&key=AIzaSyBAyf_ElJ8tG5os3_-dIoooPhCIr1mNiec');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

  if(!empty($proxy)){
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
  }

  $data = json_decode(curl_exec($ch), true);
  
  curl_close($ch);

  // now lets get to it
  $results = array();
  $items = $data["items"];

  foreach($items as $item){
    $videoid = $item["id"]["videoId"];
    $title = $item["snippet"]["title"];
    if(!empty($videoid) and !empty($title)){

      // get duration
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/youtube/v3/videos?id='.$videoid.'&part=contentDetails&key=AIzaSyBAyf_ElJ8tG5os3_-dIoooPhCIr1mNiec');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

      if(!empty($proxy)){
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
      }

      $data = json_decode(curl_exec($ch), true);

      $duration = covtime($data["items"][0]["contentDetails"]["duration"]);

      array_push($results, array("videoid"=>$videoid, "title"=>$title, "duration"=>$duration));

      curl_close($ch);

    }
  }

  return $results;

}

function covtime($youtube_time){
  $start = new DateTime('@0'); // Unix epoch
  $start->add(new DateInterval($youtube_time));

  $time = $start->format('H:i:s');
  if(startsWith($time, "00:")){
    $time = substr($time, 3);
  }
  return $time;
}
