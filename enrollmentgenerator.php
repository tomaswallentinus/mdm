<?php
$_GET = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
$tegid='';
if (isset($_GET['tegid'])){$tegid='?tegid=' . $_GET['tegid'];}
function signMobileConfig (string $file_content) {
	$file_full_pathname="*pathto_tempstorage*temp.mobileconfig";
	file_put_contents($file_full_pathname,$file_content);
    openssl_pkcs7_sign(
        $file_full_pathname,
        $file_full_pathname.'.sig',
        "file://" . realpath("*pathto_sign_cert*mdmprofile_sign_cert.pem"),
        "file://" . realpath("*pathto_sign_cert_key*mdmprofile_sign_key.pem"),
        [], 0
    );
    $signed = file_get_contents($file_full_pathname.'.sig');

    unlink($file_full_pathname.'.sig');
    unlink($file_full_pathname);

    $trimmed = preg_replace('/(.+\n)+\n/', '', $signed, 1);
    return $trimmed;
}
function mdmComm($data){
	$ch = curl_init('*pathto_micromdm*');
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
		'Authorization: Basic *base64-auth to micromdm (username:password)*'
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

$enroll='*****Your enrollment profile from micromdm but changing the following items:
        <key>CheckInURL</key>
        <string>****micromdm_checkinurl****' . $tegid . '</string>
        <key>ServerURL</key>
        <string>****micromdm_connect_url****' . $tegid . '</string>';
header('Content-type: application/x-apple-aspen-config; chatset=utf-8');
header('Content-Disposition: attachment; filename=enrollTEG.mobileconfig');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
echo base64_decode(signMobileConfig ($enroll));
?>
