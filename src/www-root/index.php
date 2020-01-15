<?php
require_once(__DIR__."/api/user.class.php");
$user = new User;
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Webroida 2</title>
    <meta charset="utf-8">
    <link rel="icon" type="image/png" href="/icon.png"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
    <link rel="stylesheet" href="/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:400,700&display=swap"/>
    <script type="text/javascript" src="/js/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script type="text/javascript" src="/js/utils.js"></script>
    <?php
    if($user->check()){
    ?>
    <link rel="stylesheet" href="/css/main.css"/>
    <script src="https://cdn.jsdelivr.net/npm/js-cookie@beta/dist/js.cookie.min.js"></script>
    <script type="text/javascript" src="/js/main.js"></script>
    <?php
    }else{
    ?>
    <link rel="stylesheet" href="/css/login.css"/>
    <script type="text/javascript" src="/js/login.js"></script>
    <?php
    }
    ?>
    
  </head>
  <body></body>
</html>