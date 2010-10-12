<?php
/**
 * Entrada [ http://www.entrada-project.org ]
 *
 * This file gives Entrada users the ability to update their user profile.
 *
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Jonathan Fingland <jonathan.fingland@queensu.ca>
 * @copyright Copyright 2010 Queen's University. All Rights Reserved.
 *
*/
if (!defined("IN_PROFILE")) {
	exit;
} elseif ((!isset($_SESSION["isAuthorized"])) || (!$_SESSION["isAuthorized"])) {
	header("Location: ".ENTRADA_URL);
	exit;
} elseif(!$ENTRADA_ACL->isLoggedInAllowed('mspr', 'update',true) || $_SESSION["details"]["group"] != "student") {
	$ONLOAD[]	= "setTimeout('window.location=\\'".ENTRADA_URL."/".$MODULE."\\'', 15000)";

	add_error("Your account does not have the permissions required to use this module.<br /><br />If you believe you are receiving this message in error please contact <a href=\"mailto:".html_encode($AGENT_CONTACTS["administrator"]["email"])."\">".html_encode($AGENT_CONTACTS["administrator"]["name"])."</a> for assistance.");

	echo display_error();

	application_log("error", "Group [".$_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["group"]."] and role [".$_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["role"]."] do not have access to this module [".$MODULE."]");
}  else {
	require_once(dirname(__FILE__)."/includes/functions.inc.php");
	
	require_once("Models/mspr/MSPRs.class.php");
	//require_mspr_models();
	$user = User::get($PROXY_ID);
	$PAGE_META["title"]			= "MSPR";
	$PAGE_META["description"]	= "";
	$PAGE_META["keywords"]		= "";

	$PROXY_ID					= $_SESSION["details"]["id"];
	
	
	$BREADCRUMB[]	= array("url" => ENTRADA_URL."/profile?section=mspr", "title" => "MSPR");

	$HEAD[] = "<script language='javascript' src='".ENTRADA_URL."/javascript/ActiveDataEntryProcessor.js'></script>";
	$HEAD[] = "<script language='javascript' src='".ENTRADA_URL."/javascript/ActiveEditProcessor.js'></script>";
	$HEAD[] = "<script language='javascript' src='".ENTRADA_URL."/javascript/PriorityList.js'></script>";
	
	if ((is_array($_SESSION["permissions"])) && ($total_permissions = count($_SESSION["permissions"]) > 1)) {
		$sidebar_html  = "The following individual".((($total_permissions - 1) != 1) ? "s have" : " has")." given you access to their ".APPLICATION_NAME." permission levels:";
		$sidebar_html .= "<ul class=\"menu\">\n";
		foreach ($_SESSION["permissions"] as $proxy_id => $result) {
			if ($proxy_id != $_SESSION["details"]["id"]) {
				$sidebar_html .= "<li class=\"checkmark\"><strong>".html_encode($result["fullname"])."</strong><br /><span class=\"content-small\">Exp: ".(($result["expires"]) ? date("D M d/y", $result["expires"]) : "Unknown")."</span></li>\n";
			}
		}
		$sidebar_html .= "</ul>\n";

		new_sidebar_item("Delegated Permissions", $sidebar_html, "delegated-permissions", "open");
	}

	$name = $user->getFirstname() . " " . $user->getLastname();
	$number = $user->getNumber();
	$year = $user->getGradYear();

	$mspr = MSPR::get($user);
	
	if (!$mspr) { //no mspr yet. create one
		MSPR::create($user);
		$mspr = MSPR::get($user);
	}

	if (!$mspr) {
		add_notice("MSPR not yet available. Please try again later.");
		application_log("error", "Error creating MSPR for user " .$PROXY_ID. ": " . $name . "(".$number.")");
		display_status_messages();
	} else {
		$revisions = $mspr->getMSPRRevisions();
		$closed = $mspr->isClosed();
		$generated = $mspr->isGenerated();
		$revision = $mspr->getGeneratedTimestamp();
		if (!$revision && $revisions) {
			$revision = array_shift($revisions);
		}
		
		$class_data = MSPRClassData::get($year);
		
		$mspr_close = $mspr->getClosedTimestamp();
		
		if (!$mspr_close) { //no custom time.. use the class default
			$mspr_close = $class_data->getClosedTimestamp();	
		}
		
		if ($type = $_GET['get']) {
			switch($type) {
				case 'html':
					header('Content-type: text/html');
					header('Content-Disposition: filename="MSPR - '.$name.'('.$number.').html"');
					
					break;
				case 'pdf':
					header('Content-type: application/pdf');
					header('Content-Disposition: attachment; filename="MSPR - '.$name.'('.$number.').pdf"');
					break;
				default:
					add_error("Unknown file type: " . $type);
			}
			if (!has_error()) {
				ob_clear_open_buffers();
				flush();
				echo $mspr->getMSPRFile($type,$revision);
				exit();	
			}
			
		}
		
		$clerkship_core_completed = $mspr["Clerkship Core Completed"];
		$clerkship_core_pending = $mspr["Clerkship Core Pending"];
		$clerkship_elective_completed = $mspr["Clerkship Electives Completed"];
		$clinical_evaluation_comments = $mspr["Clinical Performance Evaluation Comments"];
		$critical_enquiry = $mspr["Critical Enquiry"];
		$student_run_electives = $mspr["Student-Run Electives"];
		$observerships = $mspr["Observerships"];
		$international_activities = $mspr["International Activities"];
		$internal_awards = $mspr["Internal Awards"];
		$external_awards = $mspr["External Awards"];
		$studentships = $mspr["Studentships"];
		$contributions = $mspr["Contributions to Medical School"];
		$leaves_of_absence = $mspr["Leaves of Absence"];
		$formal_remediations = $mspr["Formal Remediation Received"];
		$disciplinary_actions = $mspr["Disciplinary Actions"];
		$community_health_and_epidemiology = $mspr["Community Health and Epidemiology"];
		$research_citations = $mspr["Research"];
		
	
		display_status_messages();
	
?>

<h1>Medical School Performance Report</h1> 

<?php
if ($closed) {
?>
<div class="display-notice">
	<p>MSPR submission closed on <?php echo date("F j, Y \a\\t g:i a",$mspr_close); ?></p>
	<?php if ($revision) {	?>
	<p>Your MSPR is available in HTML and PDF, below:</p>
	<span class="file-block"><a href="<?php echo ENTRADA_URL; ?>/profile?section=mspr&get=html"><img src="<?php echo ENTRADA_URL; ?>/serve-icon.php?ext=html" /> HTML</a>&nbsp;&nbsp;&nbsp;<a href="<?php echo ENTRADA_URL; ?>/profile?section=mspr&get=pdf"><img src="<?php echo ENTRADA_URL; ?>/serve-icon.php?ext=pdf" /> PDF</a></span>
	<div class="clearfix">&nbsp;</div>
	<span class="last-update">Last Updated: <?php echo date("F j, Y \a\\t g:i a",$revision); ?></span>
	<?php } else { ?>
	<p>Finalized documents are not yet available.</p>
	<?php } ?>
</div>
<?php 
} elseif ($mspr_close) {
?>
<div class="display-notice">
The deadline for student submissions to this MSPR is <?php echo date("F j, Y \a\\t g:i a",$mspr_close); ?>. Please note that submissions may be approved, unapproved, or rejected after this date.  
</div>
<?php
}

?>

<div class="mspr-tree">

	<a href="#" onclick='document.fire("CollapseHeadings:expand-all");'>Expand All</a> / <a href="#" onclick='document.fire("CollapseHeadings:collapse-all");'>Collapse All</a>

	<?php 
	if (!$closed) {
	?>
	<h2 title="Required Information Section">Information Required From You</h2>
	<div id="required-information-section">
		<div class="instructions" style="margin-left:2em;margin-top:2ex;">
			<strong>Instructions</strong>
			<p>The sections below require your input. The information you provide will appear on your Medical School Performance Report. All submisions are subject to dean approval.</p>
			<ul>
				<li>
					Each section below provides a link to add new entires or edit in the case of single entires (Critical Enquiry, and Community Health and Epidemiology Project).  
				</li>
				<li>
					All entries have a background color corresponding to their status: 
					<ul>
						<li>Gray - Approved</li>
						<li>Yellow - Pending Approval</li>
						<li>Red - Rejected</li>
					</ul>
				</li>
			</ul>
		</div>
		<div class="section" >
			<h3 title="Critical Enquiry" class="collapsable collapsed">Critical Enquiry</h3>
			<div id="critical-enquiry">
			<?php 
			//use intermediary variables to prevent trying to reference methods on a non-existent object. This results in one condition test rather than testing on every output
			if ($critical_enquiry) {
				$ce_title = $critical_enquiry->getTitle();
				$ce_location = $critical_enquiry->getLocation();
				$ce_supervisor = $critical_enquiry->getSupervisor();
				$ce_organization = $critical_enquiry->getOrganization();
			} else {
				$ce_title = "";
				$ce_location = "";
				$ce_supervisor = "";
				$ce_organization = "";
			}
			
			?>	
			<div id="edit_critical_enquiry_link" style="float: right;">
				<ul class="page-action-edit">
					<li><a id="edit_critical_enquiry" href="<?php echo ENTRADA_URL; ?>/profile?section=mspr&id=<?php echo $PROXY_ID; ?>" class="strong-green">Edit Critical Enquiry</a></li>
				</ul>
			</div>
			<div class="clear">&nbsp;</div>
			<form id="edit_critical_enquiry_form" name="edit_critical_enquiry_form" action="<?php echo ENTRADA_URL; ?>/profile?section=mspr&id=<?php echo $PROXY_ID; ?>" method="post" style="display:none;">
				<input type="hidden" name="user_id" value="<?php echo $user->getID(); ?>"></input>
				<table class="mspr_form">
					<colgroup>
						<col width="3%"></col>
						<col width="25%"></col>
						<col width="72%"></col>
					</colgroup>
					<tfoot>
						<tr>
							<td colspan="3">&nbsp;</td>
						</tr>
						<tr>
							<td colspan="3" style="border-top: 2px #CCCCCC solid; padding-top: 5px; text-align: right">
								<input type="submit" name="action" value="Update" />
								<div id="hide_critical_enquiry_link" style="display:inline-block;">
									<ul class="page-action-cancel">
										<li><a id="hide_critical_enquiry" href="<?php echo ENTRADA_URL; ?>/profile?section=mspr&id=<?php echo $PROXY_ID; ?>" class="strong-green">[ Cancel Editing Project ]</a></li>
									</ul>
								</div>
							</td>
						</tr>
					</tfoot>
					<tbody>
						<tr>
							<td>&nbsp;</td>
							<td><label class="form-required" for="title">Title:</label></td>
							<td><input name="title" type="text" style="width:40%;" value="<?php echo $ce_title; ?>"></input></td>
						</tr>	
						<tr>
							<td>&nbsp;</td>
							<td><label class="form-required" for="organization">Organization:</label></td>
							<td><input name="organization" type="text" style="width:40%;" value="<?php echo $ce_organization; ?>"></input> <span class="content-small"><strong>Example</strong>: Queen's University</span></td>
						</tr>	
						<tr>
							<td>&nbsp;</td>
							<td><label class="form-required" for="location">Location:</label></td>
							<td><input name="location" type="text" style="width:40%;" value="<?php echo $ce_location; ?>"></input> <span class="content-small"><strong>Example</strong>: Kingston, Ontario</span></td>
						</tr>	
						<tr>
							<td>&nbsp;</td>
							<td><label class="form-required" for="supervisor">Supervisor:</label></td>
							<td><input name="supervisor" type="text" style="width:40%;" value="<?php echo $ce_supervisor; ?>"></input> <span class="content-small"><strong>Example</strong>: Dr. Nick Riviera</span></td>
						</tr>	
					</tbody>
				
				</table>	
			
				<div class="clear">&nbsp;</div>
			</form>
			<div id="critical_enquiry"><?php echo display_supervised_project_profile($critical_enquiry); ?></div>
			<div class="clear">&nbsp;</div>
			<script language="javascript">
			var critical_enquiry = new ActiveEditProcessor({
				url : '<?php echo webservice_url("mspr-profile"); ?>&id=<?php echo $PROXY_ID; ?>&mspr-section=critical_enquiry',
				data_destination: $('critical_enquiry'),
				edit_form: $('edit_critical_enquiry_form'),
				edit_button: $('edit_critical_enquiry_link'),
				hide_button: $('hide_critical_enquiry'),
				section: 'critical-enquiry'
		
			});
			
			</script>
			</div>
		</div>
		<div class="section" >
			<h3 title="Community Health and Epidemiology" class="collapsable collapsed">Community Health and Epidemiology</h3>
			<div id="community-health-and-epidemiology">
			
			
				<?php 
			$show_community_health_and_epidemiology_form =  ($_GET['show'] == "community_health_and_epidemiology_form");
		
			//use intermediary variables to prevent trying to reference methods on a non-existent object. This results in one condition test rather than testing on every output
			if ($community_health_and_epidemiology) {
				$chae_title = $community_health_and_epidemiology->getTitle();
				$chae_location = $community_health_and_epidemiology->getLocation();
				$chae_supervisor = $community_health_and_epidemiology->getSupervisor();
				$chae_organization = $community_health_and_epidemiology->getOrganization();
			} else {
				$chae_title = "";
				$chae_location = "";
				$chae_supervisor = "";
				$chae_organization = "";
			}
			
			?>	
			<div id="edit_community_health_and_epidemiology_link" style="float: right;<?php if ($show_community_health_and_epidemiology_form) { echo "display:none;"; }   ?>">
				<ul class="page-action-edit">
					<li><a id="edit_community_health_and_epidemiology" href="<?php echo ENTRADA_URL; ?>/profile?section=mspr&show=community_health_and_epidemiology_form&id=<?php echo $PROXY_ID; ?>" class="strong-green">Edit Community Health and Epidemiology Project</a></li>
				</ul>
			</div>
			<div class="clear">&nbsp;</div>
			<form id="edit_community_health_and_epidemiology_form" name="edit_community_health_and_epidemiology_form" action="<?php echo ENTRADA_URL; ?>/profile?section=mspr&id=<?php echo $PROXY_ID; ?>" method="post" <?php if (!$show_community_health_and_epidemiology_form) { echo "style=\"display:none;\""; }   ?> >
				<input type="hidden" name="user_id" value="<?php echo $user->getID(); ?>"></input>
				<table class="mspr_form">
					<colgroup>
						<col width="3%"></col>
						<col width="25%"></col>
						<col width="72%"></col>
					</colgroup>
					<tfoot>
						<tr>
							<td colspan="3">&nbsp;</td>
						</tr>
						<tr>
							<td colspan="3" style="border-top: 2px #CCCCCC solid; padding-top: 5px; text-align: right">
								<input type="submit" name="action" value="Update" />
								<div id="hide_community_health_and_epidemiology_link" style="display:inline-block;">
									<ul class="page-action-cancel">
										<li><a id="hide_community_health_and_epidemiology" href="<?php echo ENTRADA_URL; ?>/profile?section=mspr&id=<?php echo $PROXY_ID; ?>" class="strong-green">[ Cancel Editing Project ]</a></li>
									</ul>
								</div>
							</td>
						</tr>
					</tfoot>
					<tbody>
						<tr>
							<td>&nbsp;</td>
							<td><label class="form-required" for="title">Title:</label></td>
							<td><input name="title" type="text" style="width:40%;" value="<?php echo $chae_title; ?>"></input></td>
						</tr>	
						<tr>
							<td>&nbsp;</td>
							<td><label class="form-required" for="organization">Organization:</label></td>
							<td><input name="organization" type="text" style="width:40%;" value="<?php echo $chae_organization; ?>"></input> <span class="content-small"><strong>Example</strong>: Housing First/Queen's University</span></td>
						</tr>	
						<tr>
							<td>&nbsp;</td>
							<td><label class="form-required" for="location">Location:</label></td>
							<td><input name="location" type="text" style="width:40%;" value="<?php echo $chae_location; ?>"></input> <span class="content-small"><strong>Example</strong>: Kingston, Ontario</span></td>
						</tr>	
						<tr>
							<td>&nbsp;</td>
							<td><label class="form-required" for="supervisor">Supervisor:</label></td>
							<td><input name="supervisor" type="text" style="width:40%;" value="<?php echo $chae_supervisor; ?>"></input> <span class="content-small"><strong>Example</strong>: Dr. Nick Riviera</span></td>
						</tr>	
					</tbody>
				
				</table>	
			
				<div class="clear">&nbsp;</div>
			</form>
			<div id="community_health_and_epidemiology"><?php echo display_supervised_project_profile($community_health_and_epidemiology); ?></div>
			<div class="clear">&nbsp;</div>
			<script language="javascript">
			var community_health_and_epidemiology = new ActiveEditProcessor({
				url : '<?php echo webservice_url("mspr-profile"); ?>&id=<?php echo $PROXY_ID; ?>&mspr-section=community_health_and_epidemiology',
				data_destination: $('community_health_and_epidemiology'),
				edit_form: $('edit_community_health_and_epidemiology_form'),
				edit_button: $('edit_community_health_and_epidemiology_link'),
				hide_button: $('hide_community_health_and_epidemiology'),
				section:'community_health_and_epidemiology'
		
			});
			
			</script>
		</div>
		</div><div class="section" >
			<h3 title="Research" class="collapsable collapsed">Research</h3>
			<div id="research">
				<div class="instructions">
					<ul>
						<li>Only add citations of published research in which you were a named author</li>
						<li>Citations below may be re-ordered. The top-six <em>approved</em> citations will appear on your MSPR.</li>
						<li>Research citations should be provided in a format following <a href="http://owl.english.purdue.edu/owl/resource/747/01/">MLA guidelines</a></li>
					</ul>
				</div>
				<div id="add_research_citation_link" style="float: right;">
					<ul class="page-action">
						<li><a id="add_research_citation" href="<?php echo ENTRADA_URL; ?>/profile?section=mspr&id=<?php echo $PROXY_ID; ?>" class="strong-green">Add Research Citation</a></li>
					</ul>
				</div>
				<div class="clear">&nbsp;</div>
				<form id="add_research_citation_form" name="add_research_citation_form" action="<?php echo ENTRADA_URL; ?>/profile?section=mspr&id=<?php echo $PROXY_ID; ?>" method="post" style="display:none;" >
					<input type="hidden" name="user_id" value="<?php echo $user->getID(); ?>"></input>
					<table class="mspr_form">
						<colgroup>
							<col width="3%"></col>
							<col width="25%"></col>
							<col width="72%"></col>
						</colgroup>
						<tfoot>
							<tr>
								<td colspan="3">&nbsp;</td>
							</tr>
							<tr>
								<td colspan="3" style="border-top: 2px #CCCCCC solid; padding-top: 5px; text-align: right">
									<input type="submit" name="action" value="Add" />
									<div id="hide_research_citation_link" style="display:inline-block;">
										<ul class="page-action-cancel">
											<li><a id="hide_research_citation" href="<?php echo ENTRADA_URL; ?>/profile?section=mspr&id=<?php echo $PROXY_ID; ?>" class="strong-green">[ Cancel Adding Contribution ]</a></li>
										</ul>
									</div>
								</td>
							</tr>
						</tfoot>
						<tbody>
							<tr>
							<td>&nbsp;</td>
							<td valign="top"><label class="form-required" for="details">Citation:</label></td>
							<td><textarea name="details" style="width:80%;height:8ex;"></textarea><br /><span class="content-small">Note: Should adhere to MLA guidelines.</span>
							</td>
							</tr>
						</tbody>
					
					</table>	
				
					<div class="clear">&nbsp;</div>
				</form>
			
				<div id="research_citations"><?php echo display_research_citations_profile($research_citations); ?></div>
				<div class="clear">&nbsp;</div>
				<script language="javascript">
				var research_citations = new ActiveDataEntryProcessor({
					url : '<?php echo webservice_url("mspr-profile"); ?>&id=<?php echo $PROXY_ID; ?>&mspr-section=research_citations',
					data_destination: $('research_citations'),
					new_form: $('add_research_citation_form'),
					remove_forms_selector: '#research .entry form',
					new_button: $('add_research_citation_link'),
					hide_button: $('hide_research_citation'),
					section:'research_citations'
			
				});
			
				var research_citation_priority_list = new PriorityList({
					url : '<?php echo webservice_url("mspr-profile"); ?>&id=<?php echo $PROXY_ID; ?>&mspr-section=research_citations',
					data_destination: $('research_citations'),
					format: /research_citation_([0-9]*)$/,
					tag: "li",
					handle:'.handle',
					section:'research_citations',
					element: 'citations_list'
				});
				</script>
			</div>
		</div>
		<div class="section">
			 
			<h3 class="collapsable collapsed" title="External Awards Section">External Awards</h3>
			<div id="external-awards-section">
				<div class="instructions">
					<ul>
						<li>Only awards of academic significance will be considered.</li>
						<li>Award terms must be provided to be considered. Awards not accompanied by terms will be rejected.</li>
					</ul>
				</div>
			<div id="add_external_award_link" style="float: right;">
				<ul class="page-action">
					<li><a id="add_external_award" href="#external-awards-section" class="strong-green">Add External Award</a></li>
				</ul>
			</div>
			<div class="clear">&nbsp;</div>
			<form id="add_external_award_form" style="display:none;" name="add_external_award_form" action="<?php echo ENTRADA_URL; ?>/profile?section=mspr&id=<?php echo $PROXY_ID; ?>" method="post">
				<input type="hidden" name="user_id" value="<?php echo $user->getID(); ?>"></input>
				<table class="mspr_form">
					<colgroup>
						<col width="3%"></col>
						<col width="25%"></col>
						<col width="72%"></col>
					</colgroup>
					<tfoot>
						<tr>
							<td colspan="3">&nbsp;</td>
						</tr>
						<tr>
							<td colspan="3" style="border-top: 2px #CCCCCC solid; padding-top: 5px; text-align: right">
								<input type="submit" name="action" value="Add" />
								<div id="hide_external_award_link" style="display:inline-block;">
									<ul class="page-action-cancel">
										<li><a id="hide_external_award" href="<?php echo ENTRADA_URL; ?>/profile?section=mspr&id=<?php echo $PROXY_ID; ?>" class="strong-green">[ Cancel Adding External Award ]</a></li>
									</ul>
								</div>
							</td>
						</tr>
					</tfoot>
					<tbody>
						<tr>
						<td>&nbsp;</td>
						<td><label class="form-required" for="title">Title:</label></td>
						<td><input name="title" type="text" style="width:60%;"></input></td>
						</tr>	
						<tr>
						<td>&nbsp;</td>
						<td><label class="form-required" for="body">Awarding Body:</label></td>
						<td><input name="body" type="text" style="width:60%;"></input></td>
						</tr>	
						<tr>
						<td>&nbsp;</td>
						<td valign="top"><label class="form-required" for="terms">Award Terms:</label></td>
						<td><textarea name="terms" style="width: 80%; height: 12ex;" cols="65" rows="20"></textarea></td>
						</tr>	
						<tr>
						<td>&nbsp;</td>
						<td><label class="form-required" for="year">Year Awarded:</label></td>
						<td><select name="year">
							<?php 
							
							$cur_year = (int) date("Y");
							$start_year = $cur_year - 10;
							$end_year = $cur_year + 4;
							
							for ($opt_year = $start_year; $opt_year <= $end_year; ++$opt_year) {
									echo build_option($opt_year, $opt_year, $opt_year == $cur_year);
							}
							
							?>
							</select></td>
						</tr>
					</tbody>
				
				</table>	
			
				<div class="clear">&nbsp;</div>
			</form>
			<div id="external_awards"><?php echo display_external_awards_profile($external_awards); ?></div>
			<div class="clear">&nbsp;</div>
			<script language="javascript">
			var external_awards = new ActiveDataEntryProcessor({
				url : '<?php echo webservice_url("mspr-profile"); ?>&id=<?php echo $PROXY_ID; ?>&mspr-section=external_awards',
				data_destination: $('external_awards'),
				new_form: $('add_external_award_form'),
				remove_forms_selector: '#external_awards .entry form',
				new_button: $('add_external_award_link'),
				hide_button: $('hide_external_award'),
			section:'external_awards'
		
			});
			
			</script>
			</div>
		</div>
		<div class="section" >
			<h3 title="Contributions to Medical School" class="collapsable collapsed">Contributions to Medical School/Student Life</h3>
			<div id="contributions-to-medical-school">
				<div class="instructions">
					<ul>
						<li>Extra-curricular learning activities are only approved if verified</li>
						<li>Examples of contributions to medical school/student life include:
							<ul>
								<li>Participation in School of Medicine student government</li>
								<li>Committees (such as admissions)</li>
								<li>Organizing extra-curricular learning activities and seminars</li>					
							</ul>
						</li>
					</ul>
				</div>
			
				<?php 
				$show_contributions_form =  ($_GET['show'] == "contributions_form");
				?>	
				<div id="add_contribution_link" style="float: right;<?php if ($show_contributions_form) { echo "display:none;"; }   ?>">
					<ul class="page-action">
						<li><a id="add_contribution" href="<?php echo ENTRADA_URL; ?>/profile?section=mspr&show=contributions_form&id=<?php echo $PROXY_ID; ?>" class="strong-green">Add Contribution</a></li>
					</ul>
				</div>
				<div class="clear">&nbsp;</div>
				<form id="add_contribution_form" name="add_contribution_form" action="<?php echo ENTRADA_URL; ?>/profile?section=mspr&id=<?php echo $PROXY_ID; ?>" method="post" <?php if (!$show_contributions_form) { echo "style=\"display:none;\""; }   ?> >
					<input type="hidden" name="user_id" value="<?php echo $user->getID(); ?>"></input>
					<table class="mspr_form">
						<colgroup>
							<col width="3%"></col>
							<col width="25%"></col>
							<col width="72%"></col>
						</colgroup>
						<tfoot>
							<tr>
								<td colspan="3">&nbsp;</td>
							</tr>
							<tr>
								<td colspan="3" style="border-top: 2px #CCCCCC solid; padding-top: 5px; text-align: right">
									<input type="submit" name="action" value="Add" />
									<div id="hide_contribution_link" style="display:inline-block;">
										<ul class="page-action-cancel">
											<li><a id="hide_contribution" href="<?php echo ENTRADA_URL; ?>/profile?section=mspr&id=<?php echo $PROXY_ID; ?>" class="strong-green">[ Cancel Adding Contribution ]</a></li>
										</ul>
									</div>
								</td>
							</tr>
						</tfoot>
						<tbody>
							<tr>
							<td>&nbsp;</td>
							<td><label class="form-required" for="role">Role:</label></td>
							<td><input name="role" type="text" style="width:40%;"></input> <span class="content-small"><strong>Example</strong>: Interviewer</span></td>
							</tr>	
							<tr>
							<td>&nbsp;</td>
							<td><label class="form-required" for="org_event">Organization/Event:</label></td>
							<td><input name="org_event" type="text" style="width:40%;"></input> <span class="content-small"><strong>Example</strong>: Medical School Interview Weekend</span></td>
							</tr>	
													<tr>
										<td>&nbsp;</td>
										<td><label class="form-required" for="start">Start:</label></td>
										<td>
											<select name="start_month">
											<?php
											echo build_option("","Month",true);
												
											for($month_num = 1; $month_num <= 12; $month_num++) {
												echo build_option($month_num, getMonthName($month_num));
											}
											?>
											</select>
											<select name="start_year">
											<?php 
											$cur_year = (int) date("Y");
											$start_year = $cur_year - 6;
											$end_year = $cur_year + 4;
											
											for ($opt_year = $start_year; $opt_year <= $end_year; ++$opt_year) {
													echo build_option($opt_year, $opt_year, $opt_year == $cur_year);
											}
											?>
											</select>
										</td>
									</tr>
									<tr>
										<td>&nbsp;</td>
										<td><label class="form-required" for="end">End:</label></td>
										<td>
											<select tabindex="1" name="end_month">
											<?php
											echo build_option("","Month",true);
												
											for($month_num = 1; $month_num <= 12; $month_num++) {
												echo build_option($month_num, getMonthName($month_num));
											}
											?>
											</select>
											<select name="end_year">
											<?php 
											echo build_option("","Year",true);
											$cur_year = (int) date("Y");
											$start_year = $cur_year - 6;
											$end_year = $cur_year + 4;
											
											for ($opt_year = $start_year; $opt_year <= $end_year; ++$opt_year) {
													echo build_option($opt_year, $opt_year, false);
											}
											?>
											</select>
										</td>
									</tr>
						</tbody>
					
					</table>	
				
					<div class="clear">&nbsp;</div>
				</form>
		
				<div id="contributions"><?php echo display_contributions_profile($contributions); ?></div>
				<div class="clear">&nbsp;</div>
				<script language="javascript">
				var contributions = new ActiveDataEntryProcessor({
					url : '<?php echo webservice_url("mspr-profile"); ?>&id=<?php echo $PROXY_ID; ?>&mspr-section=contributions',
					data_destination: $('contributions'),
					new_form: $('add_contribution_form'),
					remove_forms_selector: '#contributions .entry form',
					new_button: $('add_contribution_link'),
					hide_button: $('hide_contribution'),
					section:'contributions'
			
				});
				
				</script>
			
			</div>
		</div>
	</div>
	<h2 title="Supplied Information Section" class="collapsed">Information Supplied by Staff and Faculty</h2>
	<div id="supplied-information-section">
		<div class="instructions">
		<p>This section consists of information entered by staff or extracted from other sources (for example, clerkship schedules).</p>
		<p>Please periodically read over the information in the following sections to verify its accuracy. If any errors are found, please contact the undergraduate office.</p>
		</div>
	
		<div class="section">
		<h3 title="Clerkship Core Rotations Completed Satisfactorily to Date" class="collapsable collapsed">Clerkship Core Rotations Completed Satisfactorily to Date</h3>
			<div id="clerkship-core-rotations-completed-satisfactorily-to-date">
			<div id="clerkships_core_completed"><?php echo display_clerkship_details($clerkship_core_completed); ?></div>
		
		</div>
		</div><div class="section">
		<h3 title="Clerkship Core Rotations Pending" class="collapsable collapsed">Clerkship Core Rotations Pending</h3>
			<div id="clerkship-core-rotations-pending">
			<div id="clerkships_core_pending"><?php echo display_clerkship_details($clerkship_core_pending); ?></div>
		</div>
		</div><div class="section">
		<h3 title="Clerkship Electives Completed Satisfactorily to Date" class="collapsable collapsed">Clerkship Electives Completed Satisfactorily to Date</h3>
			<div id="clerkship-electives-completed-satisfactorily-to-date">
			<div id="clerkships_electves_completed"><?php echo display_clerkship_details($clerkship_elective_completed); ?></div>
		
		</div>
		</div><div class="section" >
			<h3 title="Clinical Performance Evaluation Comments" class="collapsable collapsed">Clinical Performance Evaluation Comments</h3>
			<div id="clinical-performance-evaluation-comments">
			<div id="clinical_performance_eval_comments"><?php echo display_clineval_profile($clinical_evaluation_comments); ?></div>
			
		</div>
		
		
		</div><div class="section" >
			<h3 title="Extra-curricular Learning Activities" class="collapsable collapsed">Extra-curricular Learning Activities</h3>
			<div id="extra-curricular-learning-activities">
			
			<div class="subsection">
				<h4 title="International Activities">International Activities</h4>
				<div id="international-activities"><?php echo display_international_activities($international_activities); ?></div>
			</div>
			<div class="subsection" >
				<h4>Observerships</h4>
				<div id="observerships"><?php echo display_observerships_public($observerships); ?></div>
			</div>
			<div class="subsection" >
				<h4>Student-Run Electives</h4>
				<div id="student_run_electives"><?php echo display_student_run_electives_public($student_run_electives); ?></div>
			</div>
		</div>
		
		</div>
		<div class="section">
			<h3 title="Internal Awards" class="collapsable collapsed">Internal Awards</h3>
			<div id="internal-awards"><?php echo display_internal_awards($internal_awards); ?></div>
			
		</div><div class="section" >
			<h3 title="Summer Studentships" class="collapsable collapsed">Summer Studentships</h3>
			<div id="summer-studentships"><?php echo display_studentships($studentships); ?></div>
		</div>
		<div class="section">
			<h3 title="Leaves of Absence" class="collapsable collapsed">Leaves of Absence</h3>
			<div id="leaves-of-absence">
			<?php 
			echo display_mspr_details($leaves_of_absence);
			?>
			</div>
		</div>
		<div class="section">
			<h3 title="Formal Remediation Received" class="collapsable collapsed">Formal Remediation Received</h3>
			<div id="formal-remediation-received">
			<?php 
			echo display_mspr_details($formal_remediations);
			?>
			</div>
		</div>
		<div class="section">
			<h3 title="Disciplinary Actions" class="collapsable collapsed">Disciplinary Actions</h3>
			<div id="disciplinary-actions"> 
			<?php 
			echo display_mspr_details($disciplinary_actions);
			?>
			</div>
		</div>
	</div>
	<?php 
	} else {
	?>
	<div class="section" >
		<h3 title="Critical Enquiry" class="collapsable collapsed">Critical Enquiry</h3>
		<div id="critical-enquiry">
			<div id="critical_enquiry"><?php echo display_supervised_project_profile($critical_enquiry); ?></div>
		</div>
	</div>
	<div class="section" >
		<h3 title="Community Health and Epidemiology" class="collapsable collapsed">Community Health and Epidemiology</h3>
		<div id="community-health-and-epidemiology">
			<div id="community_health_and_epidemiology"><?php echo display_supervised_project_profile($community_health_and_epidemiology); ?></div>
		</div>
	</div>
	<div class="section" >
		<h3 title="Research" class="collapsable collapsed">Research</h3>
		<div id="research">
			<div id="research_citations"><?php echo display_research_citations_profile($research_citations, true); ?></div>
		</div>
	</div>
	<div class="section">
		 
		<h3 class="collapsable collapsed" title="External Awards Section">External Awards</h3>
		<div id="external-awards-section">
			<div id="external_awards"><?php echo display_external_awards_profile($external_awards,true); ?></div>
		</div>
	</div>
	<div class="section" >
		<h3 title="Contributions to Medical School" class="collapsable collapsed">Contributions to Medical School</h3>
		<div id="contributions-to-medical-school">
			<div id="contributions"><?php echo display_contributions_profile($contributions,true); ?></div>
		</div>
	</div>
	<div class="section">
		<h3 title="Clerkship Core Rotations Completed Satisfactorily to Date" class="collapsable collapsed">Clerkship Core Rotations Completed Satisfactorily to Date</h3>
		<div id="clerkship-core-rotations-completed-satisfactorily-to-date">
			<div id="clerkships_core_completed"><?php echo display_clerkship_details($clerkship_core_completed); ?></div>
		</div>
	</div>
	<div class="section">
		<h3 title="Clerkship Core Rotations Pending" class="collapsable collapsed">Clerkship Core Rotations Pending</h3>
		<div id="clerkship-core-rotations-pending">
			<div id="clerkships_core_pending"><?php echo display_clerkship_details($clerkship_core_pending); ?></div>
		</div>
	</div>
	<div class="section">
		<h3 title="Clerkship Electives Completed Satisfactorily to Date" class="collapsable collapsed">Clerkship Electives Completed Satisfactorily to Date</h3>
		<div id="clerkship-electives-completed-satisfactorily-to-date">
			<div id="clerkships_electves_completed"><?php echo display_clerkship_details($clerkship_elective_completed); ?></div>
		</div>
	</div>
	<div class="section" >
		<h3 title="Clinical Performance Evaluation Comments" class="collapsable collapsed">Clinical Performance Evaluation Comments</h3>
		<div id="clinical-performance-evaluation-comments">
			<div id="clinical_performance_eval_comments"><?php echo display_clineval_profile($clinical_evaluation_comments); ?></div>
		</div>
	</div>
	
	<div class="section" >
		<h3 title="Extra-curricular Learning Activities" class="collapsable collapsed">Extra-curricular Learning Activities</h3>
		<div id="extra-curricular-learning-activities">
		
			<div class="subsection">
				<h4 title="International Activities">International Activities</h4>
				<div id="international-activities"><?php echo display_international_activities($international_activities); ?></div>
			</div>
			<div class="subsection" >
				<h4>Observerships</h4>
				<div id="observerships"><?php echo display_observerships_public($observerships); ?></div>
			</div>
			<div class="subsection" >
				<h4>Student-Run Electives</h4>
				<div id="student_run_electives"><?php echo display_student_run_electives_public($student_run_electives); ?></div>
			</div>
		</div>
	</div>
	<div class="section">
		<h3 title="Internal Awards" class="collapsable collapsed">Internal Awards</h3>
		<div id="internal-awards"><?php echo display_internal_awards($internal_awards); ?></div>
	</div>
	<div class="section" >
		<h3 title="Summer Studentships" class="collapsable collapsed">Summer Studentships</h3>
		<div id="summer-studentships"><?php echo display_studentships($studentships); ?></div>
	</div>
	<div class="section">
		<h3 title="Leaves of Absence" class="collapsable collapsed">Leaves of Absence</h3>
		<div id="leaves-of-absence">
		<?php echo display_mspr_details($leaves_of_absence); ?>
		</div>
	</div>
	<div class="section">
		<h3 title="Formal Remediation Received" class="collapsable collapsed">Formal Remediation Received</h3>
		<div id="formal-remediation-received">
		<?php echo display_mspr_details($formal_remediations); ?>
		</div>
	</div>
	<div class="section">
		<h3 title="Disciplinary Actions" class="collapsable collapsed">Disciplinary Actions</h3>
		<div id="disciplinary-actions"> 
		<?php echo display_mspr_details($disciplinary_actions); ?>
		</div>
	</div>
	<?php 
	}
	?>
	
	
</div>	
<?php 
	}
}
?>