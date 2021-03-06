<?php

require_once('./functions.php');
if (! hasPermission("process-absence-requests")) err("You don't have permission to do this.");

$style = "
<style>
	td {
		width: 14%;
	}
	.data {
		border-top: 1px dotted #000000;
		border-bottom: 1px dotted #000000;
	}
</style>";

$pending = "
	<tr align='center'>
		<td style='font-weight: bold; text-decoration: underline; border-top: 1px solid #000000;' colspan='7'>New Requests</td>
	</tr>
	<tr align='center'>
		<td style='font-weight: bold;'>When</td>
		<td style='font-weight: bold;'>What</td>
		<td style='font-weight: bold;'>Who</td>
		<td style='font-weight: bold;'>Why</td>
		<td style='font-weight: bold;'>Replacement</td>
		<td style='font-weight: bold;'>Status</td>
		<td style='font-weight: bold;'>Action</td>
	</tr>
";

$addressed = "
	<tr align='center' style='font-weight: bold; text-decoration: underline; border-top: 1px solid #000000;'>
		<td class='heading' colspan='7'>Old Requests</td>
	</tr>
	<tr align='center'>
		<td style='font-weight: bold;'>When</td>
		<td style='font-weight: bold;'>What</td>
		<td style='font-weight: bold;'>Who</td>
		<td style='font-weight: bold;'>Why</td>
		<td style='font-weight: bold;'>Replacement</td>
		<td style='font-weight: bold;'>Status</td>
		<td style='font-weight: bold;'>Action</td>
	</tr>";

$requests = query("SELECT  `absencerequest`.`eventNo` ,  `absencerequest`.`time` ,  `absencerequest`.`reason` ,  `absencerequest`.`replacement` ,  `absencerequest`.`memberID` ,  `absencerequest`.`state` ,  `event`.`callTime` , `event`.`name` ,  `member`.`firstName` ,  `member`.`lastName` FROM  `absencerequest` ,  `member` ,  `event` WHERE  `absencerequest`.`eventNo` =  `event`.`eventNo` AND  `absencerequest`.`memberID` =  `member`.`email` AND `event`.`semester`=? ORDER BY  `member`.`lastName` ASC ,  `member`.`firstName` ASC ,  `absencerequest`.`time` DESC", [$SEMESTER], QALL);

$oldMember = "";
$newCount = 0;

foreach ($requests as $request)
{
	$eventNo =$request['eventNo'];
	$time = $request["time"];
	$reason = $request["reason"];
	$email = $request["memberID"];
	$state = $request["state"];
	$name = $request["firstName"]." ".$request["lastName"];
	$eventName = $request["name"];
	$replacement = "";


	if($request["replacement"]!="")
	{
		$result = query("select  `member`.`firstName`, `member`.`lastName` from  `member` where `member`.`email` = ?", [$request["replacement"]], QONE);
		if (! $result) err("Replacement is not a valid member");
		$replacement = $result["firstName"] . " " . $result["lastName"];
	}

	if($state=='pending'){
		$pending = $pending."
		<tr id='request_".$email."_$eventNo'>
			<td align='left' class='data'>$time</td>
			<td align='left' class='data'>$eventName</td>
			<td align='left' class='data'>$name</td>
			<td align='left' class='data'>$reason</td>
			<td align='center' class='data'>$replacement</td>
			<td align='center' class='data'>$state</td>
			<td align='center' class='data'><button onClick='approveAbsence(\"$eventNo\", \"$email\");'>approve</button>
			<button onclick='denyAbsence(\"$eventNo\", \"$email\");'>deny</button>
			</td>

		</tr>
		";
		$newCount++;
	}
	else{
		$addressed = $addressed."
		<tr align='left' id='request_".$email."_$eventNo'>
			<td align='left' class='data'>$time</td>
			<td align='left' class='data'>$eventName</td>
			<td align='left' class='data'>$name</td>
			<td align='left' class='data'>$reason</td>
			<td align='center' class='data'>$replacement</td>
			<td align='center' class='data'>$state</td> 
			<td align='center' class='data'><button onclick='toggleRequestState(\"$eventNo\", \"$email\");'>Toggle</button></td>
		</tr>
		";
	}
}

if($newCount==0){
	$pending = "
	<tr align='center'>
		<td style='font-weight: bold; border-top: 1px solid #000000;' colspan='7'>No New Requests?? Right on.</td>
	</tr>";
}


echo $style."<table id='newRequestTable'>".$pending.$addressed."</table>";
?>
