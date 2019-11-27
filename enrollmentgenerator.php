<?php
$_GET = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
$tegid='';
$path_temp='path to temporary storage';
$path_signcert='path to pem cert and key';
$micromdm_path='';
$base64_basiclogin = 'Basic *base64-auth to micromdm (username:password)*';

if (isset($_GET['tegid'])){$tegid='?tegid=' . $_GET['tegid'];}

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
