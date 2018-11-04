<?php
function attendance($memberID, $mode, $semester = '', $media = 'normal')
{
	// Type:
	// 0 for grade
	// 1 for officer table
	// 2 for member table
	// 3 for gig count
	// 4 for raw array
	global $SEMESTER, $CHOIR;
	if ($semester == '') $semester = $SEMESTER;
	if (! $CHOIR) die("No choir selected");

	$eventRows = '';
	$tableOpen = '<table>';
	$tableClose = '</table>';
	$retarr = [];
	if ($mode == 1)
	{
		$eventRows = '<thead>
			<th>Event</th>
			<th>Date</th>
			<th>Type</th>
			<th>Should Have<br>Attended</th>
			<th>Did Attend</th>
			<th>Minutes Late</th>
			<th>Point Change</th>
			<th>Partial Score</th>
		</thead>';
	}
	else if ($mode == 2)
	{
		$tableOpen = '<table width="100%" id="defaultSidebar" class="table no-highlight table-bordered every-other">';
		$eventRows = '<thead>
			<th><span class="heading">Event</span></th>
			<th><span class="heading">Should have attended?</span></th>
			<th><span class="heading">Did attend?</span></th>
			<th><span class="heading">Point Change</span></th>
		</thead>';
	}
	$score = 100;
	$gigcount = 0;
	$result = query("select `gigreq` from `semester` where `semester` = ?", [$semester], QONE);
	if (! $result) die("Invalid semester");
	$gigreq = $result['gigreq'];

	$query = query("select `attends`.`eventNo`, `attends`.`shouldAttend`, `attends`.`didAttend`, `attends`.`minutesLate`, `attends`.`confirmed`, UNIX_TIMESTAMP(`event`.`callTime`) as `call`, UNIX_TIMESTAMP(`event`.`releaseTime`) as `release`, `event`.`name`, `event`.`type`, `eventType`.`name` as `typeName`, `event`.`points`, `event`.`gigcount` from `attends`, `event`, `eventType` where `attends`.`memberID` = ? and `event`.`eventNo` = `attends`.`eventNo` and `event`.`releaseTime` <= (current_timestamp - interval 1 day) and `event`.`type` = `eventType`.`id` and `event`.`semester` = ? and `event`.`choir` = ? order by `event`.`callTime` asc", [$memberID, $semester, $CHOIR], QALL);
	if (count($query) == 0)
	{
		if ($mode == 0) return $score;
		if ($mode == 3) return $gigcount;
		if ($mode == 4) return array("attendance" => $retarr, "final_score" => $score);
		return $tableOpen . $eventRows . $tableClose;
	}
	$allEvents = [];
	foreach ($query as $row)
	{
		// FIXME This method will fail if a semester lasts more than a year.
		$week = intval(date("W", $row["call"]));
		if (! array_key_exists($week, $allEvents)) $allEvents[$week] = [];
		$allEvents[$week][] = $row;
	}
	foreach ($allEvents as $week => $events)
	{
		$reqRehearsals = 0;
		$attRehearsals = 0;
		$reqSectionals = 0;
		$attSectionals = 0;
		foreach ($events as $event)
		{
			$type = $event["type"];
			if (! $type == "rehearsal" && ! $type == "sectional") continue;
			if ($event["shouldAttend"] == "1")
			{
				if ($type == "rehearsal") $reqRehearsals += 1;
				if ($type == "sectional") $reqSectionals += 1;
			}
			if ($event["didAttend"] == "1")
			{
				if ($type == "rehearsal") $attRehearsals += 1;
				if ($type == "sectional") $attSectionals += 1;
			}
		}
		$sectDiff = $attSectionals - $reqSectionals;
		foreach ($events as $event)
		{
			$eventNo = $event['eventNo'];
			$eventName = $event['name'];
			$type = $event['type'];
			$typeName = $event['typeName'];
			$points = $event['points'];
			$shouldAttend = $event['shouldAttend'];
			$didAttend = $event['didAttend'];
			$minutesLate = $event['minutesLate'];
			$confirmed = $event['confirmed'];
			$call = $event['call'];
			$release = $event['release'];
			$eventID = "attends_" . $memberID . "_$eventNo";
			$tip = "";
			$curgig = 0;
			$pointChange = 0;
			if ($didAttend == '1')
			{
				$tip = "No point change for attending required event";
				$bonusEvent = ($type == "volunteer" || $type == "ombuds" || ($type == "other" && $shouldAttend == '0') || ($type == "sectional" && $sectDiff > 0));
				// Get back points for volunteer gigs and and extra sectionals and ombuds events
				if ($bonusEvent)
				{
					if ($score + $points > 100)
					{
						$pointChange += 100 - $score;
						$tip = "Event grants $points-point bonus, but grade is capped at 100%";
					}
					else
					{
						$pointChange += $points;
						$tip = "Full bonus awarded for attending volunteer or extra event";
					}
					if ($type == "sectional") $sectDiff -= 1;
				}
				// Lose points equal to the percentage of the event missed, if they should have attended
				if ($minutesLate > 0)
				{
					$effectiveValue = $points;
					if ($pointChange > 0) $effectiveValue = $pointChange;
					$duration = floatval($release - $call) / 60.0;
					$delta = round(floatval($minutesLate) / $duration * $effectiveValue, 2);
					$pointChange -= $delta;
					if ($type == "ombuds") { }
					else if ($bonusEvent)
					{
						$pointChange -= $delta;
						$tip = "Event would grant $effectiveValue-point bonus, but $delta points deducted for lateness";
					}
					else if ($shouldAttend == '1')
					{
						$pointChange -= $delta;
						$tip = "$delta points deducted for lateness to required event";
					}
				}
				// Get gig credit for volunteer gigs if they are applicable
				if ($type == "volunteer" && $event['gigcount'] == '1')
				{
					$gigcount += 1;
					$curgig = 1;
				}
				// If you haven't been to rehearsal this week, you can't get points or gig credit
				if ($attRehearsals < $reqRehearsals)
				{
					if ($type == "volunteer")
					{
						$pointChange = 0;
						if ($curgig)
						{
							$gigcount -= 1;
							$curgig = 0;
						}
						$tip = "$points-point bonus denied because this week&apos;s rehearsal was missed";
					}
					else if ($type == "tutti")
					{
						$pointChange = -$points;
						$tip = "Full deduction for unexcused absence from this week&apos;s rehearsal";
					}
				}
			}
			// Lose the full point value if did not attend
			else if ($shouldAttend == '1')
			{
				if ($type == "ombuds") $tip = "You do not lose points for missing an ombuds event";
				else if ($type == "sectional" && $sectDiff >= 0) $tip = "No deduction because you attended a different sectional this week";
				else
				{
					$pointChange = -$points;
					$tip = "Full deduction for unexcused absence from event";
					if ($type == "sectional") $sectDiff += 1;
				}
			}
			else $tip = "Did not attend and not expected to";
			$score += $pointChange;
			// Prevent the score from ever rising above 100
			if ($score > 100) $score = 100;
			if ($pointChange > 0) $pointChange = '+' . $pointChange;

			if ($mode == 1)
			{
				$date = date("D, M j, Y", (int) $call);
				//name, date and type of the gig
				$eventRows .= "<tr id='$attendsID'><td class='data'><a href='#event:$eventNo'>$eventName</a></td><td class='data'>$date</td><td align='left' class='data'><span " . ($curgig ? "style='color: green'" : "") . ">$typeName</span></td>";
				
				if ($shouldAttend) $checked = 'checked';
				else $checked = '';
				$newval = ($shouldAttend + 1) % 2;
				if ($media == 'print') $eventRows .= "<td style='text-align: center' class='data'>" . ($shouldAttend ? "Y" : "N") . "</td>";
				else $eventRows .= "<td style='text-align: center' class='data'><input type='checkbox' class='attendbutton' data-mode='should' data-event='$eventNo' data-member='$memberID' data-val='$newval' $checked></td>";
				
				if ($didAttend) $checked = 'checked';
				else $checked = '';
				$newval = ($didAttend + 1) % 2;
				if ($media == 'print') $eventRows .= "<td style='text-align: center' class='data'>" . ($didAttend ? "Y" : "N") . "</td>";
				else $eventRows .= "<td style='text-align: center' class='data'><input type='checkbox' class='attendbutton' data-mode='did' data-event='$eventNo' data-member='$memberID' data-val='$newval' $checked></td>";

				if ($media == 'print') $eventRows .= "<td style='text-align: center'>$minutesLate</td>";
				else $eventRows .= "<td style='text-align: center'><input name='attendance-late' type='text' style='width:40px' value='$minutesLate'><button type='button' class='btn attendbutton' style='margin-top: -8px' data-mode='late' data-event='$eventNo' data-member='$memberID'>Go</button></td>";

				//make the point change red if it is negative
				if ($pointChange > 0) $eventRows .= "<td style='text-align: center' class='data' style='color: green'>";
				else if ($pointChange < 0) $eventRows .= "<td style='text-align: center'  class='data' style='color: red'>";
				else $eventRows .= "<td style='text-align: center' class='data'>";
				$eventRows .= "<a href='#' class='gradetip' data-toggle='tooltip' data-placement='right' style='color: inherit; text-decoration: none' onclick='return false' title='$tip'>$pointChange</a></td>";

				if ($pointChange != 0) $eventRows .= "<td style='text-align: center' class='data'>$score</td>";
				else $eventRows .= "<td style='text-align: center' class='data'></td>";

				$eventRows .= "</tr>";
			}
			else if ($mode == 2)
			{
				$eventRows .= "<tr align='center'><td><a href='#event:$eventNo'>$eventName</a></td><td>";
				if ($shouldAttend == "1") $eventRows .= "<i class='icon-ok'></i>";
				else $eventRows .= "<i class='icon-remove'></i>";
				$eventRows .= "</td><td>";
				if ($didAttend == "1") $eventRows .= "<i class='icon-ok'></i>";
				else $eventRows .= "<i class='icon-remove'></i>";
				$shouldAttend = ($shouldAttend == "0" ? "<i class='icon-remove'></i>" : "<i class='icon-ok'></i>");
				$eventRows .= "<td><a href='#' class='gradetip' data-toggle='tooltip' data-placement='right' style='color: inherit; text-decoration: none' onclick='return false' title='$tip'>$pointChange</a></td></tr>";
			}
			else if ($mode == 4)
			{
				$retarr[] = array("eventNo" => $eventNo, "name" => $eventName, "date" => (int) $call, "type" => $type, "shouldAttend" => ($shouldAttend > 0), "didAttend" => ($didAttend > 0), "late" => (int) $minutesLate, "pointChange" => $pointChange, "partialScore" => $score, "explanation" => $tip);
			}
		}
		if ($sectDiff != 0) die("Error: sectional offset was $sectDiff");
	}
	if ($mode == 3) return $gigcount;
	// Multiply the top half of the score by the fraction of volunteer gigs attended, if enabled
	$result = query("select `gigCheck` from `variables`", [], QONE);
	if (! $result) die("Could not retrieve variables");
	if ($result['gigCheck']) $score *= 0.5 + min(floatval($gigcount) * 0.5 / $gigreq, 0.5);
	// Bound the final score between 0 and 100
	if ($score > 100) $score = 100;
	if ($score < 0) $score = 0;
	$score = round($score, 2);
	if ($mode == 0) return $score;
	if ($mode == 4) return array("attendance" => $retarr, "finalScore" => $score, "gigCount" => $gigcount, "gigReq" => $gigreq);
	else return $tableOpen . $eventRows . $tableClose;
}

function rosterPropList($type)
{
	global $USER;
	$cols = array("#" => 10, "Name" => 260, "Section" => 80, "Contact" => 180, "Location" => 200);
	if (hasPermission("view-user-private-details"))
	{
		$cols["Enrollment"] = 40;
	}
	if (hasPermission("view-transactions"))
	{
		$cols["Balance"] = 60;
		$cols["Dues"] = 60;
		$cols["Tie"] = 40;
	}
	if (hasPermission("view-user-private-details"))
	{
		$cols["Gigs"] = 40;
		$cols["Score"] = 60;
	}
	if ($type == 'print')
	{
		unset($cols["Contact"]);
		unset($cols["Location"]);
		unset($cols["Balance"]);
	}
	return $cols;
}

function rosterProp($member, $prop)
{
	global $SEMESTER, $CHOIR;
	if (! $CHOIR) die("No choir selected");
	$html = '';
	switch ($prop)
	{
		case "Section":
			$section = query(
				"select `sectionType`.`name` from `sectionType`, `activeSemester` where `sectionType`.`id` = `activeSemester`.`section` and `activeSemester`.`choir` = ? and `activeSemester`.`semester` = ? and `activeSemester`.`member` = ?",
				[$CHOIR, $SEMESTER, $member["email"]], QONE
			);
			if (! $section) die("Failed to retrieve section");
			$html .= $section['name'];
			break;
		case "Contact":
			$html .= "<a href='tel:" . $member["phone"] . "'>" . $member["phone"] . "</a><br><a href='mailto:" . $member['email'] . "'>" . $member["email"] . "</a>";
			break;
		case "Location":
			$html .= $member["location"];
			break;
		case "Car":
			if ($member["passengers"] == 0) $html .= "No";
			else $html .= $member["passengers"] . " passengers";
			break;
		case "Enrollment":
			$enr = enrollment($member["email"]);
			if ($enr == "class") $html .= "<span style=\"color: blue\">class</span>";
			else if ($enr == "club") $html .= "club";
			else $html .= "<span style=\"color: gray\">inactive</span>";
			break;
		case "Balance":
			$balance = balance($member['email']);
			if ($balance < 0) $html .= "<span class='moneycell' style='color: red'>$balance</span>";
			else $html .= "<span class='moneycell'>$balance</span>";
			break;
		case "Dues":
			$balance = query("select sum(`amount`) as `balance` from `transaction` where `memberID` = ? and `type` = 'dues' and `semester` = ?", [$member["email"], $SEMESTER], QONE)["balance"];
			if ($balance == '') $balance = 0;
			if ($balance >= 0) $html .= "<span class='duescell' style='color: green'>$balance</span>";
			else $html .= "<span class='duescell' style='color: red'>$balance</span>";
			break;
		case "Gigs":
			$gigcount = attendance($member["email"], 3);
			$result = query("select `gigreq` from `semester` where `semester` = ?", [$SEMESTER], QONE);
			if (! $result) die("Invalid semester");
			$gigreq = $result['gigreq'];
			if ($gigcount >= $gigreq) $html .= "<span class='gigscell' style='color: green'>";
			else $html .= "<span class='gigscell' style='color: red'>";
			$html .= "$gigcount</span>";
			break;
		case "Score":
			if (enrollment($member["email"]) == 'inactive') $grade = "--";
			else $grade = attendance($member["email"], 0);
			$html .= "<span class='gradecell'";
			if (enrollment($member["email"]) == "class" && $grade < 80) $html .= " style=\"color: red\"";
			$html .= ">$grade</span>";
			break;
		case "Tie":
			$html .= "<span class='tiecell' ";
			$tieamount = query("select sum(`amount`) as `amount` from `transaction` where `memberID` = ? and `type` = 'deposit'", [$member["email"]], QONE)["amount"];
			if ($tieamount == '') $tieamount = 0;
			if ($tieamount >= fee("tie")) $html .= "style='color: green'";
			else $html .= "style='color: red'";
			$html .= ">";
			$result = query("select `tie` from `tieBorrow` where `member` = ? and `dateIn` is null", [$member["email"]], QONE);
			if ($result) $html .= $result['tie'];
			else $html .= "•";
			$html .= "</span>";
			break;
		default:
			$html .= "???";
			break;
	}
	return $html;
}
?>
