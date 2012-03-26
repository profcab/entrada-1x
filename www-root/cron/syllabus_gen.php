<?php
/**
 * Online Course Resources [Pre-Clerkship]
 * @author Unit: Medical Education Technology Unit
 * @author Director: Dr. Benjamin Chen <bhc@post.queensu.ca>
 * @author Developer: Matt Simpson <simpson@post.queensu.ca>
 * @version 3.0
 * @copyright Copyright 2006 Queen's University, MEdTech Unit
 *
 * $Id: syllabus_gen.php 1116 2010-04-13 15:38:31Z jellis $
*/

@set_time_limit(0);
@set_include_path(implode(PATH_SEPARATOR, array(
    dirname(__FILE__) . "/../core",
    dirname(__FILE__) . "/../core/includes",
    dirname(__FILE__) . "/../core/library",
    get_include_path(),
)));

/**
 * Include the Entrada init code.
 */

require_once("init.inc.php");

$courses = array (
	/*"93" => array(
		"included_pages" => array ("background", "course aims", "teaching strategies", "assessment strategies", "resources", "expectations of students", "expectations of faculty")
	),
	"101" => array(
		"included_pages" => array ("background", "course aims", "teaching strategies", "assessment strategies", "resources", "expectations of students", "expectations of faculty")
	),
	"94" => array(
		"included_pages" => array ("background", "course aims", "teaching strategies", "assessment strategies", "resources", "expectations of students", "expectations of faculty")
	),
	"92" => array(
		"included_pages" => array ("background", "course aims", "teaching strategies", "assessment strategies", "resources", "expectations of students", "expectations of faculty")
	),
	"95" => array(
		"included_pages" => array ("home", "course aims", "teaching strategies", "assessment strategies", "resources", "expectations of students", "expectations of faculty")
	),
	"109" => array(
		"included_pages" => array ("course aims", "teaching strategies", "assessment strategies", "resources", "expectations of students", "expectations of faculty")
	),
	"257" => array(
		"included_pages" => array ("home", "course aims", "teaching strategies", "assessment strategies", "resources", "expectations of students", "expectations of faculty")
	),
	"112" => array(
		"included_pages" => array ("home", "course aims", "teaching strategies", "assessment strategies", "resources", "expectations of students", "expectations of faculty")
	),
	"122" => array(
		"included_pages" => array ("home", "course aims", "teaching strategies", "assessment strategies", "resources", "expectations of students", "expectations of faculty")
	),
	"223" => array(
		"included_pages" => array ("home", "course aims", "teaching strategies", "assessment strategies", "resources", "expectations of students", "expectations of faculty")
	),*/
	"default" => array(
		"included_pages" => array ("background", "course aims", "teaching strategies", "assessment strategies", "resources", "expectations of students", "expectations of faculty")
	),
);

$cohort		 = (int) $_GET["cohort"];
$term		 = (int) $_GET["term"];
$start_date  = (int) $_GET["start_date"];
$end_date	 = (int) $_GET["end_date"];
$course_id	 = (int) $_GET["course_id"];
$course_code = (string) strip_tags($_GET["course_code"]);
$pages = array_key_exists($course_id, $courses) ? $courses[$course_id]["included_pages"] : $courses["default"]["included_pages"] ;

function course_objectives_formatted($objectives, $parent_id, $top_level_id, $edit_importance = false, $parent_active = false, $importance = 1, $selected_only = false, $top = true, $display_importance = "primary", $hierarchical = false, $full_objective_list = false, $org_id = 0) {
	global $ENTRADA_USER;
	
	$output = "";
	$active = array("primary" => false, "secondary" => false, "tertiary" => false);
	
	if ($top) {
		if ($selected_only) {
			foreach ($objectives["objectives"] as $objective_id => $objective) {
				if (isset($objective["event_objective"]) && $objective["event_objective"]) {
					if (!$active["primary"] && $objective["primary"]) {
						$active["primary"] = true; 
					} elseif (!$active["secondary"] && $objective["secondary"]) {
						$active["secondary"] = true;
					} elseif (!$active["tertiary"] && $objective["tertiary"]) {
						$active["tertiary"] = true;
					}
				}
			}
			if (!$active["primary"]) {
				$display_importance = "secondary";
			} elseif (!$active["secondary"] && !$active["primary"]) {
				$display_importance = "tertiary";
			} elseif (!$active["tertiary"] && !$active["secondary"] && !$active["primary"]) {
				return;
			}
		} else {
			if ($objectives["primary_ids"]) {
				$active["primary"] = true;
				$display_importance = "primary";
			}
			if ($objectives["secondary_ids"]) {
				$active["secondary"] = true;
				if (empty($objectives["primary_ids"])) {
					$display_importance = "secondary";
				} 
			}
			if ($objectives["tertiary_ids"]) {
				$active["tertiary"] = true;
				if (empty($objectives["primary_ids"]) && empty($objectives["secondary_ids"])) {
					$display_importance = "tertiary";
				} 
			}
		}
		$objectives = $objectives["objectives"];
		if ($display_importance == "primary" && !$active["primary"]) {
			return;
		}
	}
	if (!$full_objective_list) {
		$full_objective_list = events_fetch_objectives_structure($parent_id, $objectives["used_ids"], 1);
	}
	$flat_objective_list = events_flatten_objectives($full_objective_list);
	
	if ((is_array($objectives)) && (count($objectives))) {
		$iterated = false;
		do {
			if ($iterated) {
				if ($display_importance == "primary" && $active["secondary"]) {
					$display_importance = "secondary";
				} elseif ((($display_importance == "secondary" || $display_importance == "primary") && $active["tertiary"])) {
					$display_importance = "tertiary";
				}
			}
			if ($top) {
				$output .= "<h3".($iterated && !$hierarchical ? " class=\"collapsed\"" : "")." title=\"".ucwords($display_importance)." Objectives\"><strong><u>".ucwords($display_importance)." Objectives</u></strong></h3>\n";
				$output .= "<div id=\"".($display_importance)."-objectives\">\n";
			}
			foreach ($flat_objective_list as $objective_id => $objective_active) {
				$objective = $objectives[$objective_id];
				if (($objective["parent"] == $parent_id) && (($objective["objective_".$display_importance."_children"]) || ((isset($objective[$display_importance]) && $objective[$display_importance]) || ($parent_active && count($objective["parent_ids"]) > 2) && !$selected_only) || ($selected_only && isset($objective["event_objective"]) && $objective["event_objective"] && (isset($objective[$display_importance]) && $objective[$display_importance])))) {
					$importance = ((isset($objective["primary"]) && $objective["primary"]) ? 1 : ((isset($objective["secondary"]) && $objective["secondary"]) ? 2 : ((isset($objective["tertiary"]) && $objective["tertiary"]) ? 3 : $importance)));
					if ((count($objective["parent_ids"]) > 1)) {
						if (!empty($objective["objective_details"])) { $output .= "<div".((($parent_active) || (isset($objective[$display_importance]) && $objective[$display_importance])) && (count($objective["parent_ids"]) > 2) ? " class=\"".($importance == 1 ? "primary" : ($importance == 2 ? "secondary" : "tertiary"))."\"" : "")." id=\"objective_".$objective_id."_row\">\n"; }
						if (count($objective["parent_ids"]) == 3) {
							$output .= "	<p>".(isset($objective["objective_details"]) && $objective["objective_details"] ? $objective["objective_details"] : $objective["description"])." <strong>".$objective["name"]."</strong>";
						} else {
							$output .= "	<p id=\"objective_".$objective_id."\"><strong>".$objective["name"]."</strong></p>\n";
							$output .= "	<p>".(isset($objective["objective_details"]) && $objective["objective_details"] ? $objective["objective_details"] : $objective["description"]);
						}
						$output .= "	</p>\n";
						if (!empty($objective["objective_details"])) { $output .= "</div>"; }
					}
				}
				if ($objective["parent"] == $parent_id) {
					$output .= course_objectives_formatted($objectives, $objective_id,$top_level_id, $edit_importance, ((isset($objective[$display_importance]) && $objective[$display_importance]) ? true : false), $importance, $selected_only, false, $display_importance, $hierarchical, $full_objective_list);
				}
			}
			$iterated = true;
			if ($top) {
				$output .= "</div>\n";
			}
		} while ((($display_importance != "tertiary") && ($display_importance != "secondary" || $active["tertiary"]) && ($display_importance != "primary" || $active["secondary"] || $active["tertiary"])) && $top);
	}
	return $output;
}

		
?>
<html>

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">

	<title>Class of <?php echo "2015"; ?> / Term <?php echo $term; ?> / <?php echo $course_code; ?> Syllabus</title>

	<meta name="author" content="<?php echo $AGENT_CONTACTS["general-contact"]["name"]; ?>, &lt;<?php echo $AGENT_CONTACTS["general-contact"]["email"]; ?>&gt;">
	<meta name="copyright" content="<?php echo COPYRIGHT_STRING; ?>">
	<meta name="docnumber" content="Generated: <?php echo date(DEFAULT_DATE_FORMAT, time()) ?>">
	<meta name="generator" content="Syllabus Generator">
	<meta name="keywords" content="Class of <?php echo $EVENT_GRAD_YEAR; ?>, Term <?php echo $EVENT_Term; ?>, Syllabus, Undergraduate, Education">
	<meta name="subject" content="Class of <?php echo $EVENT_GRAD_YEAR; ?>, Term <?php echo $EVENT_Term; ?> Syllabus">
	<style type="text/css">
		body {font-family:Helvetica;}
		ul,ol {margin:auto;padding:0;}
	</style>
</head>

<body>
	<?php
	
			// pull course info and page content
			$query = "SELECT a.`course_name`, a.`course_code`
					  FROM `courses` AS a
					  WHERE a.`course_id` = ".$db->qstr($course_id);
			
			$course_details = $db->GetRow($query);
		
			$query = "SELECT LOWER(REPLACE(c.`menu_title`, ' ', '_')), c.`page_title`, c.`page_content`
					  FROM `courses`			AS a
					  JOIN `community_courses`	AS b
					  ON a.`course_id` = b.`course_id`
					  JOIN `community_pages`	AS c
					  ON b.`community_id` = c.`community_id`
					  WHERE a.`course_id` = ".$db->qstr($course_id)."
					  AND c.`menu_title` IN ('".implode("', '",$pages)."')";
			
			$course_details["pages"] = $db->GetAssoc($query);

			echo "<h1>".$course_details["course_name"]." - ".$course_details["course_code"]."</h1>";
			
			// Background Information
			if (isset($course_details["pages"]["background"]["page_content"]) && !empty($course_details["pages"]["background"]["page_content"])) {
				echo "<h3>".$course_details["pages"]["background"]["page_title"]."</h3>";
				echo "<div>".$course_details["pages"]["background"]["page_content"]."</div>";
			}
			
			// Course Aims
			if (isset($course_details["pages"]["course_aims"]["page_content"]) && !empty($course_details["pages"]["course_aims"]["page_content"])) {
				echo "<h3>".$course_details["pages"]["course_aims"]["page_title"]."</h3>";
				echo "<div>".$course_details["pages"]["course_aims"]["page_content"]."</div>";
			}
			
			// Course Contacts
			
			$query = "	SELECT a.`contact_type`, a.`contact_order`, b.`prefix`, b.`firstname`, b.`lastname`, b.`email`, b.`telephone`, b.`fax`, b.`address`, b.`city`, b.`province`, b.`postcode`, b.`country`, b.`office_hours`
						FROM medtech_central.`course_contacts` AS a 
						JOIN medtech_auth.`user_data` AS b 
						ON a.`contact_id` = b.`id`  
						WHERE a.`course_id` = ".$db->qstr($course_id)."
						ORDER BY a.`contact_type` DESC, a.`contact_order` ASC";
			$results = $db->GetAll($query);
			if ($results) {
				echo "<h3>Course Contacts</h3>";
				foreach ($results as $result) {
					echo ($result["contact_type"] == "director" ? "<p><strong>Course Director</strong></p>" : "<p><strong>Course Coordinator</strong></p>");
					echo $result["prefix"]." ".$result["firstname"]." ".$result["lastname"]." &lt;".$result["email"]."&gt;<br />";
					echo (!empty($result["telephone"]) ? "Telephone: ".$result["telephone"]."<br />" : "");
					echo (!empty($result["fax"]) ? "Fax: ".$result["fax"]."<br />" : "");
					echo (!empty($result["address"]) ? $result["address"]."<br />" : "");
					echo (!empty($result["city"]) ? $result["city"] : "");
					echo (!empty($result["province"]) ? ", ".$result["province"] : "");
					echo (!empty($result["country"]) ? ", ".$result["country"] : "");
					echo (!empty($result["address"]) ? "<br />\n" : "");
					echo (!empty($result["postcode"]) ? $result["postcode"]."<br />" : "");
					echo (!empty($result["office_hours"]) ? $result["office_hours"]."<br />" : "");
				}
			}
			
			// Curricular Objectives
			list($objectives, $top_level_id) = courses_fetch_objectives(1, array($course_id));
			$objectives_formatted = course_objectives_formatted($objectives, $top_level_id,$top_level_id, false, false, 1, false, true, "primary", false, false, "1");
			if (isset($objectives_formatted) && !empty($objectives_formatted)) {
				echo "<h1>Course Objectives:</h1>";
				echo "<div id=\"objectives_list\">\n".$objectives_formatted."\n</div>\n";
			}
			
			// MCC Presentations
			$query = "	SELECT b.*
						FROM `course_objectives` AS a
						JOIN `global_lu_objectives` AS b
						ON a.`objective_id` = b.`objective_id`
						JOIN `objective_organisation` AS c
						ON b.`objective_id` = c.`objective_id`
						WHERE a.`objective_type` = 'event'
						AND a.`course_id` = ".$db->qstr($course_id)." 
						AND b.`objective_active` = 1
						AND c.`organisation_id` = 1
						GROUP BY b.`objective_id`
						ORDER BY b.`objective_order`";
			$results = $db->GetAll($query);
			if ($results) {
				
				echo "<h1>MCC Presentations</h1>";
				foreach ($results as $result) {
					if ($result["objective_name"]) {
						echo "&nbsp;&nbsp;".$result["objective_name"]."<br />\n";
					}
				}
			}
			
			// Teaching Stratagies
			if (isset($course_details["pages"]["teaching_strategies"]["page_content"]) && !empty($course_details["pages"]["teaching_strategies"]["page_content"])) {
				echo "<h2>".$course_details["pages"]["teaching_strategies"]["page_title"]."</h2>";
				echo "<div>".$course_details["pages"]["teaching_strategies"]["page_content"]."</div>";
			}
			
			// Assessment Stratagies
			
			if (isset($course_details["pages"]["assessment_strategies"]["page_content"]) && !empty($course_details["pages"]["assessment_strategies"]["page_content"])) {
				echo "<h2>".$course_details["pages"]["assessment_strategies"]["page_title"]."</h2>";
				echo "<div>".strip_tags($course_details["pages"]["assessment_strategies"]["page_content"],"<strong><br><ul><ol><li><table><tr><td><p>")."</div>";
			}
			
			// Gradebook
			$query =  "SELECT `assessments`.`course_id`, `assessments`.`assessment_id`, `assessments`.`name`, `assessments`.`grade_weighting`, `assessments`.`order` FROM `assessments`
						WHERE `cohort` = " . $db->qstr($cohort)."
						AND `course_id` = ". $db->qstr($course_id)."
						ORDER BY `order` ASC";
			
			$results = $db->GetArray($query);
			if ($results) {
				echo "<h1>Gradebook</h1>";
				echo "<table>";
				$query =  "SELECT `assessments`.`course_id`, SUM(`assessments`.`grade_weighting`) AS `grade_weighting` FROM `assessments`
							WHERE `cohort` =". $db->qstr($cohort)." 
							AND `course_id` =". $db->qstr($course_id);

				$total_grade_weights = $db->GetAll($query);
				foreach ($results as $result) {
					$url = ENTRADA_URL."/admin/gradebook/assessments?section=grade&amp;id=".$COURSE_ID."&amp;assessment_id=".$result["assessment_id"];
					echo "<tr id=\"assessment-".$result["assessment_id"]."\" class=\"assessment\">";
					echo "	<td class=\"modified\" width=\"20\"><input type=\"hidden\" name=\"order[".$result["assessment_id"]."][]\" value=\"sortorder\" class=\"order\" /><img src=\"".ENTRADA_URL."/images/pixel.gif\" width=\"19\" height=\"19\" alt=\"\" title=\"\" /></td>";
					echo "<td>".$result["name"]."</td>";
					echo "<td colspan=\"2\">&nbsp;&nbsp;&nbsp;".$result["grade_weighting"]. "%</td>"; 
					echo "</tr>";
				}
				echo "<tr>";
				echo "<td style=\"border-bottom: 0\"></td>";
				echo "<td style=\"border-bottom: 0\"></td>";
				foreach ($total_grade_weights as $total_grade_weight) {
					if ($total_grade_weight["grade_weighting"] < '100') {
						echo "<td style=\"color: #ff2431; border-bottom: 0\">". $total_grade_weight["grade_weighting"]."%</td>";
					} else {
						echo "<td style=\"border-bottom: 0\">&nbsp;&nbsp;&nbsp;". $total_grade_weight["grade_weighting"]."%</td>";
					}
				}
				echo "</tr>";
				echo "</table>";
			}
			
			
			// Resources
			if (isset($course_details["pages"]["resources"]["page_content"]) && !empty($course_details["pages"]["resources"]["page_content"])) {
				echo "<h2>".$course_details["pages"]["resources"]["page_title"]."</h2>";
				echo "<div>".strip_tags($course_details["pages"]["resources"]["page_content"],"<strong><br><ul><ol><li><table><tr><td><p>")."</div>";
			}
			
			// Expectations of Students
			if (isset($course_details["pages"]["expectations_of_students"]["page_content"]) && !empty($course_details["pages"]["expectations_of_students"]["page_content"])) {
				echo "<h2>".$course_details["pages"]["expectations_of_students"]["page_title"]."</h2>";
				echo "<div>".$course_details["pages"]["expectations_of_students"]["page_content"]."</div>";
			}
			
			// Expectations of Faculty
			if (isset($course_details["pages"]["expectations_of_faculty"]["page_content"]) && !empty($course_details["pages"]["expectations_of_faculty"]["page_content"])) {
				echo "<h2>".$course_details["pages"]["expectations_of_faculty"]["page_title"]."</h2>";
				echo "<div>".$course_details["pages"]["expectations_of_faculty"]["page_content"]."</div>";
			}
			
			
			// Event Types By Course Report Start
			
			$output		= array();
			$appendix	= array();

			$courses_included	= array();
			$eventtype_legend	= array();

			$query = "	SELECT a.* FROM `events_lu_eventtypes` AS a 
						LEFT JOIN `eventtype_organisation` AS c 
						ON a.`eventtype_id` = c.`eventtype_id` 
						LEFT JOIN `".AUTH_DATABASE."`.`organisations` AS b
						ON b.`organisation_id` = c.`organisation_id` 
						WHERE b.`organisation_id` = '1'
						AND a.`eventtype_active` = '1' 
						ORDER BY a.`eventtype_order`
				";
			$event_types = $db->GetAll($query);
			if ($event_types) {
				foreach ($event_types as $event_type) {
					$eventtype_legend[$event_type["eventtype_id"]] = $event_type["eventtype_title"];

					$query = "	SELECT a.`event_id`, b.`course_name`, a.`event_title`, a.`event_start`, c.`duration`, d.`eventtype_title`
								FROM `events` AS a
								LEFT JOIN `courses` AS b
								ON b.`course_id` = a.`course_id`
								LEFT JOIN `event_eventtypes` AS c
								ON c.`event_id` = a.`event_id`
								LEFT JOIN `events_lu_eventtypes` AS d
								ON d.`eventtype_id` = c.`eventtype_id`
								WHERE c.`eventtype_id` = ".$db->qstr($event_type["eventtype_id"])."
								AND (a.`parent_id` IS NULL OR a.`parent_id` = 0)
								AND (a.`event_start` BETWEEN ".$db->qstr($start_date)." AND ".$db->qstr($end_date).")
								".(isset($event_title_search) && $event_title_search ? "AND a.`event_title` LIKE ".$db->qstr("%".$event_title_search."%") : "")."
								AND a.`course_id` = ".$db->qstr($course_id)."
								ORDER BY d.`eventtype_order` ASC, b.`course_name` ASC, a.`event_start` ASC";
					//echo $query;
					$results = $db->GetAll($query);
					if ($results) {
						$courses_included[$course_id] = $course_list[$course_id]["code"] . " - " . $course_list[$course_id]["name"];

						foreach ($results as $result) {
							$output[$course_id]["events"][$event_type["eventtype_id"]]["duration"] += $result["duration"];
							$output[$course_id]["events"][$event_type["eventtype_id"]]["events"] += 1;

							$appendix[$course_id][$result["event_id"]][] = $result;
						}

						$output[$course_id]["total_duration"] += $output[$course_id]["events"][$event_type["eventtype_id"]]["duration"];
						$output[$course_id]["total_events"] += $output[$course_id]["events"][$event_type["eventtype_id"]]["events"];
					}
				}
			}

			if (count($output)) {
				echo "<h1>Learning Event Types</h1>";
				foreach ($output as $course_id => $result) {
					$STATISTICS					= array();
					$STATISTICS["labels"]		= array();
					$STATISTICS["legend"]		= array();
					$STATISTICS["results"]		= array();
					?>
					<table width="70%">
					
					
						<tr>
							<td class="modified">&nbsp;</td>
							<td class="title"><strong>Event Type</strong></td>
							<td class="report-hours large"><strong>Event Count</strong></td>
							<td class="report-hours large"><strong>Hour Count</strong></td>
						</tr>
					
					<?php				
					foreach ($result["events"] as $eventtype_id => $event) {
						$STATISTICS["labels"][$eventtype_id] = $eventtype_legend[$eventtype_id];
						$STATISTICS["legend"][$eventtype_id] = $eventtype_legend[$eventtype_id];
						$STATISTICS["display"][$eventtype_id] = $event["events"];

						if ($result["total_events"] > 0) {
							$percent_events = round((($event["events"] / $result["total_events"]) * 100));
						} else {
							$percent_events = 0;
						}

						if ($result["total_duration"] > 0) {
							$percent_duration = round((($event["duration"] / $result["total_duration"]) * 100));
						} else {
							$percent_duration = 0;
						}

						echo "<tr>\n";
						echo "	<td>&nbsp;</td>\n";
						echo "	<td>".html_encode($eventtype_legend[$eventtype_id])."</td>\n";
						echo "	<td class=\"report-hours large\" style=\"text-align: left\">".$event["events"]." (~ ".$percent_events."%)</td>\n";
						echo "	<td class=\"report-hours large\" style=\"text-align: left\">".display_hours($event["duration"])." hrs (~ ".$percent_duration."%)</td>\n";
						echo "</tr>\n";
					}
					?>
					
						<tr class="na">
							<td>&nbsp;</td>
							<td><br /><strong>Event Type Totals</strong></td>
							<td class="report-hours large"><br /><strong><?php echo $result["total_events"]; ?></strong></td>
							<td class="report-hours large"><br /><strong><?php echo display_hours($result["total_duration"]); ?> hrs</strong></td>
						</tr>
					
					</table>
					<?php
				}
			}
			// Event Types by Course Report End
			
			
			
			// Course Summary Report Start
			$output		= array();
			$appendix	= array();

			$courses_included	= array();
			$eventtype_legend	= array();
			$eventtype_legend[$event_type["eventtype_id"]] = $event_type["eventtype_title"];

			$presentation_ids = array();
			$mcc_presentations = fetch_clinical_presentations(0, array(), 0, false, 1);
			if ($mcc_presentations) {
				foreach ($mcc_presentations as $mcc_presentation) {
					$presentation_ids[] = $mcc_presentation["objective_id"];
				}
			}
			
			$query = "	SELECT a.`event_id`, b.`course_name`, b.`organisation_id`, a.`event_title`, a.`event_description`
						FROM `events` AS a
						LEFT JOIN `courses` AS b
						ON b.`course_id` = a.`course_id`
						WHERE (a.`event_start` BETWEEN ".$db->qstr($start_date)." AND ".$db->qstr($end_date).")
						AND a.`course_id` = ".$db->qstr($course_id).
						" AND (b.`organisation_id` = 1) 
						AND (a.`parent_id` IS NULL OR a.`parent_id` = '0')
						ORDER BY a.`event_start` ASC";

			$results = $db->CacheGetAll(LONG_CACHE_TIMEOUT, $query);

			if ($results) {
				echo "<h1>Course Summary</h1>";

				$courses_included[$course_id] = $course_list[$course_id]["code"] . " - " . $course_list[$course_id]["name"];

				foreach ($results as $result) {
					$output[$course_id][] = $result;

					if (!isset($appendix[$course_id][$result["event_id"]])) {
						$appendix[$course_id][$result["event_id"]] = $result;
					}

					$query = "SELECT b.`objective_id`, b.`objective_name`
								FROM `event_objectives` AS a
								JOIN `objective_organisation` AS oo
								ON oo.`organisation_id` = ".$db->qstr($result["organisation_id"])."
								JOIN `global_lu_objectives` AS b
								ON b.`objective_id` = a.`objective_id`
								AND b.`objective_active` = 1
								AND b.`objective_id` = oo.`objective_id`
								WHERE a.`event_id` = ".$db->qstr($result["event_id"]);
					$objectives = $db->GetAll($query);
					if ($objectives) {
							foreach ($objectives as $objective) {
							// This means it's an MCC Presentation. Don't judge me.
							if (in_array($objective["objective_id"], $presentation_ids)) {
								$appendix[$course_id][$result["event_id"]]["presentations"][$objective["objective_id"]] = $objective["objective_name"];
							} else {
								$appendix[$course_id][$result["event_id"]]["objectives"][$objective["objective_id"]] = $objective["objective_name"];
							}
						}
					}
				}
			}
			
			if (count($output)) {
				foreach ($output as $course_id => $result) {
					$total_duration = 0;
					if ($appendix[$course_id]) {						
						foreach ($appendix[$course_id] as $event_id => $event) {
							if (isset($event["objectives"]) && is_array($event["objectives"])) {
								asort($event["objectives"]);
							}

							$objectives = array();
							if ($event["objectives"]) {
								foreach ($event["objectives"] as $value) {
									$firstpart = substr($value, 0, (strlen($value) - 1));
									$letter = substr($value, -1);

									if (!isset($objectives[$firstpart])) {
										$objectives[$firstpart] = $firstpart . $letter;
									} else {
										$objectives[$firstpart] .= ", " . $letter;
									}
								}
							}
							echo "<div>\n";
							echo "<strong>".html_encode($event["event_title"])."</strong>";
							echo (!empty($event["objectives"]) ? "<br />\nObjectives: ".implode(" - ", $objectives)."<br />" : "&nbsp;")."\n";
							echo (!empty($event["event_description"]) ? "<div><br />".$event["event_description"]."</div>\n" : "" );
							echo "</div><br />";
						}
					}
				}
			}
			// Course Summary Report End
			
			
			
		?>
			<br />
	<?php	
				/*}
			}
		}*/
	?>
</body>
</html>