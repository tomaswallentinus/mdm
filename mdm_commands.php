<?php
/* Settings */
$base64_basiclogin = 'base64-encoded basic-login for micromdm';
$micromdm_path = '';
$path_temp='path to temporary storage';
$path_signcert='path to pem cert and key';

function signMobileConfig (string $file_content) {
	$file_full_pathname=$path_temp . "temp.mobileconfig";
	file_put_contents($file_full_pathname,$file_content);
    openssl_pkcs7_sign(
        $file_full_pathname,
        $file_full_pathname.'.sig',
        "file://" . realpath($path_signcert . "mdmprofile_sign_cert.pem"),
        "file://" . realpath($path_signcert . "mdmprofile_sign_key.pem"),
        [], 0
    );
    $signed = file_get_contents($file_full_pathname.'.sig');

    unlink($file_full_pathname.'.sig');
    unlink($file_full_pathname);

    $trimmed = preg_replace('/(.+\n)+\n/', '', $signed, 1);
    return $trimmed;
}
function mdmComm($data){
	$ch = curl_init($micromdm_path . '/v1/commands');
	curl_setopt($ch, CURLOPT_VERBOSE, true);
//	curl_setopt($ch, CURLOPT_STDERR, $out); 
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);                                                       
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");   
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_FAILONERROR,true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Accept: application/json;charset=utf-8',
		'Content-Type: application/json;charset=utf-8',
		'Authorization: ' . $base64_basiclogin
	));
	$result = curl_exec($ch);
	if (curl_error($ch)) {
		$error_msg = curl_error($ch);
	}
//print_r(curl_getinfo($ch,CURLINFO_HEADER_OUT));
	curl_close($ch);
	if (isset($error_msg)) {
		echo 'Error: ';
		print_r($error_msg);
	}
}
function DeviceConfigured($udid){
	$payload=array(
		"request_type"		=> 'DeviceConfigured',
		"udid"			=> $udid
	);
	$data_string = json_encode($payload, JSON_UNESCAPED_SLASHES);
	mdmComm($data_string);
}
function AccountConfiguration($udid,$shortname,$fullname){
	$payload=array(
		"request_type"					                    => 'AccountConfiguration',
		"udid"						                          => $udid,
		"skip_primary_setup_account_creation"		    => false,
		"set_primary_setup_account_as_regular_user"	=> false,
		"dont_auto_populate_primary_account_info"	  => false,
		"lock_primary_account_info" 				        => true,
		"primary_account_user_name" 				        => $shortname,
		"primary_account_full_name" 				        => $fullname
	);
	$data_string = json_encode($payload, JSON_UNESCAPED_SLASHES);
	mdmComm($data_string);
}
function InstallApplication($manifest,$udid){
	$payload=array(
		"request_type"		=> 'InstallApplication',
		"udid"			=> $udid,
		"manifest_url"		=> $manifest,
		"management_flags"	=> 1
	);
	$data_string = json_encode($payload, JSON_UNESCAPED_SLASHES);
	mdmComm($data_string);
}
function InstallProfile($udid,$profile){
	$payload=array(
		"request_type"		=> 'InstallProfile',
		"udid"			=> $udid,
		"payload"		=> signMobileConfig ($profile),
		"management_flags"	=> 1
	);
	$data_string = json_encode($payload, JSON_UNESCAPED_SLASHES);
	mdmComm($data_string);
}
?>
