<?php
require_once('php/functions.php');

if ($_SERVER['HTTP_HOST'] != $domain) header("Location: $BASEURL");
$choirname = choirname($CHOIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="bootstrap/css/bootstrap.css" rel="stylesheet">
	<!--<script src=""https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js""></script> -->
	<script src="js/jquery-1.7.2.js"></script>
	<script src="js/jquery-ui-1.8.22.custom.min.js"></script>
	<script src="bootstrap/js/bootstrap.js"></script>
	<script src="js/bootstrap-datepicker.js"></script>
	<script src="js/tinymce/tinymce.min.js"></script>
	<link href="css/style.css" rel="stylesheet">
	<link href="css/datepicker.css" rel="stylesheet">

	<!-- Stuff for the tokenizer in messages -->
	<script type="text/javascript" src="css/token-js/src/jquery.tokeninput.js"></script>
	<link rel="stylesheet" type="text/css" href="css/token-js/styles/token-input.css" />
	<link rel="stylesheet" type="text/css" href="css/token-js/styles/token-input-facebook.css" />
	
	<script src="js/main.js"></script>
	<title>Gree-C-Web</title> <!-- Retro! -->
</head>
<body>
	<div class="container-fluid">
	<div class="row-fluid">
	<div class="navbar navbar-fixed-top navbar-inverse" style="font-size: 13px">
	<div class="navbar-inner">
	<div class="container">
		<ul class="nav">
			<li><a class="brand" href="index.php"><?php echo $choirname; ?></a></li>
			<li class="divider-vertical"></li>
			<?php if ($USER) { ?>
			<li class="dropdown">
				<a href="#" class="dropdown-toggle" data-toggle="dropdown">Events <b class="caret"></b></a>
				<ul class="dropdown-menu">
					<li><a href="#events">All</a></li>
					<?php foreach (eventTypes() as $id => $name) echo "<li><a href='#events:$id'>$name</a></li>"; ?>
					<?php if (hasPermission("create-event") || hasPermission("delete-event")) { ?>
						<li><a href="#event">Create/Delete</a></li>
					<?php } ?>
				</ul>
			</li>
			<li class="dropdown">
				<a href="#" class="dropdown-toggle" data-toggle="dropdown">Actions <b class="caret"></b></a>
				<ul class="dropdown-menu">
					<li><a href="#feedback">Feedback</a></li>
					<li><a href="#suggestSong">Suggest a song</a></li>
					<li><a href="#roster">Members</a></li>
					<?php if ($USER && $CHOIR)
					{
						$officerOptions = '';
						if (hasPermission("process-gig-requests")) $officerOptions .= '<li><a href="#gigreqs">Gig Requests</a></li>';
						if (hasPermission("edit-announcements")) $officerOptions .= '<li><a href="#addAnnouncement">Make an Announcement</a></li>';
						if (hasPermission("edit-transaction")) $officerOptions .= '<li><a href="#money">Add Transactions</a></li>';
						if (hasPermission("process-absence-requests")) $officerOptions .= '<li><a href="#absenceRequest">Absence Requests</a></li>';
						if (hasPermission("edit-semester")) $officerOptions .= '<li><a href="#semester">Edit Semester</a></li><li><a href="#timeMachine">Past Semesters</a></li>';
						if (hasPosition($USER, "President") || hasPosition($USER, "Webmaster")) $officerOptions .= '<li><a href="#settings">Site Settings</a></li>';
						echo $officerOptions;
					}?>
				</ul>
			</li>
			<?php } if ($CHOIR) { ?>
			<li class="dropdown">
				<a href="#" class="dropdown-toggle" data-toggle="dropdown">Documents <b class="caret"></b></a>
				<ul class="dropdown-menu">
					<?php if ($USER) { ?><li><a href="#repertoire">Repertoire</a></li><?php } ?>
					<li><a href="#minutes">Meeting Minutes</a></li>
					<li class="divider"><li>
					<?php foreach (query("select * from `gdocs` where `choir` = ?", [$CHOIR], QALL) as $row) echo "<li><a href='#doc:" . $row['name'] . "'>" . $row['name'] . "</a></li>"; ?>
				</ul>
			</li>
		</ul>
		<ul class="nav pull-right">
			<?php } if ($USER) { ?>
			<li>
				<form class="navbar-search pull-left">
					<input type="text" class="search-query" data-provide="typeahead"  data-items="4" data-source='["Taylor","Drew","Tot"]'>
				</form>
			</li>
			<li class="divider-vertical"></li>
			<li class="dropdown">
				<a href="#" class="dropdown-toggle" data-toggle="dropdown"> <?php echo getuser(); ?> <b class="caret"></b></a>
				<ul class="dropdown-menu">
					<li><a href="#editProfile">My Profile</a></li>
					<li><a href="php/logOut.php">Log Out</a></li>
					<li class="divider"><li>
					<?php foreach (choirs() as $id => $name) echo "<li><a href='#' onclick='setChoir(\"$id\")' style='" . ($id == $CHOIR ? "font-weight: bold" : "") . "'>$name</a></li>" ?>
				</ul>
			</li>
		<?php } ?>
		</ul>
	</div>
	</div>
	</div>
	<div class="span11 block" id="main" style='margin-bottom: 100px'></div>
	</div>
	</div>
	
	<?php
		if ($CHOIR && $USER != "")
		{
			$arr = query("select `location` from `member` where `email` = ?", [$USER], QONE);
			if (! $arr) err("Invalid user");
			$confirmed = query("select `member` from `activeSemester` where `member` = ? and `semester` = ? and `choir` = ?", [$USER, $SEMESTER, $CHOIR], QCOUNT) > 0;
			if (! $confirmed)
			{
				?>
				<div class="modal hide fade" id='confirmModal'>
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal">×</button>
						<h3>Confirm your account!</h3>
					</div>
					<div class="modal-body">
						<p>Are you in <?php echo $choirname; ?> this semester?  If not, hit Close and you will still be able to view the site, but you won't be assessed dues or expected at events.  If you are returning, please verify the information below, then hit Confirm to confirm your account.</p>
					<form class="form-horizontal">
						<div class="control-group">
						<label class="control-label" style='font-weight: bold'>Registration:</label>
						<div class="controls"><div class="btn-group" data-toggle="buttons-radio"><button type="button" class="btn" id="confirm_class">Class</button><button type="button" class="btn" id="confirm_club">Club</button></div></div>
						</div>
						<div class="control-group">
						<label class="control-label" style='font-weight: bold'>Location:</label>
						<div class="controls"><input type="text" id="confirm_location"></div>
						</div>
						<div class="control-group">
						<label class="control-label" style='font-weight: bold'>Section:</label>
						<div class="controls"><?php echo dropdown(sections(), "section"); ?></div>
						</div>
					</form></div>
					<div class="modal-footer">
						<a href="#" class="btn" style="color: inherit" data-dismiss="modal">Close</a>
						<a href="#" class="btn btn-primary" style="color: inherit" onclick="confirm_account()">Confirm</a>
					</div>
				</div>
				<?php
				$loc = addslashes($arr["location"]);
				echo '<script>
					$("#confirm_location").prop("value", "' . $loc . '");
					$("#confirmModal").modal();
				</script>';
			}
		}
	?>
</body>
</html>
