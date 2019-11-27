<?php
ini_set('session.gc_maxlifetime', 3600);
session_set_cookie_params(3600);
session_start();
###### Import the Google Api library ######
require_once $_SERVER['DOCUMENT_ROOT'] . "/Google/autoload.php";
$_GET = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
$serial='';
$token='';
########## Google Settings.Client ID, Client Secret from https://console.developers.google.com #############
$client_id = ''; 
$client_secret = '';
$redirect_uri = '';
########## MySql details  #############
$myUser = '';
$myPass = '';
$myDB = '';
######### Other settings #########
$transport_salt = '';
$host_domain = '';
$logo_path = '';
###################################################################
if (isset($_SERVER['HTTP_X_APPLE_ASPEN_DEVICEINFO'])){
	$plist_match='/<plist[^>]*?>[\s\S]*?<\/plist>/mi';
	$cms_envelope=base64_decode($_SERVER['HTTP_X_APPLE_ASPEN_DEVICEINFO']);
	preg_match_all($plist_match, $cms_envelope, $plist, PREG_SET_ORDER, 0);
	require_once 'PlistParser.php';
	$xmlParse = new PlistParser;
	$device_info=$xmlParse->StringToArray( $plist[0][0] );
}
$pdo = new PDO ('mysql:host=localhost;charset=utf8mb4;dbname=' . $myDB, $myUser, $myPass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (isset($_GET['token'])){
	$token=$_GET['token'];
}
if (isset($_GET['logout'])){
	unset($_SESSION['access_token_mdm']);
}
if (isset($_GET['serial'])){
	$serial=$_GET['serial'];
	if (strpos($serial, 'APPROVAL') !== false) {
		$tegid=str_replace('APPROVAL','',$serial);
	}
	if (hash('sha512',$transport_salt . $serial)!=str_replace("  -","",$token)){
		echo 'Fel token';
		exit();
	}
} elseif (isset($_GET['state'])) {
	$serial=base64_decode($_GET['state']);
	if (strpos($serial, 'APPROVAL') !== false) {
		$tegid=str_replace('APPROVAL','',$serial);
	}
} else {
	$stmt = $pdo->prepare("insert into approvals (approval_date) values (CURRENT_TIMESTAMP())");
	$stmt->execute();
	$serial='APPROVAL' . $pdo->lastInsertId();
}
function base64UrlEncode($inputStr) {
    return strtr(base64_encode($inputStr), '+/=', '-_,');
}
function base64UrlDecode($inputStr) {
    return base64_decode(strtr($inputStr, '-_,', '+/='));
}
$stateString = base64UrlEncode($serial);
$client = new Google_Client();
$client->setClientId($client_id);
$client->setClientSecret($client_secret);
$client->setRedirectUri($redirect_uri);
$client->setState($stateString);
$client->addScope("email");
$client->addScope("profile");
$client->setHostedDomain($host_domain);
$service = new Google_Service_Oauth2($client);
//If code is empty, redirect user to google authentication page for code.
//Code is required to aquire Access Token from google
//Once we have access token, assign token to session variable
//and we can redirect user back to page and login.
if (isset($_GET['code'])) {
  $client->authenticate($_GET['code']);
  $_SESSION['access_token_mdm'] = $client->getAccessToken();
  header('Location: ' . filter_var($redirect_uri . '?serial=' . $serial . '&token=' . hash('sha512',$transport_salt . $serial), FILTER_SANITIZE_URL));
  exit;
}
//if we have access_token continue, or else get login URL for user
if (isset($_SESSION['access_token_mdm']) && $_SESSION['access_token_mdm']) {
  $client->setAccessToken($_SESSION['access_token_mdm']);
  if ($client->isAccessTokenExpired()){
	$authUrl = $client->createAuthUrl();
  }
} else {
  $authUrl = $client->createAuthUrl();
}
?><!doctype html>
<html class="no-js" lang="">
    <head>
		<link media="all" rel="stylesheet" href="mdm.css" />
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <title>Registrering TEG-datorer</title>
        <meta name="description" content="Registrering för Täby Enskildas datorer">
        <meta name="viewport" content="width=device-width, initial-scale=1">
		<style>
		body {background-color:#E50050;color:#fff;}
		a,a:hover {color:#fff;}
		div {font-family:'Raleway', sans-serif;max-width:600px;padding:20px;}
		img {width:100%;margin-bottom:30px;max-height:50vh;}
		.button {font-size:14pt;}
		</style>
<?php
	if (isset($authUrl)){
		echo '</head><body><div><img src="' . $logo_path . '">';
		echo '<h1>Logga in på ditt Google-konto</h1><p>När du tar studenten från Täby Enskilda har du möjlighet att köpa denna dator för ett förmånligt pris, vårda den därför ömt.<br><br>För att snabbare hitta rätt användare till datorn vid e-postkontakt behöver den kopplas till ditt Google-konto. Tryck på knappen "Registrera datorn".</p>';
		echo '<a class="button" href="' . $authUrl . '">Registrera datorn</a>';
	} else {
		$user = $service->userinfo->get();
		if (isset($user->email)){
			if ($tegid!=''){
				$google_namn=$user->givenName . ' ' . $user->familyName;
				$stmt = $pdo->prepare("update approvals set google_id=:id,google_user=:email,google_namn=:google_namn WHERE approval_id=:tegid");
				$stmt->execute(array(':id'=>$user->id,':email'=>$user->email,':google_namn'=>$google_namn,':tegid'=> $tegid));
				echo '<meta http-equiv="refresh" content="4;URL=\'enrollmentgenerator.php?tegid=' . $tegid . '" />';
				echo '</head><body><div><img src="' . $logo_path . '">';
				echo '<h1>Tack ' . $user->givenName . ' för att du registrerade datorn</h1><p>Snart fortsätter installationen.</p>';
			} else {
				echo '</head><body><div><img src="' . $logo_path . '">';
				//check if user exist in database using COUNT
				$stmt = $pdo->prepare("SELECT computer_user FROM computers WHERE computer_serial=:serial");
				$stmt->execute(array(':serial'=> $serial));
				$user_count = $stmt->fetchAll(\PDO::FETCH_NUM);
				if (count($user_count)>0){
					if ($user_count[0]['computer_user']!=''){
						echo '<h1>Tack ' . $user->givenName . ' för att du uppdaterar registreringen av datorn</h1><p>Det går nu bra att stänga detta fönster för att fortsätta att arbeta.</p>';
					} else {
						echo '<h1>Tack ' . $user->givenName . ' för att du registrerade datorn</h1><p>Det går nu bra att stänga detta fönster för att fortsätta arbeta.</p>';
					}
					$stmt = $pdo->prepare("update computers set google_id=:id,google_user=:email WHERE computer_serial=:serial");
					$stmt->execute(array(':id'=>$user->id,':email'=>$user->email,':serial'=> $serial));
				} else {
					echo '<h1>Datorn med serienummer ' . $serial . ' kunde inte hittas</h1>';
				}
			}
		} else {
			echo '</head><body><div><img src="' . $logo_path . '">';
			echo '<h1>Användaren hittades inte</h1>';
		}
	}?>
</div>
<script src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js"></script>
<script>
  WebFont.load({
    google: {
      families: ['Raleway']
    }
  });
</script>
</body></html>
