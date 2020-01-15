<?php

require_once("utils.class.php");

class Webroida{

  private $db;
  private $db_host = "localhost";
  private $db_user;
  private $db_name;
  private $db_pass;

  public $volume;
  public $time;
  public $status;
  public $proxy;

  // on construct connect to DB
  function __construct(){

    $this->db_user = trim(file_get_contents("/etc/webroida/db.name"));
    $this->db_name = trim(file_get_contents("/etc/webroida/db.name"));
    $this->db_pass = trim(file_get_contents("/etc/webroida/db.pass"));

    try{
      $this->db = new PDO("mysql:host=".$this->db_host.";dbname=".$this->db_name, $this->db_user, $this->db_pass);
    }catch(PDOException $e){
      die();
    }

    $this->status = explode("\n", trim(shell_exec("mpc status")));

    $conf = json_decode(file_get_contents(__DIR__."/config.json"), true);
    if(!empty($conf["proxy"]["host"])){
      $this->proxy = $conf["proxy"]["host"].":".$conf["proxy"]["port"];
      stream_context_set_default(
        ['http'=>['proxy'=>$this->proxy]]
      );
    }else{
      $this->proxy = "";
    }

  }

  // play specific sender
  function playSender($address){
    $stmt = $this->db->prepare("SELECT * FROM senders ORDER BY id");
    $stmt->execute();
    file_put_contents("/mpd/playlists/senders.m3u", "");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
      file_put_contents("/mpd/playlists/senders.m3u", $row["address"]."\n", FILE_APPEND);
    }

    $this->loadPlaylist("senders");
    $line = findLine($this->playlist(), $address);
    if($line){
      shell_exec("mpc play ".$line);
    }
  }


  // return player stats
  function update(){

    $stats = array(
      "current"=>$this->current(),
      "volume"=>$this->volume(),
      "time"=>$this->time(),
      "playing"=>$this->playing(),
      "crossfade"=>$this->crossfade()
    );

    $x = explode("  ", $this->status[count($this->status)-1]);
    foreach($x as $setting){
      if(!empty($setting)){
        $setting_name = trim(explode(":", $setting)[0]);
        if(isset(explode(":", $setting)[1])){
          $setting_value = trim(explode(":", $setting)[1]);
        }else{
          $setting_value = "";
        }
        
        if($setting_name != "volume"){
          $stats[$setting_name] = $setting_value;
        }
      }
    }

    return array("success"=>true, "msg"=>null, "stats"=>$stats, "queue"=>$this->getQueue()["queue"], "songs"=>$this->getSongs()["songs"]);

  }

  // add sender to DB and playlist
  function addSender($name, $address){
    if(!empty($name) and !empty($address)){
      $stmt = $this->db->prepare("INSERT INTO senders (name, address) VALUES (:name, :address)");
      if($stmt->execute(array(":name"=>$name, ":address"=>$address))){
        if(file_put_contents("/mpd/playlists/senders.m3u", $address."\n", FILE_APPEND)){
          $this->loadPlaylist("senders");
          return array("success"=>true, "msg"=>null);
        }else{
          return array("success"=>false, "msg"=>"Error writing playlist");
        }
      }else{
        return array("success"=>false, "msg"=>"DB Error");
      }
    }else{
      return array("success"=>false, "msg"=>"Something is empty");
    }
  }

  // get senders
  function getSender(){
    $senders = array();
    $stmt = $this->db->prepare("SELECT * FROM senders");
    if($stmt->execute()){
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        array_push($senders, array("name"=>$row["name"], "address"=>$row["address"]));
      }
      return array("success"=>true, "msg"=>null, "senders"=>$senders);
    }else{
      return array("success"=>false, "msg"=>"DB Error");
    }
  }

  // function to remove a sender
  function delSender($address){
  

    // delete from DB
    $stmt = $this->db->prepare("DELETE FROM senders WHERE address = :address");
    $stmt->execute(array(":address"=>$address));


    // get pos in playlist
    $pos = findLine($this->playlist(), $address);
    if($pos){
      // delete from playlist
      shell_exec("mpc del ".$pos);
    }

    // save to playlist file
    file_put_contents("/mpd/playlists/senders.m3u", "");
    foreach($this->getSender()["senders"] as $sender){
      file_put_contents("/mpd/playlists/senders.m3u", $sender["address"]."\n", FILE_APPEND);
    }

  }



  // function to clear the Q
  function clearQueue(){
    shell_exec("mpc clear");
    

    $files = glob("/mpd/music/*");
    foreach($files as $file){
      if(is_file($file)){
        unlink($file);
      }
    }

    if(file_put_contents("/mpd/playlists/songs.m3u", "")){
      $stmt = $this->db->prepare("TRUNCATE songs");
      if($stmt->execute()){
        return array("success" => true, "msg" => null);
      }else{
        return array("success" => false, "msg" => "DB Error");
      }
    }else{
      return array("success" => false, "msg" => "File error");
    }
  }



  // ok so here this shit is getting serious (youtube support)
  function addQueue($user, $url){


    $urls = explode("\n", trim($url));
    $title = "";
    $duration = "";
    $progress = 0;
    $videoid = "";

    if(count($urls) == 1){

      // get title
      $stmt = $this->db->prepare("SELECT * FROM search USE INDEX(find) WHERE user = :user AND url = :url");
      $stmt->execute(array(":user"=>$user, ":url"=>$urls[0]));
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        $title = $row["title"];
        $duration = $row["duration"];
        $videoid = $row["videoid"];
      }

      if(!empty($title) and !empty($duration)){
        $progress = 2;
      }

      // delete from search table
      $stmt = $this->db->prepare("DELETE FROM search WHERE user = :user AND url = :url");
      $stmt->execute(array(":user"=>$user, ":url"=>$urls[0]));
    }

    $stmt = $this->db->prepare("INSERT INTO queue (url, title, videoid, duration, progress) VALUES (:url, :title, :videoid, :duration, :progress)");
    foreach($urls as $url){
      if(filter_var($url, FILTER_VALIDATE_URL)){
        $stmt->execute(array(":url"=>$url, ":title"=>$title, ":videoid"=>$videoid, ":duration"=>$duration, ":progress"=>$progress));
      }
    }

    return array("success"=>true, "msg"=>null, "urls"=>$urls);
  }

  // function to get songs in process
  function getQueue(){
    $stmt = $this->db->prepare("SELECT * FROM queue ORDER BY progress DESC, prio DESC");
    if($stmt->execute()){
      $queue = array();
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        array_push($queue, array("id"=>$row["id"], "title"=>$row["title"], "videoid"=>$row["videoid"], "url"=>$row["url"], "prio"=>$row["prio"], "progress"=>$row["progress"], "duration"=>$row["duration"]));
      }
      return array("success"=>true, "msg"=>null, "queue"=>$queue);
    }else{
      return array("success"=>false, "msg"=>"DB Error");
    }
  }

  // function to delete a song from queue
  function delQueue($id){
    $stmt = $this->db->prepare("DELETE FROM queue WHERE id = :id AND (progress <> 2 OR (progress = 2 AND title LIKE 'ERROR%'))");
    if($stmt->execute(array(":id"=>$id))){
      return array("success"=>true, "msg"=>null);
    }else{
      return array("success"=>false, "msg"=>"DB Error");
    }
  }

  function setPrio($id, $prio){
    if(is_numeric($prio)){
      $stmt = $this->db->prepare("UPDATE queue SET prio = :prio WHERE id = :id");
      if($stmt->execute(array(":id"=>$id, ":prio"=>$prio))){
        return array("success" => true, "msg" => null);
      }else{
        return array("success" => false, "msg" => "DB Error");
      }
    }else{
      return array("success" => false, "msg" => "Invalid prio");
    }
   
  }



  // function to get songs
  function getSongs(){
    $stmt = $this->db->prepare("SELECT * FROM songs ORDER BY id");
    if($stmt->execute()){
      $songs = array();
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        array_push($songs, array("id"=>$row["id"], "title"=>$row["title"], "url"=>$row["url"], "file"=>$row["file"], "duration"=>$row["duration"], "thumbnail"=>$row["thumbnail"]));
      }
      return array("success"=>true, "msg"=>null, "songs"=>$songs);
    }else{
      return array("success"=>false, "msg"=>"DB Error");
    }
  }

  // function to play song
  function playSong($file){

    file_put_contents("/mpd/playlists/songs.m3u", "");
    foreach($this->getSongs()["songs"] as $song){
      file_put_contents("/mpd/playlists/songs.m3u", $song["file"]."\n", FILE_APPEND);
    }

    $this->loadPlaylist("songs");
    //error_log($this->playlist());
    $line = findLine($this->playlist(), $file);
    error_log(json_encode($this->playlist()));
    if($line){
      shell_exec("mpc play ".$line);
    }
  }


  // function to move a song
  function moveSong($from, $to){
    if(is_numeric($from) and is_numeric($to)){

      // now move song
      $current = $this->current();
      if(endswith($current, ".mp3")){
        // move the song in playlist
        shell_exec("mpc move ".$from." ".$to);
        $playlist = explode("\n", $this->playlist());
      }else{
        $playlist = array_filter(explode("\n", moveLine("/mpd/playlists/songs.m3u", $from, $to)));
      }

      // temporary save the DB in an array
      $songs = array();
      $stmt = $this->db->prepare("SELECT * FROM songs");
      $stmt->execute();
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        $file = $row["file"];
        $songs[$file] = $row;
      }

      
      
      
      // delete old DB table
      $stmt = $this->db->prepare("TRUNCATE songs");
      $stmt->execute();

      // and insert everything in the right order
      $handle = fopen("/mpd/playlists/songs.m3u", "w");
      ftruncate($handle, 0);

      $stmt = $this->db->prepare("INSERT INTO songs (title, url, file, duration, thumbnail) VALUES (:title, :url, :file, :duration, :thumbnail)");
      foreach($playlist as $song){

        // $song = str_replace("file:///mpd/music/", "", $song);
        
        fwrite($handle, $song."\n");

        $title = $songs[$song]["title"];
        $url = $songs[$song]["url"];
        $file = $songs[$song]["file"];
        $duration = $songs[$song]["duration"];
        $thumbnail = $songs[$song]["thumbnail"];

        $stmt->execute(array(
          ":title"=>$title,
          ":url"=>$url,
          ":file"=>$file,
          ":duration"=>$duration,
          ":thumbnail"=>$thumbnail
        ));
      }

      fclose($handle);

    }
  }

  // shuffle
  function shuffleQueue(){


    // now move song
    $current = $this->current();
    if(endswith($current, ".mp3")){
      // shuffle queue
      shell_exec("mpc shuffle");
      $playlist = explode("\n", $this->playlist());
    }else{
      $playlist = shuffleLine("/mpd/playlists/songs.m3u");
    }

    // temporary save the DB in an array
    $songs = array();
    $stmt = $this->db->prepare("SELECT * FROM songs");
    $stmt->execute();
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
      $file = $row["file"];
      $songs[$file] = $row;
    }

    
    
    // delete old DB table
    $stmt = $this->db->prepare("TRUNCATE songs");
    $stmt->execute();

    $handle = fopen("/mpd/playlists/songs.m3u", "w");
    ftruncate($handle, 0);

    // and insert everything in the right order
    $stmt = $this->db->prepare("INSERT INTO songs (title, url, file, duration, thumbnail) VALUES (:title, :url, :file, :duration, :thumbnail)");
    foreach($playlist as $song){

      // $song = str_replace("file:///mpd/music/", "", $song);

      fwrite($handle, $song."\n");
      
      $title = $songs[$song]["title"];
      $url = $songs[$song]["url"];
      $file = $songs[$song]["file"];
      $duration = $songs[$song]["duration"];
      $thumbnail = $songs[$song]["thumbnail"];

      $stmt->execute(array(
        ":title"=>$title,
        ":url"=>$url,
        ":file"=>$file,
        ":duration"=>$duration,
        ":thumbnail"=>$thumbnail
      ));
    }

    fclose($handle);

    if(endswith($current, ".mp3")){
      $this->loadPlaylist("songs");
      $this->playSong(getLine($this->playlist(), 1));
    }    
  }

  // function to remove song
  function delSong($file){


    // delete from DB
    $stmt = $this->db->prepare("DELETE FROM songs WHERE file = :file");
    $stmt->execute(array(":file"=>$file));


    // get pos in playlist
    $pos = findLine($this->playlist(), $file);
    if($pos){
      // delete from playlist
      shell_exec("mpc del ".$pos);
    }

    // save to playlist file
    file_put_contents("/mpd/playlists/songs.m3u", "");
    foreach($this->getSongs()["songs"] as $song){
      file_put_contents("/mpd/playlists/songs.m3u", $song["file"]."\n", FILE_APPEND);
    }
  }


  // function to get search results from youtube
  function search($user, $keyword, $duration){
    
    $results = youtubeSearch($keyword, $duration);

    // clear old searches
    $stmt = $this->db->prepare("DELETE FROM search WHERE user = :user");
    $stmt->execute(array(":user"=>$user));

    // insert new results
    $stmt = $this->db->prepare("INSERT INTO search (user, title, url, videoid, duration) VALUES (:user, :title, :url, :videoid, :duration)");
    foreach($results as $result){
      $stmt->execute(array(":user"=>$user, ":title"=>$result["title"], ":url"=>"https://www.youtube.com/watch?v=".$result["videoid"], ":videoid"=>$result["videoid"], ":duration"=>$result["duration"]));
    }

  }

  // function to get search results from specific user
  function getSearch($user){
    $stmt = $this->db->prepare("SELECT * FROM search USE INDEX(find) WHERE user = :user");
    if($stmt->execute(array(":user"=>$user))){
      $results = array();
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        array_push($results, array("id"=>$row["id"], "title"=>$row["title"], "url"=>$row["url"], "duration"=>$row["duration"]));
      }

      return array("success"=>true, "msg"=>null, "results"=>$results);

    }else{
      return array("success"=>false, "msg"=>"DB Error");
    }
  }


  function delSearch($id, $user){
    $stmt = $this->db->prepare("DELETE FROM search WHERE id = :id AND user = :user");
    if($stmt->execute(array(":id" => $id, ":user" => $user))){
      return array("success" => true, "msg" => null);
    }else{
      return array("success" => false, "msg" => "DB Error");
    }
  }







  // load / clear playlist
  function loadPlaylist($playlist = ""){
    if(!empty($playlist)){
      $this->clear();
      $this->updateDB();
      shell_exec("mpc load ".$playlist);
    }
  }

  // jump to position only if its not a livestream
  function seek($seek){
    if(explode("/", $this->time())[1] != "0:00"){
      if(is_numeric($seek) and $seek <= 100 and $seek >= 0){
        shell_exec("mpc seek ".$seek."%");
      }
    }
  }

  // return if player is currently playing
  function playing(){
    if(count($this->status) == 3){
      
      // get address of current playing
      $states = array();
      $current = explode(" ", $this->status[1]);
      foreach($current as $curr){
        if(!empty($curr)){
          array_push($states, $curr);
        }
      }

      // this is a temporary playlist to get the stream URL because mpc always writes the radios description (Kronehit bla bla) into the playlist file.
      // mpc current also gives back the channels description
      unlink("/mpd/playlists/temp.m3u");
      $this->save("temp");
      $playlist = file_get_contents("/mpd/playlists/temp.m3u");
      $current = $current_raw = getLine($playlist, (int) str_replace("#" , "", explode("/", $states[1])[0]));

      $thumbnail = "";
      // if is local than get title from DB
      if(endswith($current, ".mp3")){
        $stmt = $this->db->prepare("SELECT title, thumbnail FROM songs USE INDEX(file) WHERE file = :file");
        if($stmt->execute(array(":file"=>$current))){
          while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            $current = $row["title"];
            $thumbnail = $row["thumbnail"];
          }
        }
      }

      return array("playing" => startsWith($this->status[1], "[playing]"), "current" => $current, "current_raw"=>$current_raw, "thumbnail"=>$thumbnail, "x"=>$states[1]);
    }else{
      return false;
    }
  }

  // get time 0:00/0:00
  function time(){
    if(count($this->status) == 3){
      $time = explode(" ", $this->status[1]);
      $time = array_filter($time);

      $times = array();
      foreach($time as $t){
        if(!empty($t)){
          array_push($times, $t);
        }
      }

      $this->time = $times[2];
      return $this->time;
    }
  }

  // function to set crossfade time in seconds
  function crossfade($cf = ""){
    if(!empty($cf)){
      if(is_numeric($cf) and $cf <= 15 and $cf >= 0){
        shell_exec("mpc crossfade ".$cf);
      }
    }else{
      return trim(str_replace("crossfade:", "", shell_exec("mpc crossfade")));
    }
  }

  // function to update mpc's internal DB
  function updateDB(){
    shell_exec("mpc update");
  }

  // add to current playlist
  function add($song){
    shell_exec("mpc add ".$song);
  }

  // save playlist
  function save($file){
    shell_exec("mpc save ".$file);
  }

  // list playlist
  function playlist(){
    return trim(shell_exec("mpc playlist"));
  }

  // clear playlist
  function clear(){
    shell_exec("mpc clear");
  }

  // show currently playing
  function current(){
    return trim(shell_exec("mpc current"));
  }

  // stop playing
  function stop(){
    shell_exec("mpc stop");
  }

  // start playing
  function play(){
    shell_exec("mpc play");
  }

  // play if paused / pause if playing
  function toggle(){
    shell_exec("mpc toggle");
  }

  // play next
  function next(){
    shell_exec("mpc next");
  }

  // play prev
  function prev(){
    shell_exec("mpc prev");
  }

  // set repeat
  function repeat(){
    shell_exec("mpc repeat");
  }

  // set single
  function single(){
    shell_exec("mpc single");
  }

  // set random
  function random(){
    shell_exec("mpc random");
  }

  // set consume
  function consume(){
    shell_exec("mpc consume");
  }

  // change audio output
  function output($output){
    if($output == 1 or $output == 2){
      shell_exec("sudo amixer cset numid=3 ".$output);
    }
  }

  // set volume but check if given volume is valid (0-100)
  // if volume is not given return the current volume
  function volume($volume = null){
    if($volume == null){
      $vol = trim(str_replace("volume:", "", trim(shell_exec("mpc volume"))));
      $vol = str_replace("%", "", $vol);
      $this->volume = (int) $vol;
      return $this->volume;
    }else{
      $this->volume = $volume;
      if((is_numeric($this->volume) and $this->volume <= 100 and $this->volume >= 0) or ($this->volume == "+" or $this->volume == "-")){
        shell_exec("mpc volume ".$this->volume);
      }
    }
  }

}