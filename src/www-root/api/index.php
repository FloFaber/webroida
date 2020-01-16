<?php

// bind in webroida class
require_once("webroida.class.php");
require_once("user.class.php");

// set response array
$r = array();

// set json header
header("Content-type: application/json");

// init webroida
$wr = new Webroida();

// init user
$user = new User;

// check if the neccessary paramters were set
if(isset($_REQUEST["action"]) and isset($_REQUEST["type"])){
  $action = $_REQUEST["action"];
  $type = $_REQUEST["type"];

  $r["action"] = $action;
  $r["type"] = $type;


  if(isset($_REQUEST["cookie"]) and $action == "login" and $type == "check"){
    $cookie = $_REQUEST["cookie"];
    echo json_encode(array("success" => $user->check($cookie), "msg" => "dont know"));
    die();
  }

  if($user->check()){
    if($action == "player"){

      /* PLAYER */
  
      if($type == "play"){
        $wr->play();
      }elseif($type == "stop"){
        $wr->stop();
      }elseif($type == "prev"){
        $wr->prev();
      }elseif($type == "next"){
        $wr->next();
      }elseif($type == "toggle"){
        $wr->toggle();
      }elseif($type == "time"){
        $r = $wr->time();
      }elseif($type == "update"){
        $r = $wr->update();
        $r["user"] = array(
          "name" => $user->name,
          "admin" => $user->admin
        );
      }elseif($type == "volume"){
        if(isset($_REQUEST["volume"])){
          $wr->volume($_REQUEST["volume"]);
        }
      }elseif($type == "seek"){
        if(isset($_REQUEST["seek"])){
          $wr->seek($_REQUEST["seek"]);
        }
      }elseif($type == "single"){
        $wr->single();
      }elseif($type == "repeat"){
        $wr->repeat();
      }elseif($type == "random"){
        $wr->random();
      }elseif($type == "consume"){
        $wr->consume();
      }elseif($type == "setcrossfade"){
        $wr->crossfade($_REQUEST["crossfade"]);
      }
  
  
  
    }elseif($action == "sender"){
  
      /* SENDER */
  
      if($type == "add"){
        if(isset($_REQUEST["name"]) and isset($_REQUEST["address"])){
          $r = $wr->addSender($_REQUEST["name"], $_REQUEST["address"]);
        }
      }elseif($type == "get"){
        $r = $wr->getSender();
      }elseif($type == "play"){
        $address = $_REQUEST["address"];
        $r = $wr->playSender($address);
      }elseif($type == "delete"){
        $address = $_REQUEST["address"];
        $r = $wr->delSender($address);
      }
  
  
  
    }elseif($action == "queue"){
  
      /* QUEUE */
  
      if($type == "add"){
        // if string starts with https -> process as video urls
        if(startsWith($_REQUEST["url"], "https://")){
          if(isset($_REQUEST["url"])){
            $r = $wr->addQueue($user->name, $_REQUEST["url"]);
          }
        }else{
          $r = $wr->search($user->name, $_REQUEST["url"], $_REQUEST["duration"]);
        }
        
      }elseif($type == "get"){
        $r = $wr->getQueue();
      }elseif($type == "delete"){
        $r = $wr->delQueue($_REQUEST["id"]);
      }elseif($type == "getsearch"){
        $r = $wr->getSearch($user->name);
      }elseif($type == "delsearch"){
        $r = $wr->delSearch($_REQUEST["id"], $user->name);
      }elseif($type == "setprio"){
        $r = $wr->setPrio($_REQUEST["id"], $_REQUEST["prio"]);
      }elseif($type == "clear"){
        $r = $wr->clearQueue();
      }elseif($type == "shuffle"){
        $r = $wr->shuffleQueue();
      }
  
  
  
    }elseif($action == "song"){
      
      if($type == "play"){
        $wr->playSong($_REQUEST["file"]);
      }elseif($type == "del"){
        $wr->delSong($_REQUEST["file"]);
      }elseif($type == "move"){
        if(isset($_REQUEST["from"]) and isset($_REQUEST["to"])){
          $from = $_REQUEST["from"];
          $to = $_REQUEST["to"];

          $r = $wr->moveSong($from, $to);

        }
      }elseif($type == "search"){
        $r = $wr->searchSong($_REQUEST["keyword"]);
      }
  
    }elseif($action == "user"){

      if($type == "get"){
        if($user->admin){

          $r = $user->getUsers();
  
        }else{
          $r["success"] = false;
          $r["msg"] = "Forbidden";
        }
      }elseif($type == "check"){
        if(isset($_REQUEST["cookie"])){
          $r = array("success" => $user->check($_REQUEST["cookie"]));
        }
        $r = array(
          "success" => true,
          "msg" => null,
          "user" => array(
            "name" => $user->name,
            "admin" => $user->admin
          )
        );
      }elseif($type == "add"){
        if($user->admin){
          $r = $user->addUser($_REQUEST["name"], $_REQUEST["pass"]);
        }else{
          $r["success"] = false;
          $r["msg"] = "Forbidden";
        }
      }elseif($type == "edit"){
        if($user->admin){
          $r = $user->editUser($_REQUEST["name"], $_REQUEST["admin"]);
        }else{
          $r["success"] = false;
          $r["msg"] = "Forbidden";
        }
      }elseif($type == "del"){
        if($user->admin){
          $r = $user->delUser($_REQUEST["name"]);
        }else{
          $r["success"] = false;
          $r["msg"] = "Forbidden";
        }
      }
      

    }elseif($action == "system"){
      if($user->admin){
        if($type == "getscreens"){
        
          $screens = array();
          for($i=0; $i < 4; $i++){
            $running = !empty(trim(shell_exec("sudo screen -ls | grep worker".$i)));
            array_push($screens, array("name" => "worker".$i, "running" => $running));
          }

          for($i=0; $i < 4; $i++){
            $running = !empty(trim(shell_exec("sudo screen -ls | grep worker-getter-".$i)));
            array_push($screens, array("name" => "getter".$i, "running" => $running));
          }

  
          $r["success"] = true;
          $r["msg"] = null;
          $r["screens"] = $screens;
  
        }elseif($type == "restartscreens"){
          shell_exec("sudo /etc/init.d/webroida restart");
        }elseif($type == "proxy"){

          $host = $_REQUEST["host"];
          $port = $_REQUEST["port"];

          // read whole config
          $conf = json_decode(file_get_contents(__DIR__."/config.json"), true);

          if(!empty($host) and !empty($port) and is_numeric($port)){
            $conf["proxy"]["host"] = $host;
            $conf["proxy"]["port"] = (int) $port;
            if(file_put_contents(__DIR__."/config.json", json_encode($conf))){
              if(file_put_contents("/etc/mpd.proxy.conf", "input {\n") and
                file_put_contents("/etc/mpd.proxy.conf", "  plugin \"curl\"\n", FILE_APPEND) and
                file_put_contents("/etc/mpd.proxy.conf", "  proxy \"".$host.":".$port."\"\n", FILE_APPEND) and
                file_put_contents("/etc/mpd.proxy.conf", "}\n", FILE_APPEND)
              ){
                $r["success"] = true;
                $r["msg"] = null;
              }else{
                $r["success"] = false;
                $r["msg"] = "Error updating mpd.proxy.conf";
              }
            }else{
              $r["success"] = false;
              $r["msg"] = "Error updating config.json";
            }

            

          }else{
            $conf["proxy"]["host"] = "";
            $conf["proxy"]["port"] = "";
            if(file_put_contents(__DIR__."/config.json", json_encode($conf))){
              if(file_put_contents("/etc/mpd.proxy.conf", "")){
                $r["success"] = true;
                $r["msg"] = null;
              }else{
                $r["success"] = false;
                $r["msg"] = "Error updating mpd.proxy.conf";
              }
            }else{
              $r["success"] = false;
              $r["msg"] = "Error updating config.json";
            }
            
            
          }

          // i know, sudo... but how else should I restart the mpd service?
          shell_exec("sudo /etc/init.d/mpd restart");

        }elseif($type == "getconfig"){
          $conf = json_decode(file_get_contents(__DIR__."/config.json"), true);
          $r["success"] = true;
          $r["msg"] = null;
          $r["conf"] = $conf;
        }elseif($type == "output"){
          
          if(isset($_REQUEST["output"])){
            $output = $_REQUEST["output"];
            if($output == 1 or $output == 2){
              $conf = json_decode(file_get_contents(__DIR__."/config.json"), true);
              $conf["audio"] = array(
                "output" => (int) $output
              );
              $wr->output($output);
              file_put_contents(__DIR__."/config.json", json_encode($conf));
            }
          }
          
        }
      }
      
      
    }else{
      $r["success"] = false;
      $r["msg"] = "Invalid Request";
    }

  }else{
    if($action == "user"){
      if($type == "login"){
        $name = $_REQUEST["name"];
        $pass = $_REQUEST["pass"];
        $r = $user->login($name, $pass);
      }
    }else{
      $r["success"] = false;
      $r["msg"] = "Invalid Request";
    }
  }

}else{
  $r["success"] = false;
  $r["msg"] = "Invalid Request";
}

echo json_encode($r);