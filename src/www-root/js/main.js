window.p = {
  api: "/api/index.php",
  update: true,
  user: {
    "name": "",
    "admin": false
  }
}

$(document).ready(function(){

  $.ajax({
    url: p.api,
    type: "GET",
    data: { "action": "user", "type": "check" },
    success: function(r){
      if(r.success){
        p.user.name = r.user.name;
        p.user.admin = r.user.admin;
      }

      $("body").append(`
        <div id="body">
          <div id="sidebar">
            <div class="sb-top">Webroida 2<i class="fa fa-music" aria-hidden="true"></i></div>
            <div class="sb-item"><a class="text-light" href="/">Webradio</a></div>
            <div class="sb-item"><a class="text-light" href="/yt">YouTube</a></div>
            <div class="sb-item"><a class="text-light" href="/sc">Shortcuts</a></div>
            `+(p.user.admin ? `
            <div class="sb-item"><a class="text-light" href="/users">Userverwaltung</a></div>
            <div class="sb-item"><a class="text-light" href="/system">System</a></div>
            `: "")+`
            <div id="cpu"><div id="cpu-prog" style="display: inline-block;"></div></div>
          </div>
          <div id="content"></div>
          <div id="player">
            <div id="player-thumb"></div>
            <div id="player-title"></div>
            <span id="volume"><span id="volume-show"></span><input type="range" step="5" min="0" max="100" value="0" class="slider" id="volume"/></span>
            <div id="player-controls">
              <span class="player-control" id="prev" title="Play previous"><i class="fa fa-step-backward" aria-hidden="true"></i></span>
              <span class="player-control" id="toggle" title="Pause / Play"><i class="fa fa-pause" aria-hidden="true"></i></span>
              <span class="player-control" id="stop" title="Stop playing"><i class="fa fa-stop" aria-hidden="true"></i></span>
              <span class="player-control" id="next" title="Play next"><i class="fa fa-step-forward" aria-hidden="true"></i></span>
              <span id="time"></span>
            </div>
            <div id="player-progress">
              <div id="player-progress-bar">
                <div id="player-progress-played"></div>
              </div>
            </div>
            <div id="player-settings">
              <span style="display: flex; position: relative;"><input type="range" id="player-crossfade" min="0" max="15" value="0"/><span id="crossfade-info">crossfade: .s</span></span>
              <span class="player-setting" id="player-single"><button class="btn btn-secondary mr-1"><i class="fa fa-repeat" aria-hidden="true"></i><span>1</span></button></span>
              <span class="player-setting" id="player-repeat"><button class="btn btn-secondary mr-1"><i class="fa fa-repeat" aria-hidden="true"></i><span>A</span></button></span>
              <span class="player-setting" id="player-random"><button class="btn btn-secondary mr-1"><i class="fa fa-random" aria-hidden="true"></i></button></span>
            </div>
          </div>
        </div>
      `);

      // keyboard SHORTCUTS \(o.o)/
      $(document).keypress(function(e){
        e.key.toLowerCase();
        if(!$("input,textarea").is(":focus")){
          if(e.key == " "){
            $("span.player-control#toggle").click();
          }else if(e.key == "a"){
            $("span.player-control#prev").click();
          }else if(e.key == "d"){
            $("span.player-control#next").click();
          }else if(e.key == "s"){
            $("span.player-control#stop").click();
          }else if(e.key == "+"){
            var vol = parseInt($("input#volume").val());
            vol += 5;
            volume(vol);
          }else if(e.key == "-"){
            var vol = parseInt($("input#volume").val());
            vol -= 5;
            volume(vol);
          }else if(e.key == "w"){
            $("a[href='/']").click();
          }else if(e.key == "y"){
            $("a[href='/yt']").click();
          }
          return false;
        }
      })

      // set crossfade
      // set volume
      $("input#player-crossfade").on("input", function(){
        var crossfade = $(this).val();
        $("span#crossfade-info").html("crossfade: " + crossfade + "s");
      });

      $("input#player-crossfade").on("mousedown", function(){
        $("span#crossfade-info").fadeIn(200);
      });

      $("input#player-crossfade").on("mouseup", function(){
        $("span#crossfade-info").fadeOut(200);
        var crossfade = $(this).val();
        $.ajax({
          url: p.api,
          type: "POST",
          data: { "action": "player", "type": "setcrossfade", "crossfade": crossfade }
        })
      });

      // set volume with scroll
      $("input#volume").bind("mousewheel wheel", function(e, delta){
        var vol = parseInt($(this).val());
        if(e.originalEvent.deltaY < 0){
          if(vol + 5 <= 100){
            volume(vol + 5);
          }
        }else{
          if(vol - 5 >= 0){
            volume(vol - 5);
          }
        }
        return false;
      });

      // settings
      $("span.player-setting").on("click", function(){
        var mode = $(this).attr("id").replace("player-", "");
        $.ajax({
          url: p.api,
          type: "POST",
          data: { "action": "player", "type": mode }
        });

        // toggle classes
        $(this).find("button").toggleClass("btn-primary");
        $(this).find("button").toggleClass("btn-secondary");

      });

      // seek
      $("div#player-progress-bar").on("click", function(e){

        var offsetX = e.pageX - $(this).offset().left;
        var percent = Math.round(offsetX / $(this).width() * 100);
        $.ajax({
          url: p.api,
          type: "POST",
          data: { "action": "player", "type": "seek", "seek": percent }
        });

      });

      // set volume
      $("input#volume").on("input", function(){
        var vol = $(this).val();
        volume(vol);
      });

      function volume(vol){
        $("span#volume-show").html(vol + "%");
        $("input#volume").val(vol);
        $.ajax({
          url: p.api,
          type: "POST",
          data: { "action": "player", "type": "volume", "volume": vol }
        });
      }

      // stop / pause / next / prev
      $("span.player-control").on("click", function(){
        console.log($(this).attr("id"));
        var type = $(this).attr("id");
        $.ajax({
          url: p.api,
          type: "POST",
          data: { "action": "player", "type": type },
          success: function(r){
            update(0);
          }
        });
      });

      fetch(window.location.pathname);
      update(1);

    }
  });
});

// update
function update(all = 0){
  if(p.update){
    $.ajax({
      url: p.api,
      type: "GET",
      data: { "action": "player", "type": "update", "all": all },
      success: function(r){

        //r.stats.current = escapeHtml(r.stats.current);
        if(all != 2){
          r.stats.playing.current = (typeof r.stats.playing.current !== "undefined" ? escapeHtml(r.stats.playing.current) : "");
        }

        if(window.location.pathname == "/yt"){
          // console.log(all);
          if(all == 1){
            updateSongs(r);
            updatePlayer(r);

            r.stats.cpu = Math.round(r.stats.cpu / 4);
            if(r.stats.cpu > 100){
              r.stats.cpu = 100;
            }

            $("div#cpu-prog").html(r.stats.cpu + "%");

            $("div#cpu-prog").css("background-color", "transparent");
            $("div#cpu-prog").css("min-width", r.stats.cpu + "%");
            $("div#cpu").css("background-image", "none");

            if(r.stats.cpu >= 95){
              $("div#cpu").css("background-image", "url(https://upload.wikimedia.org/wikipedia/commons/2/22/Animated_fire_by_nevit.gif)");
            }else{
              $("div#cpu-prog").css("background-color", getColor(r.stats.cpu / 100));
              $("div#cpu-prog").css("min-width", r.stats.cpu + "%");
            }
            

            $("input#player-crossfade").val(r.stats.crossfade);
            $("div#progress").html("");
            for(var i = 0; i < r.queue.length; i++){
              appendQueue(r.queue[i]);
            }
          }else if(all == 2){
            updateSongs(r);
          }else if(all == 0){
            updatePlayer(r);
          }
        }else{
          if(all == 1){
            $("input#player-crossfade").val(r.stats.crossfade);
          }
          updatePlayer(r);
        }

  
        
  
      }
    });
  }
  
}

function updateSongs(r){
  if($("input#search").val() == ""){
    $("div#songs").html("");
    for(var i = 0; i < r.songs.length; i++){
      appendSong(r.songs[i]);
    }
  }
  

  $("span#qcount").html(r.songs.length);
  sc = document.getElementById("songs");
  el = document.getElementById("songs");
  Sortable.create(el, {
    scroll: sc,
    animation: 150,
    
    onStart: function(e){
      p.update = false;
    },
    onEnd: function(e){
      var item = e.item;
      indexOld = (e.oldIndex + 1);
      indexNew = (e.newIndex + 1);
      $.ajax({
        url: p.api,
        type: "POST",
        data: { "action": "song", "type": "move", "from": indexOld, "to": indexNew }
      });
      p.update = true;
    }
  });
}


function updatePlayer(r){
  // update playing progress
  if(r.stats.time != "" && typeof r.stats.time != "undefined" && r.stats.time != null){
  
    $("span#time").html(r.stats.time);

    var time = r.stats.time.split("/");
    var width;
    if(time[1] == "0:00"){
      width = 100;
    }else{
      var duration = hmsToSecondsOnly(time[1]);
      var played = hmsToSecondsOnly(time[0]);
      width = played / duration * 100;
    }
  }

  $("div#player-progress-played").css("width", width + "%");
  
  // update settings buttons
  {
    if(r.stats.repeat == "on"){
      $("span#player-repeat").find("button").attr("class", "btn btn-primary mr-1");
    }else{
      $("span#player-repeat").find("button").attr("class", "btn btn-secondary mr-1");
    }

    if(r.stats.single == "on"){
      $("span#player-single").find("button").attr("class", "btn btn-primary mr-1");
    }else{
      $("span#player-single").find("button").attr("class", "btn btn-secondary mr-1");
    }

    if(r.stats.random == "on"){
      $("span#player-random").find("button").attr("class", "btn btn-primary mr-1");
    }else{
      $("span#player-random").find("button").attr("class", "btn btn-secondary mr-1");
    }

    if(r.stats.consume == "on"){
      $("span#player-consume").find("button").attr("class", "btn btn-primary");
    }else{
      $("span#player-consume").find("button").attr("class", "btn btn-secondary");
    }
  }
  


  // show current playing song
  if(r.stats.current != ""){
    $("div#player-title").html(r.stats.current.endsWith(".mp3") ? r.stats.playing.current : r.stats.current);
    if(r.stats.current.endsWith(".mp3")){
      $("div#player-thumb").css("display", "flex");
      $("div#player-progress-bar").css("width", "calc(100% - 150px)");
      $("div#player-thumb").html("<img src='"+r.stats.playing.thumbnail+"'></img>");
    }else{
      $("div#player-thumb").css("display", "none");
      $("div#player-progress-bar").css("width", "100%");
      $("div#player-thumb").html("");
    }
  }else{
    $("div#player-title").html("<b>Spüd grod nix, oida.</b>");
    $("div#player-thumb").css("display", "none");
    $("div#player-progress-bar").css("width", "100%");
    $("div#player-thumb").html("");
  }
  

  // update volume on slider
  if(!$("div#player").find("span#volume:hover").length){
    $("input#volume").val(r.stats.volume);
    $("span#volume-show").html(r.stats.volume + "%");
  }
  

  // change play / pause button
  $("div.sender").css("border-left", "3px solid var(--primary)");
  $("div.sender").find("span.sender-controls").find("span.sender-play").html('<i class="fa fa-play" aria-hidden="true"></i>');
  $("div.sender[address='"+r.stats.playing.current+"']").attr("playing", "0");

  $("div.song").css("border-left", "3px solid var(--primary)");
  $("div.song").css("font-weight", "normal");
  $("div.song").attr("playing", "0");

  if(r.stats.playing.playing){
    $("span.player-control#toggle").html('<i class="fa fa-pause" aria-hidden="true"></i>');
    $("div.sender[address='"+r.stats.playing.current+"']").css("border-left", "3px solid var(--warning)");
    $("div.sender[address='"+r.stats.playing.current+"']").find("span.sender-controls").find("span.sender-play").html('<i class="fa fa-pause" aria-hidden="true"></i>');
    $("div.sender[address='"+r.stats.playing.current+"']").attr("playing", "1");

    if(r.stats.current.endsWith(".mp3")){
      $("div.song[file='"+r.stats.current+"']").css("border-left", "3px solid var(--warning)");
      $("div.song[file='"+r.stats.current+"']").css("font-weight", "bold");
      $("div.song[file='"+r.stats.current+"']").attr("playing", "1");
    }
    

  }else{
    $("span.player-control#toggle").html('<i class="fa fa-play" aria-hidden="true"></i>');
  }
}



setInterval(function(){
  update(1);
}, 7000);

setInterval(function(){
  update(0);
}, 1500);

function fetch(url){

  $("div#content").css("display", "flex");
  if(url == "" || url == "/"){
    $("div#content").css("padding-right", "");
  }else if(url == "/yt"){
    $("div#content").css("padding-right", "0");
  }else if(url == "/sc"){
    $("div#content").css("display", "block");
  }

  $("div.sb-item").css("border-left", "5px solid transparent");
  $("a[href='"+url+"']").parent().css("border-left", "5px solid var(--light)");

  if(url == "" || url == "/"){
    $("div#content").html(`
      <div id="list-sender" class="w-49" style="margin-bottom: 100px;">
        <div class="list-top"><h2>Sender <span id="sendercount"></span><input type="text" class="form-control" id="search" placeholder="Suchen"/></h2></div>
        <div id="senderlist"></div>
      </div>
      <div id="list-add" class="w-49">
        <div class="list-top"><h2>Hinzufügen<a target="_blank" style="float: right;" href="https://www.webroida.com/list.txt"><button style="text-align: center; display: flex; height: 32px; align-items: center;" class="btn btn-sm btn-dark"><i class="fa fa-external-link" aria-hidden="true"></i></button></a></h2></div>
        <form id="sender-add">
          <div>
            <input type="text" class="form-control" id="sender-name" placeholder="Name"/>
            <input type="text" class="form-control" id="sender-address" placeholder="Adresse"/>
          </div>
          <div><button type="submit" class="btn btn-dark">speichern</button></div>
        </form>
      </div>
    `);

    // show sender
    $.ajax({
      url: p.api,
      type: "GET",
      data: { "action": "sender", "type": "get" },
      success: function(r){

        $("span#sendercount").html("("+r.senders.length+")");
        for(var i = 0; i < r.senders.length; i++){
          appendSender(r.senders[i].name, r.senders[i].address);
        }

        el = document.getElementById("senderlist");
        sc = document.documentElement;
        Sortable.create(el, {
          scroll: sc,
          scrollSensitivity: 60,
          animation: 150,
          onStart: function(e){
            p.update = false;
          },
          onEnd: function(e){
            var item = e.item;
            indexOld = (e.oldIndex + 1);
            indexNew = (e.newIndex + 1);

            $.ajax({
              url: p.api,
              type: "POST",
              data: { "action": "sender", "type": "move", "from": indexOld, "to": indexNew }
            });

            p.update = true;
          }
        });
      }
    });

    // add sender
    $("form#sender-add").on("submit", function(){

      var name = $("input#sender-name").val();
      var address = $("input#sender-address").val();

      if(name != "" && address != ""){
        $.ajax({
          url: p.api,
          type: "POST",
          data: { "action": "sender", "type": "add", "name": name, "address": address },
          success: function(r){
            if(r.success){
              $("input#sender-name").val("");
              $("input#sender-address").val("");
              appendSender(name, address);
            }else{
              console.log(r.msg);
            }
          }
        });
      }

      return false;
    });
  }else if(url == "/yt"){

    $("div#content").html(`
      <div id="list-queue" class="w-50">
        <div class="list-top">
          <h2>Q (<span id='qcount'>0</span>)
            <button class="btn btn-sm btn-danger ml-2 q-btn" id="clearq">Clear</button>
            <button class="btn btn-sm btn-success ml-2 q-btn" id="shuffleq">Shuffle</button>
            <input type="text" class="form-control" id="search" placeholder="Suchen"/>
          </h2>
        </div>
        <div id="songs"></div>
      </div>

      <div id="list-queue-add" class="w-50">
        <div class="list-top"><h2>Hinzufügen</h2></div>
        <div id="songs-add">
          <form id="queue-add">
            <div><textarea class="form-control" id="url" placeholder="Suchanfrage oder URLs (ane pro Zöün)"></textarea></div>
            <div>
              <button type="submit" class="btn btn-dark mt-1">Ind Q damit</button>
              <span id="search-settings" class="mt-1 d-inline-block rounded bg-secondary pointer text-light float-right">
                <span class="search-setting d-inline-block p-1" id="any">Olle</span>
                <span class="search-setting d-inline-block p-1" id="short" title="Lieder bis 4 Minuten Länge">Kurz</span>
                <span class="search-setting d-inline-block p-1" id="medium" title="Lieder von 4 bis 10 Minuten Länge">Mittl</span>
                <span class="search-setting d-inline-block p-1" id="long" title="Lieder ab 20 Minuten Länge">Laung</span>
              </span>
            </div>
          </form>
          <div id="search-toggle"><a href="" class="text-dark">Suchergebnisse:<span id="search-dropdown"><i class="fa fa-chevron-circle-down" aria-hidden="true"></i></span></a></div>
          <div id="search"></div>
          <div id="progress"></div>
        </div>
      </div>
    `);

    // search songs
    $("input#search").on("keyup", function(){
      searchSong($(this).val());
    });

    function searchSong(str = ""){
      if(str != ""){
        $.ajax({
          url: p.api,
          type: "GET",
          data: { "action": "song", "type": "search", "keyword": str },
          success: function(r){
            if(r.success){
              $("div#songs").html("");
              for(var i = 0; i < r.songs.length; i++){
                appendSong(r.songs[i]);
              }
            }
          }
        });
      }else{
        update(2);
      }
    }

    // clear and shuffle q
    $("button.q-btn").on("click", function(){
      var type = $(this).attr("id").replace("q", "");
      $.ajax({
        url: p.api,
        type: "POST",
        data: { "action": "queue", "type": type },
        success: function(r){
          //console.log(r);
          update(2);
        }
      });
    });
    

    // on enter when text is not http...
    $("textarea#url").keypress(function (e) {
      var code = (e.keyCode ? e.keyCode : e.which);
      if(code == 13){
        var text = $("textarea#url").val();
        if(!text.startsWith("http") && text != ""){
          $("form#queue-add").submit();
        }
      }
    });

    if(Cookies.get("setting-search-toggle") == "up"){
      $("div#search").slideUp();
      //$("span#search-dropdown").find("i").css("transform", "rotate(180deg)");
    }

    // toggle search
    $("div#search-toggle").on("click",function(){
      if(Cookies.get("setting-search-toggle") == "down"){
        Cookies.set("setting-search-toggle", "up", { expires: 365, path: "/" });
        $("div#search").slideUp();
        $("span#search-dropdown").find("i").css("transform", "rotate(0deg)");
      }else{
        Cookies.set("setting-search-toggle", "down", { expires: 365, path: "/" });
        $("div#search").slideDown();
        $("span#search-dropdown").find("i").css("transform", "rotate(180deg)");
      }
    })

    // set active setting
    if(Cookies.get("setting-search")){
      $("span.search-setting#"+Cookies.get("setting-search")).addClass("search-setting-active");
    }else{
      $("span.search-setting#any").addClass("search-setting-active");
      Cookies.set("setting-search", "any", { expires: 365, path: "/" });
    }

    $("span.search-setting").on("click", function(){
      $("span.search-setting").removeClass("search-setting-active");
      var type = $(this).attr("id");
      Cookies.set("setting-search", type, { expires: 365, path: "/" });
      $("span.search-setting#"+Cookies.get("setting-search")).addClass("search-setting-active");
    });

    // insert into queue
    $("form#queue-add").on("submit", function(){
      var url = $("textarea#url").val();
      addQueue(url);
      return false;
    });

    updateSearch();
    update(1);

  }else if(url == "/users"){

    $("div#content").html(`
      <div id="list-queue" class="w-49">
        <div class="list-top"><h2>User</h2></div>
        <div id="users"></div>
      </div>

      <div id="list-queue-add" class="w-49">
        <div class="list-top"><h2>User hinzufügen</h2></div>
        <form id="user-add">
          <div><input type="text" id="name" placeholder="Name" class="form-control w-100"/></div>
          <div class="mt-1"><input type="text" id="pass" placeholder="Passwort" class="form-control w-100"/></div>
          <div class="mt-1"><button type="submit" class="btn btn-primary mt-1">Erstellen</button></div>
        </form>
      </div>
    `);

    // get all users
    function getUsers(){
      $.ajax({
        url: p.api,
        type: "GET",
        data: { "action": "user", "type": "get" },
        success: function(r){
          if(r.success){
            $("div#users").html("");
            for(var i = 0; i < r.users.length; i++){
              appendUser(r.users[i]);
            }
          }
        }
      });
    }
    getUsers()
    

    // create new user
    $("form#user-add").on("submit", function(){

      var name = $("input#name").val();
      var pass = $("input#pass").val();
      
      $.ajax({
        url: p.api,
        type: "POST",
        data: { "action": "user", "type": "add", "name": name, "pass": pass },
        success: function(r){
          $("input#name").val("");
          $("input#pass").val("");
          getUsers();
        }
      });

      return false;
    });
    

  }else if(url == "/system"){

    $("div#content").html(`
      <div id="list-queue" class="w-49">
        <div class="list-top"><h2>System</h2></div>
        <div id="screens"></div>
        <div class="mt-1"><button id="screen-restart" class="btn btn-primary btn-sm">Restart workers</button></div>
      </div>

      <div id="list-queue-add" class="w-49">
        <div class="list-top"><h2>Einstellungen</h2><h3 class="mt-4">Proxy</h3></div>
        <form id="proxy">
          <div><input type="text" id="host" placeholder="Host" class="form-control w-100"/></div>
          <div class="mt-1"><input type="number" id="port" placeholder="Port" class="form-control w-100"/></div>
          <div class="mt-1"><button type="submit" class="btn btn-dark">Speichern</button></div>
        </form>
        <h3 class="mt-4">Audio output</h3>
        <form id="audio">
          <div>
            <select id="output" class="form-control">
              <option class="output-val" value="1">AUX</option>
              <option class="output-val" value="2">HDMI</option>
            </select>
            <div class="mt-1"><button type="submit" class="btn btn-dark">Speichern</button></div>
          </div>
        </form>
      </div>
    `);

    $("form#proxy").on("submit", function(){

      var host = $("input#host").val();
      var port = $("input#port").val();

      $.ajax({
        url: p.api,
        type: "POST",
        data: { "action": "system", "type": "proxy", "host": host, "port": port },
        success: function(r){
          //console.log(r);
        }
      });

      return false;
    });

    $("form#audio").on("submit", function(){

      var output = $("select#output").val();

      $.ajax({
        url: p.api,
        type: "POST",
        data: { "action": "system", "type": "output", "output": output },
      });

      return false;
    });

    $("button#screen-restart").on("click", function(){
      var check = confirm("Bitte nur, wenn der grod ned konvertiert!");
      if(check){
        $.ajax({
          url: p.api,
          type: "POST",
          data: { "action": "system", "type": "restartscreens" },
          success: function(){
            fetch("/system");
          }
        })
      }
    });

    $.ajax({
      url: p.api,
      type: "GET",
      data: { "action": "system", "type": "getscreens" },
      success: function(r){
        if(r.success){
          for(var i = 0; i < r.screens.length; i++){
            appendScreen(r.screens[i]);
          }
        }
      }
    });

    $.ajax({
      url: p.api,
      type: "GET",
      data: { "action": "system", "type": "getconfig" },
      success: function(r){
        if(r.success){
          $("input#host").val(r.conf.proxy.host);
          $("input#port").val(r.conf.proxy.port);

          $("select#output").prop('selectedIndex', (parseInt(r.conf.audio.output) - 1));
        }
      }
    })

  }else if(url == "/sc"){
    $("div#content").html(`
      <h2>Keyboard Shortcuts</h2>
      <div class="key-container"><span class="key">A</span>prev</div>
      <div class="key-container"><span class="key">D</span>next</div>
      <div class="key-container"><span class="key">[ ]</span>pause</div>
      <div class="key-container"><span class="key">S</span>stop</div>
      <div class="key-container"><span class="key">+</span>lauter</div>
      <div class="key-container"><span class="key">-</span>leiser</div>
      <div class="key-container"><span class="key">Y</span>YouTube</div>
      <div class="key-container"><span class="key">W</span>Webradio</div>
    `);
  }else{
    $("div#content").html("<h1>404</h1>");
  }
}

function appendScreen(s){
  $("div#screens").append(`
    <div class="screen">
      <span class="screen-name">`+s.name+`</span>
      <span class="screen-status" style="background-color: `+(s.running ? "var(--success)" : "var(--danger)")+`;"></span>
    </div>
  `);
}


function appendUser(u){
  $("div#users").append(`
    <div class="user" id="user`+u.id+`" user="`+u.name+`">
      <span class="user-name">`+u.name+`</span>
      <span class="user-control">
        <span admin="`+u.admin+`" class="user-admin pointer" title="ADMIN" style="color: `+(u.admin == 1 ? "var(--primary)" : "var(--dark)")+`;"><i class="fa fa-user" aria-hidden="true"></i></span>
        <span class="user-del"><i class="fa fa-times" aria-hidden="true"></i></span>
      </span>
    </div>
  `);

  $("span.user-del").unbind();
  $("span.user-del").on("click", function(){
    var x = $(this).parent().parent();
    var name = $(x).attr("user");
    $.ajax({
      url: p.api,
      type: "POST",
      data: { "action": "user", "type": "del", "name": name },
      success: function(r){
        if(r.success){
          $(x).fadeOut(200, function(){ $(this).remove() });
        }
      }
    });
  });

  $("span.user-admin").unbind();
  $("span.user-admin").on("click", function(){
    var x = $(this);
    var name = $(x).parent().parent().attr("user");
    var admin = ($(x).attr("admin") == 1 ? 0 : 1);
    
    $.ajax({
      url: p.api,
      type: "POST",
      data: { "action": "user", "type": "edit", "name": name, "admin": admin },
      success: function(r){
        $(x).attr("admin", admin);
        $(x).css("color", (admin == 1 ? "var(--primary)" : "var(--dark)"));
      }
    })
  });

}


function updateSearch(){
  $.ajax({
    url: p.api,
    type: "GET",
    data: { "action": "queue", "type": "getsearch" },
    success: function(r){
      if(r.success){
        $("div#search").html("");
        for(var i = 0; i < r.results.length; i++){
          appendSearch(r.results[i]);
        }
      }
    }
  });
}

function appendSearch(s){
  $("div#search").append(`
    <div class="search" id="`+s.id+`" url="`+s.url+`">
      <span class="search-title"><a class="text-dark" target="_blank" href='`+s.url+`'>`+s.title+`</a></span>
      <span class="search-delete text-danger" title="weg"><i class="fa fa-times" aria-hidden="true"></i></span>
      <span class="search-add text-primary" title="In die Q damit"><i class="fa fa-plus" aria-hidden="true"></i></span>
      <span class="search-duration">`+s.duration+`</span>
    </div>
  `);

  $("span.search-add").unbind();
  $("span.search-add").click(function(){
    addQueue($(this).parent().attr("url"));
  });

  $("span.search-delete").unbind();
  $("span.search-delete").on("click", function(){
    var x = $(this).parent();
    var id = $(x).attr("id");
    $.ajax({
      url: p.api,
      type: "POST",
      data: { "action": "queue", "type": "delsearch", "id": id },
      success: function(r){
        //console.log(r);
        if(r.success){
          $(x).fadeOut(200, function(){ $(this).remove(); });
        }
      }
    });
  });

}

function addQueue(url){
  var type = Cookies.get("setting-search");
  $.ajax({
    url: p.api,
    type: "POST",
    data: { "action": "queue", "type": "add", "url": url, "duration": type },
    success: function(r){
      $("textarea#url").val("");
      $("div.search[url='"+url+"']").fadeOut(200, function(){ $(this).remove() });
      updateSearch();
    }
  });
}

function appendQueue(q){
  var style = "";
  var style_a = "";
  var disabled = "";
  if(q.progress == 3){
    style = "border-left: 3px solid var(--warning); color: #929ea9;";
    style_a = "color: #929ea9; text-decoration: none;";
    disabled = "disabled='disabled'";
  }else if(q.progress == 2){
    style = "border-left: 3px solid var(--warning);";
    style_a = "color: var(--text-dark); text-decoration: none;";
  }else if(q.progress == 0 || q.progress == 1){
    style_a = "color: var(--text-dark); text-decoration: none;";
  }

  $("div#progress").append(`
    <div class="queue" id="`+q.id+`" progress="`+q.progress+`" style="`+style+`" title="`+(q.title != "" ? escapeHtml(q.title) : q.url)+`">
      <span class="queue-prio"><input `+disabled+` type="number" class="prio" value="`+q.prio+`"/></span>
      <span class="queue-title"><a target="_blank" style="`+style_a+`" href='`+q.url+`'>`+(q.title != "" ? q.title : q.url)+`</a></span>
      `+(q.progress != 3 ? '<span class="queue-delete text-danger" title="Song aus Q entfernen"><i class="fa fa-times" aria-hidden="true"></i></span>':"")+`
      <span class="queue-duration">`+q.duration+`</span>
    </div>
  `);


  // change prio
  $("input.prio").unbind();
  $("input.prio").bind("keyup mouseup", function () {
    
    var id = $(this).parent().parent().attr("id");
    var prio = $(this).val();
    //console.log("prio changed: " + prio);

    $.ajax({
      url: p.api,
      type: "POST",
      data: { "action": "queue", "type": "setprio", "id": id, "prio": prio },
      success: function(r){
        //console.log(r);
      }
    });
  });

  // on delete
  $("span.queue-delete").unbind();
  $("span.queue-delete").on("click", function(){
    var id = $(this).parent().attr("id");
    $.ajax({
      url: p.api,
      type: "POST",
      data: { "action": "queue", "type": "delete", "id": id },
      success: function(r){
        if(r.success){
          $("div.queue#"+id).fadeOut(200, function(){ $(this).remove(); });
        }
      }
    });
  });
}

function appendSong(s){
  $("div#songs").append(`
    <div class="song" id="`+s.id+`" file="`+s.file+`">
      <!--<span class="song-title"><a class="text-dark" target="_blank" href='`+s.url+`'>`+s.title+`</a></span>-->
      <span class="song-title">`+s.title+`</span>
      <span style="float: right">
        <span class="song-duration">`+s.duration+`</span>      
        <span action="play" class="song-control text-dark mr-1 ml-1 pointer" title="Abspielen / Pausieren"><i class="fa fa-play" aria-hidden="true"></i></span>
        <span action="del" class="song-control text-danger pointer" title="Abspielen / Pausieren"><i class="fa fa-times" aria-hidden="true"></i></span>
      </span>
    </div>
  `);



  $("span.song-control").unbind();
  $("span.song-control").on("click", function(){
    
    var action = $(this).attr("action");
    var x = $(this).parent().parent();
    var file = $(x).attr("file");
    $.ajax({
      url: p.api,
      type: "POST",
      data: { "action": "song", "type": action, "file": file },
      success: function(){
        if(action == "del"){
          $(x).fadeOut(200, function(){ $(this).remove(); });
        }
        update(2);
      }
    });
  });

}

function appendSender(name, address){
  $("div#senderlist").append(`
    <div class="sender" address="`+address+`" playing="0">
      <span>`+name+`</span>
      <span class="sender-controls">
        <span class="sender-play" title="Abspielen / Pausieren"><i class="fa fa-play" aria-hidden="true"></i></span>
        <span class="sender-delete text-danger" title="Sender löschen"><i class="fa fa-times" aria-hidden="true"></i></span>
      </span>
    </div>
  `);

  $("span.sender-play").unbind();
  $("span.sender-delete").unbind();

  $("span.sender-delete").on("click", function(){
    
    var sender = $(this).parent().parent();
    var address = $(sender).attr("address");
    $.ajax({
      url: p.api,
      type: "POST",
      data: { "action": "sender", "type": "delete", "address": address },
      success: function(){
        $(sender).fadeOut(200, function(){ $(this).remove() });
      }
    });

  });

  $("span.sender-play").on("click", function(){
    
    var sender = $(this).parent().parent();
    //console.log(sender.attr("address"));
    $("div.sender").attr("playing", "0");
    if(sender.attr("playing") == "0"){
      $.ajax({
        url: p.api,
        type: "POST",
        data: { "action": "sender", "type": "play", "address": sender.attr("address") },
        success: function(){
          sender.attr("playing", "1");
          update(1);
        }
      });
    }else{
      $.ajax({
        url: p.api,
        type: "POST",
        data: { "action": "player", "type": "toggle" },
        success: function(){
          sender.attr("playing", "0");
          update(1);
        }
      });
    }
  });

}

//bind popstate
$(window).bind("popstate", function(){ fetch(window.location.pathname) });

//Check where user clicks on
$(document).on("click", "a", function(e){
  e.preventDefault();
  if($(this).attr("href")){
    var href = $(this).attr("href");
    if($(this).attr("ignore") != "ignore" && href != "#"){
      if($(this).attr("ignore-fetch") == "true" || $(this).attr("target") == "_blank"){
        var tab_href = window.open(href, "_blank");
        if(tab_href){
          tab_href.focus();
        }else{
          alert("Error while opening a new tab");
        }
      }else if(href.startsWith("mailto:")){
        location.href = href;
      }else{
        if(href.indexOf("https://") != -1 || href.indexOf("http://") != -1 ){
          window.location.href = href;
        }else{
          if(href !== window.location.pathname){
            history.pushState('', window.location.host, href);
            var url_new = window.location.pathname;
            if(!href.startsWith("/mail/")){
              fetch(url_new);
            }
          }
        }
      }
    }
  }
});