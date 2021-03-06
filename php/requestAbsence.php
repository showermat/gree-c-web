<?php
require_once('./functions.php');

if(! isset($_POST['eventNo'])) err("No event number provided");
$eventNo = $_POST['eventNo'];
$replacement = $_POST['replacement'];
$reason = $_POST['reason'];

//if they didn't specify a reason, don't let them off the hook
if ($reason == "") err("You need a reason.  Try again.<br><div class='btn' id='retryAbsenceButton' value='$eventNo'>try again</div>");
$recipients = implode(", ", getPosition("Vice President")) . ", " . implode(", ", getPosition("President"));
if (query("select * from `absencerequest` where `memberID` = ? and `eventNo` = ?", [$USER, $eventNo], QCOUNT) > 0) err("You have already submitted an absence request for this event.");
query("insert into `absencerequest` (reason, memberID, eventNo) values (?, ?, ?)", [$reason, $USER, $eventNo]);
if (query("select * from `emailSettings` where `id` = ? and `enabled` != '0'`", ["new-absence-request"], QCOUNT) > 0) mail($recipients, "Absence Request from " . memberName($USER, "real"), 'Name:  ' . memberName($USER, "real") . '<br>Event:  ' . getEventName($eventNo) . '<br>Reason:  ' . $reason);
echo "<p>Your request has been submitted.  You lazy bum!</p>";
?>
