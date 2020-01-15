<?php

$db_host = "localhost";
$db_user = trim(file_get_contents("/etc/webroida/db.name"));
$db_name = trim(file_get_contents("/etc/webroida/db.name"));
$db_pass = trim(file_get_contents("/etc/webroida/db.pass"));

try{
  $db = new PDO("mysql:host=".$db_host.";dbname=".$db_name, $db_user, $db_pass);
}catch(PDOException $e){
  header("Content-Type: application/json");
  echo '{"success":false,"msg":"DB Error"}';
  die();
}
