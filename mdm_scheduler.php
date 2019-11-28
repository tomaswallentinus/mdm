<?php
set_time_limit(600);
session_start();

/* A script like this can be added to a curl or similar in Task Scheduler or Cron */
/* Ad a proper login-handler */

/* Import common functions */
require_once( 'mdm_commands.php' );

$myUser = "";
$myPass = "";
$myDB = "";

$pdo = new PDO ('mysql:host=localhost;charset=utf8mb4;dbname=' . $myDB, $myUser, $myPass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->prepare("SELECT computer_serial,udid FROM `computers` where (mdm_update is null or mdm_update < DATE_SUB(NOW(), INTERVAL 150 DAY)) and udid<>''
and teg_version<>'UN' and (computer_change<>'2' or computer_change is null)
order by mdm_checkin desc limit 10");
$stmt->execute();
$computers=$stmt->fetchAll(\PDO::FETCH_ASSOC);
if (count($computers)>0){
	echo 'Update Enrollment profile:<br><pre>';
	foreach ($computers as $computer){
		echo $computer['computer_serial'] . '<br>';
		installProfile($computer['udid'],$enroll);
		$stmt = $pdo->prepare("update `computers` set mdm_update=now() where computer_serial=:view");
		$stmt->execute(array(':view'=>$computer['computer_serial']));
		sleep(2);
	}
	echo '</pre>';
} else {
	$stmt = $pdo->prepare("SELECT computer_serial,udid FROM `computers` where (profiles_check is null or profiles_check < DATE_SUB(NOW(), INTERVAL 100 DAY)) and udid<>''
	and teg_version<>'UN' and (computer_change<>'2' or computer_change is null)
	order by mdm_checkin desc limit 10");
	$stmt->execute();
	$computers=$stmt->fetchAll(\PDO::FETCH_ASSOC);
	if (count($computers)>0){
		echo 'ProfileList:<br><pre>';
		foreach ($computers as $computer){
			echo $computer['computer_serial'] . '<br>';
			checkProfiles($computer['udid']);
			$stmt = $pdo->prepare("update `computers` set profiles_check=now() where computer_serial=:view");
			$stmt->execute(array(':view'=>$computer['computer_serial']));
			sleep(2);
		}
		echo '</pre>';
	}

	$stmt = $pdo->prepare("SELECT profile_udid,udid,computer_profiles.computer_serial FROM `computer_profiles` 
	inner join computers on computers.computer_serial=computer_profiles.computer_serial and teg_version<>'UN' and (computer_change<>'2' or computer_change is null)
	where profile_status=1
	and (
	(profile_name<>'ProfileName 1.3' and profile_udid='proflie1_uuid') or
	(profile_name<>'ProfileName 2.4' and profile_udid='proflie2_uuid') or
	(profile_name<>'ProfileName 0.1' and profile_udid='proflie3_uuid') or
	(profile_name<>'ProfileName 1.6' and profile_udid='proflie4_uuid')
	)
	order by mdm_checkin asc limit 10");
	$stmt->execute();
	$update=$stmt->fetchAll(\PDO::FETCH_ASSOC);
	if (count($update)>0){
		echo 'Update profile:<br><pre>';
		foreach ($update as $computer){
			echo $computer['computer_serial'] . "\n";
      /* Update profiles */
			switch ($computer['profile_udid']){
				case 'proflie1_uuid':
					installProfile($computer['udid'],$teggrund);
					break;
				case 'proflie2_uuid':
					installProfile($computer['udid'],$tcc_profile);
					break;
				case 'proflie3_uuid':
					installProfile($computer['udid'],$area53);
					break;
				case 'proflie4_uuid':
					installProfile($computer['udid'],$bootrunner);
					break;
			}
			$stmt = $pdo->prepare("update `computer_profiles` set profile_status=3 where computer_serial=:serial and profile_udid=:profil");
			$stmt->execute(array(
				':serial'=>$computer['computer_serial'],
				':profil'=>$computer['profile_udid']
			));
		}
		echo '</pre>';
	}
	
	$stmt = $pdo->prepare("SELECT profile_udid,udid,computer_profiles.computer_serial FROM `computer_profiles` 
	inner join computers on computers.computer_serial=computer_profiles.computer_serial and teg_version<>'UN' and (computer_change<>'2' or computer_change is null)
	where profile_status=1
	and (
	profile_udid='profiludid_of_profile_to_delete1' or
	profile_udid='profiludid_of_profile_to_delete2' or
	profile_udid='profiludid_of_profile_to_delete3' or
	profile_udid='profiludid_of_profile_to_delete4'
	)
	order by mdm_checkin asc limit 10");
	$stmt->execute();
	$remove=$stmt->fetchAll(\PDO::FETCH_ASSOC);
	if (count($remove)>0){
		echo 'Remove profile:<br><pre>';
		foreach ($remove as $computer){
			echo $computer['computer_serial'] . "\n";
			removeProfile($computer['udid'],$computer['profile_udid']);
			if ($computer['profile_udid']=='profil_udid_of_profile_to_delete2'){
        /* To replace with a new profile */
				installProfile($computer['udid'],$new_profile_plist);
			}
			$stmt = $pdo->prepare("update `computer_profiles` set profile_status=3 where computer_serial=:serial and profile_udid=:profil");
			$stmt->execute(array(
				':serial'=>$computer['computer_serial'],
				':profil'=>$computer['profile_udid']
			));
		}
		echo '</pre>';
	}
}
?>
