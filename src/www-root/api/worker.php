<?php

require(__DIR__."/webroida.class.php");
require(__DIR__."/db.php");
//require(__DIR__."/utils.class.php");

$wr = new Webroida;

/*

  PROGRESS VARIABLE EXPLAINED:

  0: nothing, not in progress but in queue (everything can be done with it (delete, edit prio, etc...))
  1: currently getting data (can be deleted and edited)
  2: data is known and can be downloaded (can be deleted and edited)
  3: is now beeing downloaded (cant be deleted or edited, will soon be in songs)

*/

file_put_contents("/var/log/webroida.log", "WORKER STARTED WITH PARAMTER: ".$argv[1]."\n", FILE_APPEND);


while(true){

  shell_exec("chmod 777 -R /mpd/music/");

  $conf = json_decode(file_get_contents(__DIR__."/config.json"), true);
  if(!empty($conf["proxy"]["host"])){
    $proxy = "--proxy ".$conf["proxy"]["host"].":".$conf["proxy"]["port"];
  }else{
    $proxy = "";
  }

  unset($out);
  unset($std);

  if($argv[1] == "convert"){
    $stmt = $db->prepare("SELECT * FROM queue WHERE title NOT LIKE 'ERROR:%' AND progress = 2 ORDER BY prio DESC LIMIT 1");
    if($stmt->execute()){
      //echo "check if progress is 1\n";
      // if rowcount is not 0 than convert, if it is than go and get title, duration, thumbnail, etc...
      if($stmt->rowCount() > 0){
        //echo "found!\n";
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
  
          $id = $row["id"];
          $videoid = $row["videoid"];
          $title = $row["title"];
          $url = $row["url"];
          $duration = $row["duration"];
          $thumbnail = $row["thumbnail"];
    
          // now update to progress 3
          $stmt_sec = $db->prepare("UPDATE queue SET progress = 3 WHERE id = :id");
          $stmt_sec->execute(array(":id"=>$id));
    
          echo "start downloading (videoid: ".$videoid.")\n";
          // now start downloading
          exec("youtube-dl ".$proxy." --extract-audio --audio-format mp3 --postprocessor-args \"-threads 4\" --no-playlist -o \"/mpd/music/%(id)s.%(ext)s\" \"".$url."\"", $out, $std);
          if($std == 0){
            // success
            echo "finished downloading (videoid: ".$videoid.")\n";
            // add into songs table
            $stmt_sec = $db->prepare("INSERT INTO songs (title, url, file, duration, thumbnail) VALUES (:title, :url, :file, :duration, :thumbnail)");
            $stmt_sec->execute(array(
              ":title"=>$title,
              ":url"=>$url,
              ":file"=>$videoid.".mp3",
              ":duration"=>$duration,
              ":thumbnail"=>$thumbnail
            ));
    
            $stmt_sec = $db->prepare("DELETE FROM queue WHERE id = :id");
            $stmt_sec->execute(array(":id"=>$id));

            // now add to playlist
            file_put_contents("/mpd/playlists/songs.m3u", $videoid.".mp3\n", FILE_APPEND);
            $wr->updateDB();
            if(endsWith($wr->playing()["current_raw"], ".mp3")){
              $wr->add($videoid.".mp3");
            }

    
          }else{
            echo "\nERROR:\n".$out[count($out)-1]."\n";
            $stmt_sec = $db->prepare("UPDATE queue SET progress = 1, title = :title WHERE id = :id");
            $stmt_sec->execute(array(":title"=>"ERROR: ".json_encode($out), ":id"=>$id));
          }
        }
      }
    }
  }elseif($argv[1] == "get"){
    // go and get title, duration, etc...
    $stmt = $db->prepare("SELECT * FROM queue WHERE title NOT LIKE 'ERROR:%' AND progress = 0 AND title = '' AND duration = '' ORDER BY prio DESC, id LIMIT 1");
    if($stmt->execute()){

      // if rowcount is not 0 than get title, duration, thumbnail, etc... else just sleep a couple of seconds and begin new
      if($stmt->rowCount() > 0){

        while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

          $id = $row["id"];
          $url = $row["url"];

          // now update to progress 1
          $stmt_sec = $db->prepare("UPDATE queue SET progress = 1 WHERE id = :id");
          $stmt_sec->execute(array(":id"=>$id));

          unset($out);
          echo "start getting data (url: ".$url.")\n";
          exec("youtube-dl ".$proxy." --no-playlist --get-id --get-title --get-duration --get-thumbnail \"".$url."\"", $out, $std);

          /*
            OUTPUT OF $out
            0: title
            1: id
            2: thumbnail
            3: duration
          */

          $found = 0;

          if($std == 0){

            // check if this song is already in songs or in the queue
            $stmt_sec = $db->prepare("SELECT id FROM songs WHERE file = :file");
            if($stmt_sec->execute(array(":file" => $out[1].".mp3"))){
              $found += $stmt_sec->rowCount();
            }

            // check if this song is already in songs or in the queue
            $stmt_sec = $db->prepare("SELECT id FROM queue WHERE videoid = :videoid AND id != :id");
            if($stmt_sec->execute(array(":videoid" => $out[1], ":id" => $id))){
              $found += $stmt_sec->rowCount();
            }

            if($found == 0){
              echo "finished getting data (url: ".$url.")\n";
              // update to progress 1
              $stmt_sec = $db->prepare("UPDATE queue SET title = :title, videoid = :videoid, thumbnail = :thumbnail, duration = :duration, progress = 2 WHERE id = :id");
              $stmt_sec->execute(array(
                ":id"=>$id,
                ":title"=>$out[0],
                ":videoid"=>$out[1],
                ":thumbnail"=>$out[2],
                ":duration"=>$out[3]
              ));
            }else{
              $stmt_sec = $db->prepare("DELETE FROM queue WHERE id = :id");
              $stmt_sec->execute(array(":id" => $id));
            }            

          }else{
            echo "Error getting data (url: ".$url.") (".json_encode($out).")\n";
            // error
            $stmt_sec = $db->prepare("UPDATE queue SET title = :title, progress = 2 WHERE id = :id");
            $stmt_sec->execute(array(":title"=>"ERROR: ".json_encode($out), ":id" => $id));
          }
        }
      }
    }
  }

  sleep(5);
  
}