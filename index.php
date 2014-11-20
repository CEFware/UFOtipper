<?php
ob_start();
session_start();
include("const.php");
require("auth/twitteroauth.php");
require("jsonRPCClient.php");

ini_set("display_errors",1);
ini_set("display_startup_errors",1);
error_reporting(-1);

$rpc = new jsonRPCClient("http://".RPC_USER.":".RPC_PASS."@".RPC_ADDR.":".RPC_PORT."/");

function echobr($str) {
  echo($str);
  echo("<br>");
}

function tweetFind($conn, $RPC) {

//
// read last tweet
//
  $file = file_get_contents("last_tweet");
  $arr = json_decode($file);
  $next = $arr[0];
  $tid = $arr[1];

  if ($next > time()) return;

//
// get tweets
//
  $str = "search/tweets.json?q=" . TWEET_QUERY . "&since_id=" . $tid . "&count=100";
  $tweets = $conn->get($str);

//
// check for errors
//
  if (isset($tweets->errors)) {
    echobr("error: " . $tweets->errors[0]->message);
    exit();
  }

  $count = count($tweets->statuses);
  for ($i = 0; $i < $count; $i++) {
    $name = $tweets->statuses[$i]->user->screen_name;
    $tid  = $tweets->statuses[$i]->id;
    $users = $tweets->statuses[$i]->entities->user_mentions;
    $tags = $tweets->statuses[$i]->entities->hashtags;
    $text = $tweets->statuses[$i]->text;
    $text_clean = $text;

//
// remove hashtags & user mentions from the text
//

    for($j = 0, $c = count($tags); $j < $c; $j++) $text_clean = str_ireplace($tags[$j]->text,"",$text_clean);
    for($j = 0, $c = count($users); $j < $c; $j++) $text_clean = str_ireplace($users[$j]->screen_name,"",$text_clean);

//
// search for symbol & amount
//
    if (stristr($text_clean,"ufo") == false) continue;
    preg_match_all('!\d+!', $text_clean, $matches);
    if (isset($matches[0][0]) == false) continue;
    $amount = floatval($matches[0][0]);
    if ($amount <= 0) continue;

//
// get balance
//    
    $balance = $RPC->getbalance($name,MIN_CONF);
    if (is_numeric($balance) == false) {
      echobr("error: failed connecting to ufod");
      exit();
    }

//
// send funds
//
    for ($j = 0, $c = count($users); $j < $c; $j++) {
      if (strcasecmp($users[$j]->screen_name,TWITTER_HANDLE) == 0) continue;
      if ($amount > $balance) continue;

      $balance -= $amount;
      $RPC->move($name,$users[$j]->screen_name,$amount);
      
      $conn = new TwitterOAuth(API_KEY, API_SECRET, TOKEN_KEY, TOKEN_SECRET);

      $status = $conn->post("statuses/update", array("status" => "" .  "@" . $users[$j]->screen_name . ", " . "@" . $name . " sent you " . $amount . " " . CC_SYMBOL . "! Claim it at: " . HOMEPAGE));     
      
    }
  }

//
// update last tweet
//
  if ($count > 0) $tid = $tweets->statuses[0]->id_str;
  $next = time()+10;
  $file = "[" . $next . "," . $tid . "]";
  file_put_contents("last_tweet",$file);
}

?>

<html>
<head>
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <title>UFO Coin Tipper (beta)</title>
  <style>
    .text {font-family: sans-serif; color: #777; font-size: 20px; font-weight: 200; line-height: 1.4;}
  </style>

<!-- Custom styles for this template -->

<link href="css/custom-style.css" rel="stylesheet">
    
<link href="../bootstrap/bootstrap-3.3.0/docs/examples/jumbotron-narrow/jumbotron-narrow.css" rel="stylesheet">
<link href="http://maxcdn.bootstrapcdn.com/bootstrap/3.3.0/css/bootstrap.min.css" rel="stylesheet">

    
</head>
<body>
      
    <div class="container">
      <div class="header">
       <ul class="nav nav-pills pull-right" role="tablist">
          <li role="presentation"><a href="http://tipper.ufocoin.co/tips/index_home.php">Home</a></li>
          <li role="presentation"><a href="http://tipper.ufocoin.co/tips/index.php?with">Withdraw</a></li>
          <li role="presentation"><a href="http://tipper.ufocoin.co/tips/index.php?dep">Deposit</a></li>
          <li role="presentation"><a target="_blank" href="http://ufocoin.co/tips">How it Works</a></li>
       </ul>
          <p>           
           <h3 class="text-muted">UFO Tipper  </h3> 
          <p>
      </div>
    </div>
      
<div class="container">
    
<div  class="form-signin">
<div class="jumbotron">

<div class="container">
</div> <!-- container -->
 
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="http://maxcdn.bootstrapcdn.com/bootstrap/3.3.0/js/bootstrap.min.js"></script>


<?php

//
// Logout
//

if (isset($_GET["logout"])) {
  if (!isset($_SESSION["start"])) { echobr("You are not logged in."); exit(); }
  echobr("Logged out of " . $_SESSION["name"]);
  unset($_SESSION["name"]);
  unset($_SESSION["start"]);
  exit();
}

//
// Start or Next
//

if (empty($_SESSION["start"]) || empty($_SESSION["next"]) || empty($_SESSION["name"])) {

  if (empty($_GET["oauth_token"])) { // redirect TO twitter
    $conn = new TwitterOAuth(API_KEY,API_SECRET);
    
    $temporary_credentials = $conn->getRequestToken(HOMEPAGE);

    $redirect_url = $conn->getAuthorizeURL($temporary_credentials);
      
     
    $oauth_token = $temporary_credentials["oauth_token"];
    $oauth_token_secret = $temporary_credentials["oauth_token_secret"];
    $_SESSION["oauth_token_secret"] = $oauth_token_secret;

    echobr($redirect_url);
    header("Location: " . $redirect_url);
    echobr("redirecting to twitter..");
  }

//
// From Twitter
//
  
  else { // redirected FROM twitter
    $conn = new TwitterOAuth(API_KEY, API_SECRET, $_GET["oauth_token"],$_SESSION["oauth_token_secret"]);
    $token_credentials = $conn->getAccessToken($_GET["oauth_verifier"]);
     
    
//
// check for errors
//
    if (isset($conn->errors)) {
      echobr("error: " . $account->errors[0]->message);
      exit();
    }

    $_SESSION["oauth_token"] = $token_credentials["oauth_token"];
    $_SESSION["oauth_token_secret"] = $token_credentials["oauth_token_secret"];

    $conn = new TwitterOAuth(API_KEY, API_SECRET, $_SESSION["oauth_token"],$_SESSION["oauth_token_secret"]);
    $account = $conn->get("account/verify_credentials");
       
    
    // check for errors
    if (isset($account->errors)) {
      echobr("error: " . $account->errors[0]->message);
      exit();
    }

    $_SESSION["name"] = $account->screen_name;
    $_SESSION["start"] = true;
    $_SESSION["next"] = time();

    header("Location: " . "index.php");
  }
  exit();
}

//
// Next
//

if ($_SESSION["next"] <= time()) {
  $_SESSION["next"] = time()+45;
  $conn = new TwitterOAuth(API_KEY, API_SECRET, $_SESSION["oauth_token"],$_SESSION["oauth_token_secret"]);
  tweetFind($conn, $rpc);
}

//
// Name
//
$name = $_SESSION["name"];
$addr_arr   = $rpc->getaddressesbyaccount($name);
$balance    = $rpc->getbalance($name,MIN_CONF);
$balance_un = $rpc->getbalance($name,0);
$balance_un -= $balance;

if (($addr_arr == NULL && json_last_error() != 0) || is_numeric($balance) == false) {
  echobr("error: failed connecting to ufod");
  exit();
}
else if ($addr_arr == NULL && json_last_error() == 0) {

//
//echobr("creating address..");
//
  $addr = $rpc->getnewaddress($name);
  $addr_arr = $rpc->getaddressesbyaccount($name);
}
$addr = $addr_arr[0];


//
// Withdraw
//

if (empty($_GET["withdraw"]) == false) { // withdraw
  if (!isset($_GET["addr"]) || $_GET["addr"] == NULL) { echobr("error: 'addr' parameter is missing"); exit(); }  
  
  $amount = intval($_GET["withdraw"]);
  $addr = $_GET["addr"];

  if ($amount <= 0) { echobr("Sorry, invalid amount"); exit(); }
  if ($amount > $balance) { echobr("Sorry, amount exceeds balance, deposit using your address listed above"); exit(); }
  if ($amount < MIN_WITH) { echobr("Sorry, minimum withdraw is " . MIN_WITH); exit(); }

  $ret = $rpc->sendfrom($name,$addr,$amount);   //ccSendFrom($name,$addr,$amount);
  if ($ret == NULL) echobr("error: failed connecting to ufod");
  
  echobr("Withdraw Complete");
  echobr($ret);
  
  exit(); 
}

echo("<img src='../images/twitter_logo.png' height='50' width='50'>");
echo("<div class='pad_bot_10'>"."<span class='text_1'>Twitter Account:</span> <span class='text_1' style='font-weight: bold;'>@" . $name . "</span> </div>");
echo("<br />");

$balance_formatted = number_format($balance);

echo("<div class='pad_bot_10'>"."<span class='text_1'>Current Balance:</span> <span class='text_1' style='color:#0fabf6; font-weight: bold;'>" . $balance_formatted . " " . CC_SYMBOL . "</span>"."</div>");
echo("<br />");

if ($balance_un > 0) echo("<div>"."<span class='text_1' style='font-weight: bold;'>(Unconfirmed: " . $balance_un . ")</span>"."</div>");


echo("<img src='../images/ufo_icon.png' height='75' width='75'>");
echo("<br />");


//
// Show Deposit 
//

if (isset($_GET['dep'])){
echo("<span class='text_1'>Deposit Address</text> <text style='font-weight: bold;' class='text_1'></span>");
echo("<br />");
echo("<span class='text_1'>" . $addr . "</text></span>");
echo("<br />");
echo("<br />");
echo("<img  class='img_n' src=\"https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . $addr . "\">");
echo("<br />");
}

//
// Show Withdraw
//

if (isset($_GET['with'])){
echo("<br />");
echo("<div> <span class='text_1'>Withdraw Address:</span> <input  type='text' class='form-control' id='addr'></div>");
echo("<br />");
echo("<div> <span class='text_1'>Withdraw Amount:</span>  <input  type='text' class='form-control' id='amount'></div>");
echo("<br />");
}


?>

<div id="content" <?php if (isset($_GET['dep'])){ echo 'style="display:none;"'; } ?> >
 <input type="submit" value="Withdraw" class='btn btn-primary btn-block' onClick="window.location = 'index.php?withdraw=' + document.getElementById('amount').value + '&addr=' + document.getElementById('addr').value">
</div>
 
</div><!-- /container -->
 
 <div class="footer">
        <p>Sponsored by &copy; UFO Coin 2014 <a target="_blank" href="http://ufocoin.co">Learn More about UFO Coin</a></p> <h5 class='text_1'>v0.02(beta)</h5> </div>

    
</div><!-- /container -->
   
</body>
</html>
