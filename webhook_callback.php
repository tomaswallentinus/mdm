<?php
$myUser = '';
$myPass = '';
$myDB = '';
$pdo = new PDO ('mysql:host=localhost;charset=utf8mb4;dbname=' . $myDB, $myUser, $myPass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$log_entry=json_decode(file_get_contents('php://input'));

$computer=array();

switch ($log_entry->topic) {
	case "mdm.Connect":
		//Update database device enrolled
		$udid=filter_var($log_entry->acknowledge_event->udid, FILTER_SANITIZE_STRING);

		$raw_payload=$log_entry->acknowledge_event->raw_payload;
		if ($raw_payload!='' && $log_entry->acknowledge_event->status=='Acknowledged'){
			$stmt = $pdo->prepare("select computer_serial from computers where udid=:udid");
			$stmt->execute(array(
				':udid'		=>	$udid
			));
			$check = $stmt->fetchAll(\PDO::FETCH_ASSOC);
			if (count($check)>0){
				$raw_payload=base64_decode($raw_payload);
				if (strlen($raw_payload)>425){
					require_once 'PlistParser.php';
					$xmlParse = new PlistParser;
					$xml=$xmlParse->StringToArray( $raw_payload );
					if (isset($xml['ProfileList'])){
						$stmt = $pdo->prepare("update computer_profiles set profile_status=0 where computer_serial=:serial and profile_status=1");
						$stmt->execute(array(
							':serial'		=>	$check[0]['computer_serial']
						));
						foreach ($xml['ProfileList'] as $profile){
							$stmt = $pdo->prepare("insert into computer_profiles (computer_serial,profile_name,profile_udid,profile_status) values (:serial,:name,:udid,1) ON DUPLICATE KEY UPDATE profile_date=NOW(),profile_name=:name,profile_status=1");
							$stmt->execute(array(
								':serial'	=>	$check[0]['computer_serial'],
								':name'		=>	$profile['PayloadDisplayName'],
								':udid'		=>	$profile['PayloadIdentifier']
							));
						}
						$stmt = $pdo->prepare("update computer_profiles set profile_status=2 where computer_serial=:serial and profile_status=0");
						$stmt->execute(array(
							':serial'		=>	$check[0]['computer_serial']
						));
					} else {
						$stmt = $pdo->prepare("INSERT INTO computer_checks (computer_serial,check_blob,check_type) VALUES (:serial,:blob,'mdm')");
						$stmt->execute(array(
							':serial'		=>	$check[0]['computer_serial'],
							':blob'		=>	$raw_payload
						));
					}
				}
			}
		}
		$stmt = $pdo->prepare("update computers set mdm_status=3,mdm_checkin=NOW() where udid=:udid and udid<>''");
		$stmt->execute(array(':udid'=>$udid));
		break;
	case "mdm.CheckOut":
		//Update database device unenrolled
		$udid=filter_var($log_entry->checkin_event->udid, FILTER_SANITIZE_STRING);
		$stmt = $pdo->prepare("update computers set mdm_status=4,mdm_checkin=NOW() where udid=:udid and udid<>''");
		$stmt->execute(array(':udid'=>$udid));
		break;
	case "mdm.TokenUpdate":
		$udid=filter_var($log_entry->checkin_event->udid, FILTER_SANITIZE_STRING);
		$stmt = $pdo->prepare("update computers set mdm_status=2,mdm_checkin=NOW() where udid=:udid and udid<>''");
		$stmt->execute(array(':udid'=>$udid));
		break;
	case "mdm.Authenticate":
		//Update database with udid for serialnumer
		$udid=filter_var($log_entry->checkin_event->udid, FILTER_SANITIZE_STRING);
		if (isset($log_entry->checkin_event->url_params->tegid)){
			$tegid=json_decode($log_entry->checkin_event->url_params->tegid);
			if ($tegid!=''){
				$stmt = $pdo->prepare("select google_id,google_user from approvals where approval_id=:tegid");
				$stmt->execute(array(
					':tegid'		=>	$tegid
				));
				$approvals = $stmt->fetchAll(\PDO::FETCH_ASSOC);
				if (count($approvals)>0){
					$google_id=$approvals[0]['google_id'];
					$google_user=$approvals[0]['google_user'];
				}
			}
		}
		
		$payload=base64_decode($log_entry->checkin_event->raw_payload);
		$xml = new SimpleXMLElement($payload);
		$result = $xml->xpath("//key[.='SerialNumber']/following-sibling::string");
		$serialnumber = filter_var((string)$result[0], FILTER_SANITIZE_STRING);
		if ($serialnumber!='' && $udid!=''){
			if ($google_id!=''){
				$stmt = $pdo->prepare("INSERT INTO computers (computer_serial,udid,mdm_checkin,mdm_status,google_id,google_user,mdm_update) VALUES (:serial,:udid,now(),1,:google_id,:google_user,now()) ON DUPLICATE KEY UPDATE udid=:udid,mdm_checkin=now(),mdm_status=1,google_id=:google_id,google_user=:google_user,FIRMWARE_PASSWORD='',FIRMWARE_HASH='',computer_mdm=''");
				$stmt->execute(array(
					':serial'		=>	$serialnumber,
					':udid'			=>	$udid,
					':google_id'	=>	$google_id,
					':google_user'	=>	$google_user
				));
			} else {
				$stmt = $pdo->prepare("INSERT INTO computers (computer_serial,udid,mdm_checkin,mdm_status,mdm_update) VALUES (:serial,:udid,now(),1,now()) ON DUPLICATE KEY UPDATE udid=:udid,mdm_checkin=now(),mdm_status=1,FIRMWARE_PASSWORD='',FIRMWARE_HASH='',computer_mdm=''");
				$stmt->execute(array(
					':serial'		=>	$serialnumber,
					':udid'			=>	$udid
				));
			}
		}
		
		break;
	default:
		//Else save log
		$log_entry=base64_encode(serialize($log_entry));
		$stmt = $pdo->prepare("insert into mdm_logg (logg) values (:logg)");
		$stmt->execute(array(':logg'=>$log_entry));
}
?>
