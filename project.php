<?php

##########################################################################
# Copyright 2013, Philip Ewels (phil.ewels@babraham.ac.uk)               #
#                                                                        #
# This file is part of Labrador.                                         #
#                                                                        #
# Labrador is free software: you can redistribute it and/or modify       #
# it under the terms of the GNU General Public License as published by   #
# the Free Software Foundation, either version 3 of the License, or      #
# (at your option) any later version.                                    #
#                                                                        #
# Labrador is distributed in the hope that it will be useful,            #
# but WITHOUT ANY WARRANTY; without even the implied warranty of         #
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the          #
# GNU General Public License for more details.                           #
#                                                                        #
# You should have received a copy of the GNU General Public License      #
# along with Labrador.  If not, see <http://www.gnu.org/licenses/>.      #
##########################################################################


include('includes/start.php');

$project_id = false;
$stop_page_after_message = false;

if(isset($_GET['id']) && is_numeric($_GET['id'])){
	$new_project = false;
	$project_id = $_GET['id'];
} else {
	$new_project = true;
}

if(isset($_GET['p_name'])){
	$sql = sprintf("SELECT `id` FROM `projects` WHERE `name` = '%s'", mysqli_real_escape_string($dblink, $_GET['p_name']));
	$project_q = mysqli_query($dblink, $sql);
	if(mysqli_num_rows($project_q) > 0 ){
		$project = mysqli_fetch_array($project_q);
		$_GET['id'] = $project['id'];
		$new_project = false;
		$project_id = $project['id'];
	} else {
		$new_project = true;
		echo $sql;
	}
}

if(isset($_GET['edit']) && is_numeric($_GET['edit'])){
	$edit = true;
	$new_project = false;
	$project_id = $_GET['edit'];
} else {
	$edit = false;
}

if(isset($_GET['delete']) && is_numeric($_GET['delete']) && $admin){
	$delete = true;
	$new_project = false;
	$edit = false;
	$project_id = $_GET['delete'];
} else {
	$delete = false;
}

if($project_id){
	$projects = mysqli_query($dblink, "SELECT * FROM `projects` WHERE `id` = '".$project_id."' LIMIT 1");
	$project_users = array();
	if(mysqli_num_rows($projects) > 0){
		$project = mysqli_fetch_array($projects);
		$project_users_q = mysqli_query($dblink, "SELECT `user_id` FROM `project_contacts` WHERE `project_id` = '$project_id'");
		if(mysqli_num_rows($project_users_q) > 0){
			while($project_user = mysqli_fetch_array($project_users_q)){
				$project_users[] = $project_user['user_id'];
			}
		}
	} else {
		$new_project = true;
		$edit = false;
		$project_id = false;
		$error = true;
		$msg[] = "Could not find project ID. This may be an error in the URL or it could have been deleted.";
		$stop_page_after_message = true;
	}
}

if(isset($_GET['adduser']) && $project_id && $user && !$admin){
	if(!in_array($user['id'], $project_users)){
		$query = sprintf("INSERT INTO `project_contacts` (`project_id`, `user_id`) VALUES ('%d', '%d')", $project_id, $user['id']);
		if(!mysqli_query($dblink, $query)){
			$error = true;
			$msg[] = "Could not add user as project contact. mySQL error: <code>".mysqli_error()."</code><br>mySQL query: <code>$query</code>";
		} else {
			$project_users[] = $user['id'];
		}
	}
}
if(isset($_GET['removeuser']) && $project_id && $user && !$admin){
	if(in_array($user['id'], $project_users)){
		$query = sprintf("DELETE FROM `project_contacts` WHERE `project_id` = '%d' AND `user_id` = '%d'", $project_id, $user['id']);
		if(!mysqli_query($dblink, $query)){
			$error = true;
			$msg[] = "Could not remove user as project contact. mySQL error: <code>".mysqli_error()."</code><br>mySQL query: <code>$query</code>";
		} else {
			if(($key = array_search($user['id'], $project_users)) !== false) {
				unset($project_users[$key]);
			}
		}
	}
}

if(isset($_GET['edit']) && is_numeric($_GET['edit']) && !$admin && !in_array($user['id'], $project_users)){
	$new_project = false;
	$edit = false;
	header("Location: index.php");
}

///////
// SAVE SUBMITTED FORM
///////
if($user && isset($_POST['save_project']) && $_POST['save_project'] == 'Save Project'){

	if(!isset($project_id) || !is_numeric($project_id)){
		$new_project = true;
	} else {
		$new_project = false;
	}

	// Collect and validate the submitted input
	$error = false;
	$msg = array();
	$values = array (
		"name" => preg_replace("/[^A-Za-z0-9_]/", '_', $_POST['name']),
		"accession_geo" => $_POST['accession_geo'],
		"accession_sra" => $_POST['accession_sra'],
		"accession_ena" => $_POST['accession_ena'],
		"accession_ddjb" => $_POST['accession_ddjb'],
		"title" => $_POST['title'],
		"description" => $_POST['description'],
		"notes" => $_POST['notes']
	);
	if($admin){
		$values["status"] = $_POST['status'];
		$values["assigned_to"] = filter_var($_POST['assigned_to'], FILTER_SANITIZE_EMAIL);
		$contacts = $_POST['contacts'];
	} else {
		$contacts = $project_users;
		if(!in_array($user['id'], $contacts)){
			$contacts[] = $user['id'];
		}
	}

	if(strlen($values['name']) == 0){
		$error = true;
		$msg[] = "Project Identifier cannot be blank";
	}

	// Passed validation - save project
	if(!$error){
		if($new_project){
			$query = "INSERT INTO `projects` (";
			foreach($values as $id => $var) {
				$query .= "`$id`, ";
			}
			$query = substr($query, 0, -2) . ") VALUES (";
			foreach($values as $id => $var) {
				$query .= "'".mysqli_real_escape_string($dblink, $var)."', ";
			}
			$query = substr($query, 0, -2) . ")";
			$history = "Created project.";
		} else {
			$query = "UPDATE `projects` SET ";
			foreach($values as $id => $var) {
				$query .= "`$id` = '".mysqli_real_escape_string($dblink, $var)."', ";
			}
			$query = substr($query, 0, -2) . " WHERE `id` = '$project_id'";
			$history = "Edited project.";
		}
		if(mysqli_query($dblink, $query)){
			$project_array = $values;

			// Saved project. What is its ID?
			if(!isset($project_id) || !is_numeric($project_id)){
				$project_id = mysqli_insert_id($dblink);
				$project = $values;
			}

			// Saved project - now save contacts
			$contacts = array_unique($contacts);

			// We can end up with blank values in here so remove those
			$contacts = array_filter($contacts);
			$insert_contacts = $contacts;
			$contacts_q = mysqli_query($dblink, "SELECT `id`, `user_id` FROM `project_contacts` WHERE `project_id` = '$project_id'");
			if(mysqli_num_rows($contacts_q) > 0){
				while($contacts_e = mysqli_fetch_array($contacts_q)){
					if(($key = array_search($contacts_e['user_id'], $insert_contacts)) !== false) {
						// already here. no need to insert again
						unset($insert_contacts[$key]);
					} else {
						// Not in submitted form. Delete from database.
						$query = "DELETE FROM `project_contacts` WHERE `id` = '".$contacts_e['id']."'";
						if(!mysqli_query($dblink, $query)){
							$error = true;
							$msg[] = "Could not delete contact from database. mySQL error: <code>".mysqli_error()."</code><br>mySQL query: <code>$query</code>";
						}
					}
				}
			}
			// Insert remaining contacts into database
			foreach($insert_contacts as $uid){
				$query = "INSERT INTO `project_contacts` (`project_id`, `user_id`) VALUES ('$project_id', '$uid')";
				if(!mysqli_query($dblink, $query)){
					$error = true;
					$msg[] = "Could not insert contact into database. mySQL error: <code>".mysqli_error()."</code><br>mySQL query: <code>$query</code>";
				}
			}


			// Saved project and contacts - now save papers
			if($project_id > 0){
				$i = 1;
				while(isset($_POST['paper_year_'.$i])){
					// Collect variables
					$values = array (
						"project_id" => $project_id,
						"year" => $_POST['paper_year_'.$i],
						"journal" => $_POST['paper_journal_'.$i],
						"title" => $_POST['paper_title_'.$i],
						"authors" => $_POST['paper_authors_'.$i],
						"pmid" => $_POST['paper_pmid_'.$i],
						"doi" => $_POST['paper_doi_'.$i],
					);
					// Build mysql queries
					if(isset($_POST['paper_id_'.$i]) && !empty($_POST['paper_id_'.$i])){
						$query = "UPDATE `papers` SET ";
						foreach($values as $id => $var) {
							$query .= "`$id` = '".mysqli_real_escape_string($dblink, $var)."', ";
						}
						$query = substr($query, 0, -2) . " WHERE `id` = '".$_POST['paper_id_'.$i]."'";
					} else {
						$query = "INSERT INTO `papers` (";
						foreach($values as $id => $var) {
							$query .= "`$id`, ";
						}
						$query = substr($query, 0, -2) . ") VALUES (";
						foreach($values as $id => $var) {
							$query .= "'".mysqli_real_escape_string($dblink, $var)."', ";
						}
						$query = substr($query, 0, -2) . ")";
					}
					// Save to database
					if(!mysqli_query($dblink, $query)){
						$error = true;
						$msg[] = "Could not save paper to database. mySQL error: <code>".mysqli_error()."</code><br>mySQL query: <code>$query</code>";
					}
					// increment counter
					$i++;
				}

				// Save history message
				$query = sprintf("INSERT INTO `history` (`project_id`, `user_id`, `note`, `time`) VALUES ('%d', '%d', '%s', '%d')", $project_id, $user['id'], mysqli_real_escape_string($dblink, $history), time());
				if(!mysqli_query($dblink, $query)){
					$error = true;
					$msg[] = "Could not save history log to database. mySQL error: <code>".mysqli_error()."</code><br>mySQL query: <code>$query</code>";
				}

				// Success!
				if(isset($_GET['id']) && is_numeric($_GET['id'])){
					$msg[] = '<strong>Successfully saved project.</strong> &nbsp; <a href="project.php?id='.$project_id.'">View project</a>.';
				} else {
					$msg[] = '<strong>Successfully saved project.</strong> &nbsp; <a href="datasets.php?add='.$project_id.'">Add datasets</a>.';
				}
				$stop_page_after_message = true;

				// Email project contacts
				$project_contacts = array();
				foreach($contacts as $contact){
					$query = sprintf("SELECT `email`, `firstname`, `surname` FROM `users` WHERE `id` = '%d'", $contact);
					$contact_u = mysqli_fetch_array(mysqli_query($dblink, $query));
					$project_contacts[] = $contact_u['firstname'].' '.$contact_u['surname'].' ('.$contact_u['email'].')';
					if($new_project && !($admin && $contact_u['email'] == $user['email'])){
						mail($contact_u['email'], '[Labrador] Project '.$project_array['name'].' Created', "Hi there,

The project ".$project_array['name']." has just been created on Labrador and you are marked as a contact. As such, you will receive e-mail notifications if the status of the project is updated.

You can see the project here: ".$labrador_url."project.php?id=$project_id

If you have any queries, please e-mail $support_email

--
This is an automated e-mail sent from Labrador
$labrador_url
", $email_headers);
					} else if($admin && $contact_u['email'] != $user['email']) {
						mail($contact_u['email'], '[Labrador] Project '.$project_array['name'].' Updated', "Hi there,

The project ".$project_array['name']." has just been updated on Labrador. Its status is now '".$project_array['status']."'

You can see the project here: ".$labrador_url."project.php?id=$project_id

If you have any queries, please e-mail $support_email

--
This is an automated e-mail sent from Labrador
$labrador_url
", $email_headers);
					}
				}

				// Email support if no-one is assigned
				if(strlen($project_array['assigned_to']) < 3 && $new_project && !$admin){
					mail($support_email, '[Labrador] Project '.$project_array['name'].' Created', "Hi there,

The project ".$project_array['name']." has just been created on Labrador by ".implode(", ", $project_contacts).". It doesn't have anyone assigned to the project yet.

You can see the project here: ".$labrador_url."project.php?id=$project_id

--
This is an automated e-mail sent from Labrador
$labrador_url
", $email_headers);
				}

			} else {
				$error = true;
				$msg[] = "Saved project to database but couldn't find inserted ID so didn't save papers.";
			}
		} else {
			$error = true;
			$msg[] = "Could not save project to database. mySQL error: <code>".mysqli_error()."</code><br>mySQL query: <code>$query</code>";
		}
	}

}

///////
// DELETE PROJECT
///////
if($delete && $project_id){
	// Go through and delete everything else which refers to project_id
	$query = sprintf("DELETE FROM `processing` WHERE `project_id` = '%d'", $project_id);
	if(mysqli_query($dblink, $query)){
		$s = mysqli_affected_rows($dblink) > 1 ? 's' : '';
		$msg[] = mysqli_affected_rows($dblink). " processing record$s deleted";
	} else {
		$error = true;
		$msg[] = "Could not delete processing records: ".mysqli_error();
	}

	$query = sprintf("DELETE FROM `datasets` WHERE `project_id` = '%d'", $project_id);
	if(mysqli_query($dblink, $query)){
		$s = mysqli_affected_rows($dblink) > 1 ? 's' : '';
		$msg[] = mysqli_affected_rows($dblink). " dataset$s deleted";
	} else {
		$error = true;
		$msg[] = "Could not delete datasets: ".mysqli_error();
	}

	$query = sprintf("DELETE FROM `project_contacts` WHERE `project_id` = '%d'", $project_id);
	if(mysqli_query($dblink, $query)){
		$s = mysqli_affected_rows($dblink) > 1 ? 's' : '';
		$msg[] = mysqli_affected_rows($dblink). " project contact$s deleted";
	} else {
		$error = true;
		$msg[] = "Could not delete project contacts: ".mysqli_error();
	}

	$query = sprintf("DELETE FROM `papers` WHERE `project_id` = '%d'", $project_id);
	if(mysqli_query($dblink, $query)){
		$s = mysqli_affected_rows($dblink) > 1 ? 's' : '';
		$msg[] = mysqli_affected_rows($dblink). " paper$s deleted";
	} else {
		$error = true;
		$msg[] = "Could not delete papers: ".mysqli_error();
	}

	$query = sprintf("DELETE FROM `history` WHERE `project_id` = '%d'", $project_id);
	if(mysqli_query($dblink, $query)){
		$s = mysqli_affected_rows($dblink) > 1 ? 's' : '';
		$msg[] = mysqli_affected_rows($dblink). " history log$s deleted";
	} else {
		$error = true;
		$msg[] = "Could not delete history logs: ".mysqli_error();
	}

	$query = sprintf("DELETE FROM `projects` WHERE `id` = '%d'", $project_id);
	if(mysqli_query($dblink, $query)){
		$msg[] = "Project deleted";
	} else {
		$error = true;
		$msg[] = "Could not delete project: ".mysqli_error();
	}

	$stop_page_after_message = true;
}



include('includes/header.php'); ?>

<div class="sidebar-nav">
	<h3 id="sidebar_project_title">
	<?php if($project_id){
		echo '<a href="project.php?id='.$project_id.'">'.$project['name'].'</a>';
	} else {
		echo '<span class="muted">New Project</span>';
	}?></h3>
	<ul class="project-tabs">
	<?php if($new_project){ ?>
		<li class="active">
			<a href="project.php">Project Details</a>
		</li>
		<li class="inactive">
			<a href="#" class="fake_link">Datasets</a>
		</li>
		<li class="inactive">
			<a href="#" class="fake_link">Reports</a>
		</li>
		<li class="inactive">
			<a href="#" class="fake_link">Files</a>
		</li>
	<?php } else { ?>
		<li class="active">
			<a href="project.php?id=<?php echo $project_id; ?>">Project Details</a>
		</li>
		<li>
			<a href="datasets.php?id=<?php echo $project_id; ?>">Datasets</a>
		</li>
		<li>
			<a href="reports.php?id=<?php echo $project_id; ?>">Reports</a>
		</li>
		<li>
			<a href="files.php?id=<?php echo $project_id; ?>">Files</a>
		</li>
	<?php } ?>
	</ul>
</div>

<?php
///////
// VIEW EXISTING PROJECT DETAILS
///////

if(!$new_project and !$edit and !$error){ ?>

<div class="sidebar-mainpage project-mainpage">

	<?php // if(!empty($_POST)) { echo '<pre>'.print_r($_POST, true).'</pre>'; } ?>
	<?php if(!empty($msg)): ?>
		<div class="alert alert-<?php echo $error ? 'error' : 'success'; ?>">
			<button type="button" class="close" data-dismiss="alert">×</button>
			<?php echo $error ? '<strong>Error!</strong><br>' : ''; ?>
			<?php foreach($msg as $var)	echo $var.'<br>'; ?>
		</div>
	<?php endif; ?>

	<?php // End page if it's a terminal message
	if($stop_page_after_message){ ?>
		</div>
		<?php include('includes/javascript.php'); ?>
		<script src="js/project.js" type="text/javascript"></script>
		<?php include('includes/footer.php');
		exit;
	 } ?>



	<?php
	if(!$new_project && ($admin || in_array($user['id'], $project_users))) {	?>
		<a style="float:right;" class="btn" href="project.php?edit=<?php echo $project['id']; ?>">Edit Project</a>
	<?php } else if($user){ ?>
		<a style="float:right;" class="btn" href="project.php?id=<?php echo $project['id']; ?>&adduser">Add me as a contact</a>
	<?php }
	if(!$new_project && !$admin && in_array($user['id'], $project_users) && count($project_users) > 1) { ?>
		<a style="margin-right:15px; float:right;style="margin-right:15px;" " class="btn" href="project.php?id=<?php echo $project['id']; ?>&removeuser">Remove me as a contact</a>
	<?php }	?>

	<a class="labrador_help_toggle pull-right" href="#labrador_help" title="Help"><i class="icon-question-sign"></i></a>
	<?php project_header($project); ?>

	<div class="labrador_help" style="display:none;">
		<div class="well">
			<h3>The Project Page</h3>
			<p>Projects are the base of Labrador - each project has a unique name which should correspond to a directory held on the server.</p>
			<p>This page holds information about a specific project.</p>
			<dl class="dl-horizontal">
				<dt>Name</dt>
				<dd>Project Name - corresponds to a directory on the server <em>(required)</em></dd>

				<dt>Assigned To</dt>
				<dd>The e-mail address of the bioinformatician that the project has been assigned to</dd>

				<dt>Status</dt>
				<dd>The current status of the project <em>(Not Started / Processing / Complete)</em></dd>

				<dt>Primary Contact</dt>
				<dd>The name of the person who requested or generated the data</dd>

				<dt>Contact E-mail</dt>
				<dd>E-mail address of the primary contact</dd>

				<dt>Group</dt>
				<dd>Group of the primary contact</dd>
			</dl>

			<dl class="dl-horizontal">
				<dt>GEO Accession</dt>
				<dd><a href="http://www.ncbi.nlm.nih.gov/geo/" target="_blank">NCBI Gene Expression Omnibus</a> accession. Should be the accession for the project not a single dataset <em>(starting GSE, not GSM)</em></dd>

				<dt>SRA Accession</dt>
				<dd><a href="http://www.ncbi.nlm.nih.gov/sra" target="_blank">NCBI Sequence Read Archive</a> accession. Should be the accession for the project not a single dataset <em>(starting SRP, not SRR)</em></dd>

				<dt>ENA Accession</dt>
				<dd><a href="http://www.ebi.ac.uk/ena/" target="_blank">EBI European Nucleotide Archive</a> accession.</dd>

				<dt>DDJB Accession</dt>
				<dd><a href="http://www.ddbj.nig.ac.jp/" target="_blank">DNA Data Bank of Japan</a> accession.</dd>

				<dt>PMID Accession</dt>
				<dd><a href="http://www.ncbi.nlm.nih.gov/pubmed/" target="_blank">NCBI PubMed</a> accession. Used to automatically retrieve papers.</dd>
			</dl>

			<dl class="dl-horizontal">
				<dt>Publications</dt>
				<dd>Multiple publications can be added for each project. These can be searched using the search bar at the top.</dd>
			</dl>

			<dl class="dl-horizontal">
				<dt>Project Title</dt>
				<dd>Long title, can be automatically filled from GEO accession.</dd>

				<dt>Project Description</dt>
				<dd>Long description, can be automatically filled from GEO accession.</dd>

				<dt>Comments</dt>
				<dd>Any notes about the project.</dd>

				<dt>History</dt>
				<dd>A log of events that have happened with Labrador related to the project.</dd>
			</dl>
		<p>You can read the full <a href="<?php echo $labrador_url; ?>/documentation/">Labrador documenation here</a>.</p>
		</div>
	</div>

	<?php $papers = mysqli_query($dblink, "SELECT * from `papers` WHERE `project_id` = '".$project['id']."'");
	if(mysqli_num_rows($papers) > 0) { ?>
	<fieldset id="project_paper_fieldset">
		<legend>Publications</legend>
		<table class="table">
			<thead>
				<th>Year</th>
				<th>Journal</th>
				<th>Title</th>
				<th>Authors</th>
				<th>PMID</th>
				<th>DOI</th>
			<thead>
			<tbody>
			<?php while($paper = mysqli_fetch_array($papers)){
				echo '<tr>';
				echo '<td>'.$paper['year'].'</td>';
				echo '<td>'.$paper['journal'].'</td>';
				echo '<td>'.$paper['title'].'</td>';
				echo '<td>'.$paper['authors'].'</td>';
				echo '<td><a href="http://www.ncbi.nlm.nih.gov/pubmed/'.$paper['pmid'].'" target="_blank">'.$paper['pmid'].'</a></td>';
				echo '<td><a href="http://dx.doi.org/'.$paper['doi'].'" target="_blank">'.$paper['doi'].'</a></td>';
				echo '</tr>';
			} // while ?>
			</tbody>
		</table>
	</fieldset>
	<?php } // > 0 papers


	if(!empty($project['title'])){ ?>
	<fieldset>
		<legend>Title &amp; Description</legend>
		<p><?php echo nl2br(stripslashes($project['title'])); ?></p>
		<?php if(!empty($project['description'])){ ?>
		<p><small class="muted"><?php echo nl2br(stripslashes($project['description'])); ?></small></p>
		<?php } ?>
	</fieldset>
	<?php } // has title

	if(!empty($project['notes'])){ ?>
	<fieldset>
		<legend>Comments</legend>
		<p><?php echo nl2br(stripslashes($project['notes'])); ?></p>
	</fieldset>
	<?php } // has notes


	$p_users = mysqli_query($dblink, "SELECT `users`.`email`, `users`.`firstname`, `users`.`surname`, `users`.`group` FROM `users` LEFT JOIN `project_contacts` on `users`.`id` = `project_contacts`.`user_id` WHERE `project_contacts`.`project_id` = '".$project['id']."'");
	if(mysqli_num_rows($p_users) > 0){
		?>
	<fieldset>
		<legend>Contacts</legend>
		<dl>
		<?php
		while($p_user = mysqli_fetch_array($p_users)){
			echo '<dt>'.$p_user['firstname'].' '.$p_user['surname'].'</dt>';
			echo '<dd><a href="mailto:'.$p_user['email'].'">'.$p_user['email'].'</a> &nbsp; <small><em>(Group - '.$p_user['group'].')</small></em></dd>';
		}
		?>
		</dl>
	</fieldset>
	<?php }

	// HISTORY LOG
	$histories = mysqli_query($dblink, "SELECT * FROM `history` WHERE `project_id` = '$project_id' ORDER BY `time` DESC");
	if(mysqli_num_rows($histories) > 0){	?>

	<fieldset>
		<legend>History</legend>
		<dl class="dl-horizontal muted">
			<?php while($history = mysqli_fetch_array($histories)){
				$history_user = mysqli_fetch_array(mysqli_query($dblink, "SELECT * FROM `users` WHERE `id` = '".$history['user_id']."'")); ?>
			<dt><small><?php echo date("j/m/Y, G:i", $history['time']); ?></small></dt>
			<dd><small><a href="mailto:<?php echo $history_user['email']; ?>"><?php echo $history_user['firstname'].' '.$history_user['surname']; ?></a> - <?php echo $history['note']; ?></small></dd>
			<?php } ?>
		</dl>
	</fieldset>

	<?php } ?>

</div>

<?php
///////
// ADD OR EDIT A PROJECT
///////

} else if($user){

	$names = array ("Chuck Norris", "Albert Einstein", "Charles Darwin", "George Martin", "Galileo Galilei", "Barack Obama", "Margaret Thatcher",
					"Jean-Claude Van Damme", "Isaac Newton", "Darth Vader", "William Shatner", "Dolly Parton", "David Hasselhoff", "Mr T", "B. A. Baracus", "MC Hammer",
					"Daenerys Targaryen", "Tin Tin", "James Bond", "Indiana Jones", "Alex Ferguson", "Lord Nelson", "Leonardo da Vinci", "Clark Kent", "Yoda",
					"Miss Moneypenny", "Harry Houdini", "Edmund Blackadder", "Hannibal Lector", "Evel Knievel", "Dr Evil", "Neil Armstrong", "Alan Partridge",
					"John Lennon", "Marilyn Monroe", "Elvis Presley", "Michael Corleone", "Napoleon Bonaparte", "Marie Antoinette", "Oliver Cromwell", "Flash Gordon",
					"Kermit the Frog", "Thom Yorke", "George Clooney", "Homer Simpson", "Harry Potter", "Sherlock Holmes", "Bilbo Baggins", "Julius Caesar", "Bruce Lee",
					"Michael Jackson", "Freddy Mercury", "Winne the Pooh");
	$name = $names[array_rand($names)];

	$values = array (
		"name" => "",
		"status" => ($user && $admin) ? "Currently Processing" : "",
		"assigned_to" => ($user && $admin) ? $user['email'] : "",
		"project_users" => array(-1),
		"accession_geo" => "",
		"accession_sra" => "",
		"accession_ena" => "",
		"accession_ddjb" => "",
		"title" => "",
		"description" => "",
		"notes" => ""
	);
	$papers = array();

	if($edit){
		$values = array (
			"name" => $project['name'],
			"status" => $project['status'],
			"assigned_to" => $project['assigned_to'],
			"project_users" => $project_users,
			"accession_geo" => $project['accession_geo'],
			"accession_sra" => $project['accession_sra'],
			"accession_ena" => $project['accession_ena'],
			"accession_ddjb" => $project['accession_ddjb'],
			"title" => $project['title'],
			"description" => $project['description'],
			"notes" => $project['notes']
		);
		if(count($values['project_users']) < 1){
			$values['project_users'] = array(-1);
		}
	}

	if($error) {
		$values = array (
			"name" => $_POST['name'],
			"status" => $_POST['status'],
			"assigned_to" => $_POST['assigned_to'],
			"project_users" => $_POST['project_users'],
			"accession_geo" => $_POST['accession_geo'],
			"accession_sra" => $_POST['accession_sra'],
			"accession_ena" => $_POST['accession_ena'],
			"accession_ddjb" => $_POST['accession_ddjb'],
			"title" => $_POST['title'],
			"description" => $_POST['description'],
			"notes" => $_POST['notes']
		);
	}

?>



<div class="sidebar-mainpage project-mainpage">
	<form action="project.php<?php if($edit){ echo '?id='.$project_id; } ?>" method="post" class="form-horizontal add_edit_project form_validate">

		<?php if(!empty($msg)): ?>
			<div class="alert alert-<?php echo $error ? 'error' : 'success'; ?>">
				<button type="button" class="close" data-dismiss="alert">×</button>
				<?php echo $error ? '<strong>Error!</strong><br>' : ''; ?>
				<?php foreach($msg as $var)	echo $var.'<br>'; ?>
			</div>
		<?php endif; ?>

		<?php // End page if it's a terminal message
		if($stop_page_after_message){ ?>
			</div>
			<?php include('includes/javascript.php'); ?>
			<script src="js/project.js" type="text/javascript"></script>
			<?php include('includes/footer.php');
			exit;
		 } ?>

		<input style="float:right;" type="submit" class="btn btn-primary" name="save_project" id="save_project" value="Save Project">

		<fieldset>
			<legend>Project Identifier</legend>
			<p>Every project needs a unique identifier. For an external project, this is typically the first author's surname and year, <em>eg.</em> <code>Ewels_2013</code>. Numbers, letters and underscores only.</p>
			<p><input type="text" id="name" name="name" maxlength="255" required placeholder="Surname_<?php echo date("Y"); ?>" value="<?php echo $values['name']; ?>"></p>
			<p>All of the remaining fields are optional.</p>
		</fieldset>

	<?php if($admin){ ?>
		<fieldset id="project_status_fieldset">
			<legend>Project Contacts</legend>
			<div class="control-group">
				<label class="control-label" for="assigned_to">Assigned To</label>
				<div class="controls">
					<input type="email" id="assigned_to" name="assigned_to" maxlength="250" placeholder="<?php echo preg_replace('/\s+/', '.', strtolower($names[array_rand($names)])); ?>@babraham.ac.uk" value="<?php echo $values['assigned_to']; ?>" />
					<span class="help-inline">Who is processing the data? <small> &nbsp;
						<?php foreach($administrators as $qf_email => $qf_name){
							echo ' / <a href="#" class="assign_quickFill" title="'.$qf_email.'">'.$qf_name.'</a>';
						} ?>
					</small></span>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label" for="status">Status</label>
				<div class="controls">
					<select id="status" name="status" autofocus>
						<option <?php if($values['status'] == 'Not Started'){ echo 'selected="selected"'; } ?>>Not Started</option>
						<option <?php if($values['status'] == 'Currently Processing'){ echo 'selected="selected"'; } ?>>Currently Processing</option>
						<option <?php if($values['status'] == 'Processing Complete'){ echo 'selected="selected"'; } ?>>Processing Complete</option>
					</select>
				</div>
			</div>


			<div class="control-group contacts-control-group">
				<label class="control-label">Contacts</label>
				<div class="controls">
					<?php $first = true;
					foreach($values['project_users'] as $id){
						if(!$first){ echo '<br>'; }
						$first = false;?>
					<select name="contacts[]" class="contacts_dropdown">
						<option value=""> [ select user ] </option>
						<?php
						$allusers_query = mysqli_query($dblink, "SELECT `id`, `firstname`, `surname` FROM `users` ORDER BY `firstname` ASC");
						if(mysqli_num_rows($allusers_query) > 0){
							while($alluser = mysqli_fetch_array($allusers_query)){
								echo '<option value="'.$alluser['id'].'"';
								if($alluser['id'] == $id){
									echo ' selected="selected"';
								}
								echo '>'.$alluser['firstname'].' '.$alluser['surname'].'</option>';
							}
						}
					?>
					</select>
					<?php
					} ?>
					<span class="help-inline">Who requested / generated the data?</span>
					&nbsp; <a href="#" id="project_add_contact" class="btn btn-small">Add</a>
					&nbsp; <a href="#" id="project_remove_contact" class="btn btn-small <?php if(count($values['project_users']) < 2){ echo 'disabled'; } ?>">Remove</a>
				</div>
			</div>

		</fieldset>
	<?php } // if is admin ?>

		<fieldset id="project_accessions_fieldset">
			<legend>Accessions</legend>
			<p>External projects can have multiple accession numbers associated with them. If you click a magnifying glass, Labrador will try to fill in empty fields elsewhere using these.</p>
			<p>Multiple accessions can be entered, separated by spaces. When auto-completing, fields will be filled in order of accessions.</p>
			<div class="control-group">
				<label class="control-label" for="accession_geo"><abbr title="Gene Expression Omnibus">GEO</abbr></label>
				<div class="controls">
					<input type="text" name="accession_geo" id="accession_geo" maxlength="50" placeholder="GSE000000" value="<?php echo $values['accession_geo']; ?>">
					<span class="help-inline"><a href="#" id="geo_lookup" title="Auto-complete empty fields"><i class="icon-search"></i></a></span>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label" for="accession_sra"><abbr title="Sequence Read Archive">SRA</abbr></label>
				<div class="controls">
					<input type="text" name="accession_sra" id="accession_sra" maxlength="50" placeholder="SRP000000" value="<?php echo $values['accession_sra']; ?>">
					<span class="help-inline"><a href="#" id="sra_lookup" title="Auto-complete empty fields"><i class="icon-search"></i></a></span>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label" for="accession_ena"><abbr title="European Nucleotide Archive">ENA</abbr></label>
				<div class="controls">
					<input type="text" name="accession_ena" id="accession_ena" maxlength="50" placeholder="ERP000000" value="<?php echo $values['accession_ena']; ?>">
					<span class="help-inline"><a href="#" id="ena_lookup" title="Auto-complete empty fields"><i class="icon-search"></i></a></span>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label" for="accession_ddjb"><abbr title="DNA Data Bank of Japan">DDJB</abbr></label>
				<div class="controls">
					<input type="text" name="accession_ddjb" id="accession_ddjb" maxlength="50" placeholder="DRP000000" value="<?php echo $values['accession_ddjb']; ?>">
				</div>
			</div>
			<div class="control-group">
				<label class="control-label" for="accession_geo"><abbr title="PubMed ID">PMID</abbr></label>
				<div class="controls">
					<input type="text" id="accession_pmid" maxlength="50" placeholder="12345678">
					<span class="help-inline"><a href="#" id="pmid_lookup" title="Auto-complete paper details"><i class="icon-search"></i></a></span>
				</div>
			</div>
		</fieldset>

		<fieldset id="project_paper_fieldset">
			<legend>Publications</legend>
			<table class="table table_form edit_publications">
				<thead>
					<th style="width:40px">Year</th>
					<th style="width:100px">Journal</th>
					<th>Title</th>
					<th>Authors</th>
					<th style="width:60px">PMID</th>
					<th style="width:140px;">DOI</th>
					<th style="width:60px;">Actions</th>
				</thead>
				<tbody>
					<?php
					$papers = array();
					if($error){
						while(isset($_POST['project_year_'.$i])){
							// Collect variables
							$paper = array (
								"year" => $_POST['paper_year_'.$i],
								"journal" => $_POST['paper_journal_'.$i],
								"title" => $_POST['paper_journal_'.$i],
								"authors" => $_POST['paper_journal_'.$i],
								"pmid" => $_POST['paper_journal_'.$i],
								"doi" => $_POST['paper_journal_'.$i],
							);
							if(isset($_POST['paper_id_'.$i]) && is_numeric($_POST['paper_id_'.$i])){
								$paper['id'] = $_POST['paper_id_'.$i];
							}
							$papers[] = $paper;
						}
					} else if($edit){
						$papers_q = mysqli_query($dblink, "SELECT * FROM `papers` WHERE `project_id` = '".$project_id."'");
						while ($paper = mysqli_fetch_array($papers_q)){
							$papers[] = $paper;
						}
					}
					if(count($papers) == 0){ ?>
					<tr class="no_papers_tr">
						<td colspan="7"><em>No papers found..</em></td>
					</tr>
					<?php
					} else {
						$i = 0;
						foreach($papers as $paper){
							$i++; ?>
							<tr id="paper_row_<?php echo $i; ?>">
								<td>
									<input type="hidden" class="paper_id" id="paper_id_<?php echo $i; ?>" name="paper_id_<?php echo $i; ?>" value="<?php echo $paper['id']; ?>" />
									<input type="text" maxlength="4" class="paper_year" id="paper_year_<?php echo $i; ?>" name="paper_year_<?php echo $i; ?>" value="<?php echo $paper['year']; ?>" />
								</td>
								<td><input type="text" class="paper_journal" id="paper_journal_<?php echo $i; ?>" name="paper_journal_<?php echo $i; ?>" value="<?php echo $paper['journal']; ?>" /></td>
								<td><input type="text" class="paper_title" id="paper_title_<?php echo $i; ?>" name="paper_title_<?php echo $i; ?>" value="<?php echo $paper['title']; ?>"></td>
								<td><input type="text" class="paper_authors" id="paper_authors_<?php echo $i; ?>" name="paper_authors_<?php echo $i; ?>" value="<?php echo $paper['authors']; ?>"></td>
								<td><input type="text" class="paper_pmid" id="paper_pmid_<?php echo $i; ?>" name="paper_pmid_<?php echo $i; ?>" value="<?php echo $paper['pmid']; ?>" /></td>
								<td><input type="text" class="paper_doi" id="paper_doi_<?php echo $i; ?>" name="paper_doi_<?php echo $i; ?>" value="<?php echo $paper['doi']; ?>" /></td>
								<td><button class="paper_delete btn btn-small btn-danger" id="paper_delete_<?php echo $i; ?>">Delete</button></td>
							</tr>
					<?php }
					} ?>
				</tbody>
			</table>
			<p><a href="#" class="btn" id="paper_add_paper">Add Paper Manually</a></p>
		</fieldset>

		<fieldset id="project_notes_fieldset">
			<legend>Description &amp; Comments</legend>

			<div class="control-group ">
				<label class="control-label" for="title">Project Title</label>
				<div class="controls">
					<input type="text" name="title" id="title" class="input-xlarge" maxlength="250" value="<?php echo $values['title']; ?>">
				</div>
			</div>

			<div class="control-group ">
				<label class="control-label" for="description">Project Description</label>
				<div class="controls">
					<textarea name="description" class="input-xlarge" id="description"><?php echo $values['description']; ?></textarea>
				</div>
			</div>

			<div class="control-group ">
				<label class="control-label" for="notes">Comments</label>
				<div class="controls">
					<textarea name="notes" class="input-xlarge" id="notes"><?php echo $values['notes']; ?></textarea>
				</div>
			</div>
		</fieldset>

		<div class="form-actions">
			<input type="submit" class="btn btn-primary btn-large" name="save_project" id="save_project" value="Save Project">
			<?php if($edit && $admin){ ?>
				&nbsp; &nbsp; <a href="#" id="delete_project_button" class="btn btn-large btn-danger popover_button" data-toggle="popover" data-html="true" title="Delete Project" data-content="Are you sure? This will delete the project and all papers &amp; datasets associated with it from the database. <strong>This cannot be undone</strong>. Data on the server will not be affected. <br><br> <a href='project.php?delete=<?php echo $project_id; ?>' class='btn btn-danger btn-block'>I'm sure - delete the project</a>" data-original-title="Delete Project">Delete Project</a>
			<?php } ?>
		</div>
	</form>
</div>

<?php } // if($new or $edit or $delete)
else { // creating / editing but not logged in ?>

<div class="sidebar-mainpage project-mainpage">
	<div class="alert alert-info">
		<button type="button" class="close" data-dismiss="alert">×</button>
		<div style="text-align:center;">To create or edit a project, please <a data-toggle="modal" href="#register_modal">log in or register</a>.</div>
	</div>
</div>

<?php }

include('includes/javascript.php'); ?>
<script src="js/project.js" type="text/javascript"></script>
<?php include('includes/footer.php'); ?>
