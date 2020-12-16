<?php require_once 'config/database.php'; 
use Intercom\IntercomClient;
	
	$email = trim($_POST['email']);
	$deposit = trim($_POST['deposit']);
	$type = trim($_POST['type']);
	$broker_server_name = trim($_POST['broker_server_name']);
	$createdat = trim($_POST['createdat']);
	$password = trim($_POST['password']);
    $act_nr = trim($_POST['actnumber']);
	
	$query = mysqli_query($con,"select daily_dd,total_dd from fxtracking.`acc_type` WHERE type = '".$type."'");
	$row = mysqli_fetch_object($query);

	$maxdailydd = $row->daily_dd * $deposit/100;
	$maxtotaldd = $row->total_dd * $deposit/100;
    
	
	$log = "";
	
				if ($act_nr>0 ) 
				{
					$sql = "INSERT IGNORE INTO fxtracking.`accountsfx` (`id`, `email`, `login`, `pass`, `mt4_server`, `createdat`, `deposit`, `balance`, `equity`, `max_allowed_dd`, `max_dd`, `max_dd_time`, `maxdaily_allowed_dd`, `maxdaily_dd`, `maxdaily_dd_time`, `type`, `status`, `last_sync`, `notif`, `freshsales_id`) VALUES ('$act_nr', '$email', NULL, '$password', '$broker_server_name', '".substr($createdat,0,10)."', '$deposit', NULL, NULL, '$maxtotaldd', NULL, NULL, '$maxdailydd', NULL, NULL, '$type', 'NEW', NULL, NULL, '7777777')";
					
					if ($deposit>0)
					{	
						$run_q = mysqli_query($con,$sql); 
						
						// we need to check if a row was affected, then add it to our other DB
						if (mysqli_affected_rows($con)>0)
						{		
							
							/******************
							Intercom Integration - Status is being sent below to $status as well
							******************/
							$client = new IntercomClient('dG9rOmRlMWZkNDZhX2QyMmJfNDlhZl84YzExX2ZiNmIzZTA5ZjNhNToxOjA=');	// Replace API token

							// Check if email exist in intercom
							$existe = 1;
							try{
								$client->users->getUsers([
									'email' => $email,
								]);
							} catch (Http\Client\Exception $e) {
								if ($e->getCode() == '404') {
									$existe = 0;
								} else {
									throw $e;
								}
							}

							if ($existe == 0) {// Not exist
								$status = "NoSuch Email and abort it";
							} else {
								$status = "OK";
								$message_sent = true;
								try {
									$client->messages->create([
										'message_type' => 'email',
										'subject' => 'New Account Details',
										'template' => 'personal',
										"body" => "\ " .
										"<html> \ " .
											"<body> \ " .
												"<p>Hello<br> \ " .
												"</p> \ " .
												"<p> \ " .
													"Here are your demo account details: " .
												"</p> \ " .
													"<ul> \ " .
														"<li>Broker: ".$broker_server_name."</li> \ " .
														"<li>Server: ".$broker_server_name."</li>  \ " .
														"<li>Account Number: ".$act_nr."</li> \ " .
														"<li>Trader Password: ".$password."</li> \ " .
													"</ul> \ " .
												"<h2> \ " .
													"An Ordered HTML List \ " .
												"</h2> \ " .
												"<ol> \ " .
													"<li>Talent Bonus 1: </li> \ " .
													"<li>Talent Bonus 2: </li>  \ " .
												"</ol> \ " .
												"We can't wait to see your progress. Please reach out to our support team at any time if you have questions. \ " .
												"agentName <br> \ " .
												"Funding Talent Support <br> \ " .
												
											"</body> \ " .
										"</html>",
										'from' => [
											'id' => '1234',	// input admin ID
											'type' => 'admin',
										],
										'to' => [
											'email' => $email,
											'type' => 'user',
										],
									]);
								} catch (Http\Client\Exception $e) {
									$message_sent = false;
								}
								
							};


							$createdat = date('Y-m-d H:i:s');
							$action = "(NEW) $act_nr + $email $type $deposit $act_nr $password $createdat";
							$email_history_q = "INSERT IGNORE INTO fxtracking.`email_history` (`createdat`,`status`,`email`,`action`) VALUES ('$createdat','$status','$email','".$action."')";
							mysqli_query($con,$email_history_q);
							$sql_servers = "SELECT api_server_ip , cnt, tudor.api_servers.max_accounts, cnt/tudor.api_servers.max_accounts as `load` FROM (SELECT `api_server_ip`, COUNT(`account_number`) as `cnt` FROM tudor.`accounts` WHERE `account_status`!=7 GROUP BY `api_server_ip`) as x1, tudor.api_servers WHERE api_servers.ip LIKE `api_server_ip` ORDER BY `load` ASC";
							
							$run_servers = mysqli_query($con,$sql_servers);
							
							$row = mysqli_fetch_array($run_servers, MYSQLI_ASSOC);
							//print_r(error_get_last());
							if (isset($row['cnt']))
							{
								//$log .= " <br/> Lowest Load ".$row['api_server_ip']." cnt:".$row['cnt']." max:".$row['max_accounts']." load:".$row['load'];								
								
								if ($row['load']<1.1)
								{
									$sql_newact = "INSERT IGNORE INTO tudor.`accounts` (`account_number`,`broker_server_name`,`password`,`api_server_ip`) VALUES ('$act_nr','$broker_server_name','$password','".$row['api_server_ip']."')";
									
									
									$run_newact = mysqli_query($con,$sql_newact);
									
									print_r(error_get_last());
									
									if (mysqli_affected_rows($con)>0)
									{
										$sql_attach = "INSERT IGNORE INTO tudor.expert_templates (account_id,`options`) SELECT id, '<chart></chart>' FROM tudor.accounts";
										$run_attach = mysqli_query($con,$sql_attach);
										
										if (mysqli_affected_rows($con)>0) $log .= " <br/>  [ OK ]  Account $act_nr ";
											else $log .= " <br/> ====== ERROR #5 ===== Account Exists  $sql_attach \n";
									} else $log .= " <br/> ====== ERROR #4 ===== Account Exists  $sql_newact \n";
									
								} else $log .= " <br/> ====== ERROR #3 ===== Load too Big  \n";
							} else $log .= " <br/> ====== ERROR #2 SERVERS LOAD ===== \n";	
							
						} else $log .= "<br/> ===== ERROR #0 =====  ACCOUNT EXIST: $act_nr <br/>";						
					} else    $log .= " <br/> ====== ERROR #1 ===== with act $type $act_nr ";					
				}
				
				if ($log!="")
				{
					$myfile = fopen("addact_log.txt", "a");
					$log = str_replace("<br/>","\n",$log);					
					fwrite($myfile, $log);
					fclose($myfile);
				}
	
	
	//$q = mysqli_query($con,"select type FROM fxtracking.`acc_type` WHERE type = '".$type."'"); // , '$email', '$createdat'
	if(strpos($log,"ERROR")!==false){
		
		$response = ['status'=>201,'message'=>$log];  //mysqli_error($con)
	}else
	{
		$response = ['status'=>200,'message'=>$log." ."];
		
	}
	mysqli_close($con);
	echo json_encode($response);
	//echo "OK $maxdailydd $maxtotaldd";
	exit;
	
	
?>