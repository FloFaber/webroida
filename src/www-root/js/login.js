$(document).ready(function(){
  $("body").html(`
  
    <div id="login" class="shadow">
      <!--<h2>Webroida 2</h2>-->
      <form id="login">
        <div><input type="text" id="name" class="form-control" placeholder="Name"/></div>
        <div><input type="password" id="pass" class="form-control" placeholder="Passwort"/></div>
        <div id="status"></div>
        <div><button type="submit" class="btn btn-primary">Login</button></div>
      </form>
    </div>
  
  `);

  $("form#login").on("submit", function(){

    var name = $("input#name").val();
    var pass = $("input#pass").val();

    $.ajax({
      url: "/api/",
      type: "POST",
      data: { "action": "user", "type": "login", "name": name, "pass": pass },
      success: function(r){
        if(r.success){
          $("div#status").html("Redirecting...");
          window.location.reload();
        }else{
          $("div#status").html(r.msg);
        }
      },
      error: function(xhr, r){
        $("div#status").html(xhr.status);
      }
    });

    return false;
  });

})