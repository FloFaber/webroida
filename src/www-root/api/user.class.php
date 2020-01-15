<?php

require_once(__DIR__."/utils.class.php");

class User{
  private $db;
  private $db_host = "localhost";
  private $db_user;
  private $db_name;
  private $db_pass;
  
  public $name;
  public $admin;

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
  }


  function getUsers(){
    $users = array();
    $stmt = $this->db->prepare("SELECT id, name, admin FROM users");
    if($stmt->execute()){
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        array_push($users, array("id"=>(int) $row["id"], "name"=>$row["name"], "admin"=>(int)$row["admin"]));
      }
      return array("success" => true, "msg" => null, "users" => $users);
    }else{
      return array("success" => false, "msg" => "DB Error");
    }
  }


  function login($name, $pass){
    $stmt = $this->db->prepare("SELECT * FROM users WHERE name = :name AND pass = :pass");
    if($stmt->execute(array(
      ":name" => $name,
      ":pass" => hash("sha512", $pass)
    ))){
      if($stmt->rowCount() > 0){

        $random = random(36);

        // set cookie
        setcookie("login", $random, time() + 31536000, "/");

        while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
          $this->name = $row["name"];
          $this->admin = boolval($row["admin"]);
        }

        // insert into logins
        $stmt = $this->db->prepare("INSERT INTO logins (user, cookie) VALUES (:user, :cookie)");
        if($stmt->execute(array(
          ":user" => $this->name,
          ":cookie" => $random
        ))){

          return array("success"=>true, "msg"=>null);

        }else{
          return array("success"=>false, "msg"=>"DB Error...");
        }
      }else{
        return array("success"=>false, "msg"=>"Invalid credentials");
      }
    }else{
      return array("success"=>false, "msg"=>"DB Error..");
    }
  }


  // check if logged in
  function check($cookie = ""){
    if(isset($_COOKIE["login"]) or !empty($cookie)){

      if(!empty($cookie)){
        $cookie = $cookie;
      }else{
        $cookie = $_COOKIE["login"];
      }
      

      $stmt = $this->db->prepare("SELECT * FROM logins WHERE cookie = :cookie");
      if($stmt->execute(array(":cookie"=>$cookie))){
        if($stmt->rowCount() > 0){
          while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            $this->name = $row["user"];
          }

          $stmt = $this->db->prepare("SELECT * FROM users WHERE name = :name");
          if($stmt->execute(array(":name"=>$this->name))){
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
              $this->admin = boolval($row["admin"]);
            }
            return true;
          }else{
            return false;
          }
        }else{
          return false;
        }
      }else{
        return false;
      }
    }else{
      return false;
    }
  }


  // function to add a user
  function addUser($name, $pass){
    if(ctype_alnum($name) and strlen($name) >= 2){
      $pass = hash("sha512", $pass);
      $stmt = $this->db->prepare("INSERT INTO users (name, pass) values (:name, :pass)");
      if($stmt->execute(array(":name"=>$name, ":pass"=>$pass))){
        return array("success" => true, "msg" => null);
      }else{
        return array("success" => false, "msg" => "DB Error");
      }
    }else{
      return array("success" => false, "msg" => "Invalid username");
    }
  }

  // function to update a user
  function editUser($name, $admin){
    if(is_bool($admin) or ($admin == 0 or $admin == 1)){
      $admin = ($admin ? 1 : 0);
      $stmt = $this->db->prepare("UPDATE users SET admin = :admin WHERE name = :name");
      if($stmt->execute(array(":name" => $name, ":admin" => $admin))){
        return array("success" => true, "msg" => null);
      }else{
        return array("success" => false, "msg" => "DB Error");
      }
    }else{
      return array("success" => false, "msg" => "Invalid Admin Value");
    }
  }

  // function to add a user
  function delUser($name){
    $stmt = $this->db->prepare("DELETE FROM users WHERE name = :name");
    if($stmt->execute(array(":name"=>$name))){
      $stmt = $this->db->prepare("DELETE FROM logins WHERE user = :name");
      if($stmt->execute(array(":name"=>$name))){
        return array("success" => true, "msg" => null);
      }else{
        return array("success" => false, "msg" => "DB Error");
      }
    }else{
      return array("success" => false, "msg" => "DB Error");
    }
  }



}

?>