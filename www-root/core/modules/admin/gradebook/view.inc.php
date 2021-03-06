<?php
/**
 * Entrada [ http://www.entrada-project.org ]
 *
 * Entrada is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Entrada is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Entrada.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Harry Brundage <hbrundage@qmed.ca>
 * @copyright Copyright 2010 Queen's University. All Rights Reserved.
 *
 */

if ((!defined("PARENT_INCLUDED")) || (!defined("IN_GRADEBOOK"))) {
	exit;
} elseif ((!isset($_SESSION["isAuthorized"])) || (!$_SESSION["isAuthorized"])) {
	header("Location: ".ENTRADA_URL);
	exit;
} elseif (!$ENTRADA_ACL->amIAllowed("gradebook", "update", false)) {
	$ONLOAD[]	= "setTimeout('window.location=\\'".ENTRADA_URL."/admin/".$MODULE."\\'', 15000)";

	$ERROR++;
	$ERRORSTR[]	= "Your account does not have the permissions required to use this feature of this module.<br /><br />If you believe you are receiving this message in error please contact <a href=\"mailto:".html_encode($AGENT_CONTACTS["administrator"]["email"])."\">".html_encode($AGENT_CONTACTS["administrator"]["name"])."</a> for assistance.";

	echo display_error();

	application_log("error", "Group [".$_SESSION["permissions"][$ENTRADA_USER->getAccessId()]["group"]."] and role [".$_SESSION["permissions"][$ENTRADA_USER->getAccessId()]["role"]."] does not have access to this module [".$MODULE."]");
} else {
	if ($COURSE_ID) {
        $selected_audience_name = false;
		/**
		 * Handles the AJAX re-ordering of assessments.
		 */
		if (isset($_POST["mode"]) && ($_POST["mode"] == "ajax") && isset($_POST["order"]) && is_array($_POST["order"]) && !empty($_POST["order"])) {
			ob_clear_open_buffers();

			foreach ($_POST["order"] as $assessment_id => $order) {
				$order = (int) $order[0];

				$query = "UPDATE `assessments` SET `order` = ".$db->qstr($order)."
				            WHERE `course_id` = ".$db->qstr($COURSE_ID)."
				            AND `assessment_id` = ".$db->qstr((int) $assessment_id);
				if($db->Execute($query)) {
					$error = false;
					application_log("success", "Updated gradebook assessment [".$assessment_id."] to order [".$order."].");
				} else {
					$error = true;
					application_log("error", "Failed to update assessment [".$assessment_id."] to order [".$order."]. Database said: ".$db->ErrorMsg());
				}
			}

			echo ($error == false ? 1 : 0);

			exit;
		}

		$query = "	SELECT * FROM `courses`
					WHERE `course_id` = ".$db->qstr($COURSE_ID)."
					AND `course_active` = '1'";
		$course_details	= $db->GetRow($query);
		if ($course_details && $ENTRADA_ACL->amIAllowed(new GradebookResource($course_details["course_id"], $course_details["organisation_id"]), "read")) {
			$BREADCRUMB[] = array("url" => ENTRADA_URL."/admin/gradebook?".replace_query(array("section" => "view", "id" => $COURSE_ID, "step" => false)), "title" => "Assessments");

			/**
			 * Update requested column to sort by.
			 * Valid: director, name
			 */
			if (isset($_GET["sb"])) {
				if (@in_array(trim($_GET["sb"]), array("name", "type", "scheme"))) {
					$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"]	= trim($_GET["sb"]);
				} else {
					$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"] = "name";
				}

				$_SERVER["QUERY_STRING"]	= replace_query(array("sb" => false));
			} else {
				if (!isset($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"])) {
					$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"] = "name";
				}
			}

			/**
			 * Update requested order to sort by.
			 * Valid: asc, desc
			 */
			if (isset($_GET["so"])) {
				$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"] = ((strtolower($_GET["so"]) == "desc") ? "desc" : "asc");

				$_SERVER["QUERY_STRING"] = replace_query(array("so" => false));
			} else {
				if (!isset($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"])) {
					$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"] = "asc";
				}
			}

			/**
			 * Update requsted number of rows per page.
			 * Valid: any integer really.
			 */
			if ((isset($_GET["pp"])) && ((int) trim($_GET["pp"]))) {
				$integer = (int) trim($_GET["pp"]);

				if (($integer > 0) && ($integer <= 250)) {
					$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"] = $integer;
				}

				$_SERVER["QUERY_STRING"] = replace_query(array("pp" => false));
			} else {
				if (!isset($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"])) {
					$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"] = DEFAULT_ROWS_PER_PAGE;
				}
			}

			/**
			 * Check if preferences need to be updated on the server at this point.
			 */
			preferences_update($MODULE, $PREFERENCES);

			/**
			 * Check if cohort variable is set, otherwise a default is used.
			 */
			if (isset($_GET["cohort"]) && ((int)$_GET["cohort"])) {
				$selected_cohort = (int) $_GET["cohort"];
			} elseif (isset($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["cohort"]) && $_SESSION[APPLICATION_IDENTIFIER][$MODULE]["cohort"]) {
                $selected_cohort = $_SESSION[APPLICATION_IDENTIFIER][$MODULE]["cohort"];
            }
			
			if (isset($_GET["course_list"]) && ((int)$_GET["course_list"])) {
				$selected_classlist = (int) $_GET["course_list"];
			} elseif (isset($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["course_list"]) && $_SESSION[APPLICATION_IDENTIFIER][$MODULE]["course_list"]) {
                $selected_classlist = $_SESSION[APPLICATION_IDENTIFIER][$MODULE]["course_list"];
            }
			if (isset($selected_cohort) && $selected_cohort) {
				$query	= "	SELECT COUNT(*) AS `total_rows` 
							FROM `assessments` a
							JOIN `groups` AS b
							ON a.`cohort` = b.`group_id`
							JOIN `group_organisations` AS c
							ON b.`group_id` = c.`group_id`
							AND c.`organisation_id` = ".$db->qstr($ENTRADA_USER->getActiveOrganisation())."
							AND b.`group_active` = 1
							AND b.`group_id` = " . $db->qstr($selected_cohort) . "
							WHERE a.`course_id` = ".$db->qstr($COURSE_ID)."
							AND a.`active` = '1'";
				$result	= $db->GetRow($query);
			} 
			if (isset($result) && $result) {
				$total_rows	= $result["total_rows"];

				if ($total_rows <= $_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"]) {
					$total_pages = 1;
				} elseif (($total_rows % $_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"]) == 0) {
					$total_pages = (int) ($total_rows / $_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"]);
				} else {
					$total_pages = (int) ($total_rows / $_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"]) + 1;
				}
			} else {
				$total_rows = 0;
				$total_pages = 1;
			}

			/**
			 * Check if pv variable is set and see if it's a valid page, other wise page 1 it is.
			 */
			if (isset($_GET["pv"])) {
				$page_current = (int) trim($_GET["pv"]);

				if (($page_current < 1) || ($page_current > $total_pages)) {
					$page_current = 1;
				}
			} else {
				$page_current = 1;
			}

			if ($total_pages > 1) {
				$pagination = new Pagination($page_current, $_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"], $total_rows, ENTRADA_URL."/admin/".$MODULE, replace_query());
			}

			$limit_parameter = (int) (($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"] * $page_current) - $_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"]);

            courses_subnavigation($course_details,"gradebook");
			$curriculum_path = curriculum_hierarchy($COURSE_ID);
			if ((is_array($curriculum_path)) && (count($curriculum_path))) {
                echo "  <div class=\"row-fluid\">";
                echo "      <div class=\"span12\">";
				echo "          <h1>" . implode(": ", $curriculum_path) . " Gradebook </h1>";
                echo "      </div>";
                echo "  </div>";
			}

            echo "  <br />\n";

            echo "<div class=\"row-fluid\">\n";
			
			$query = "	SELECT * 
						FROM `groups` 
						WHERE `group_type` = 'course_list' 
						AND `group_value` = ".$db->qstr($COURSE_ID)." 
						AND `group_active` = '1'
						ORDER BY `group_name`";
			$course_lists = $db->GetAll($query);			
			if ($course_lists) { 		
				$cohorts = $course_lists;
				if (count($course_lists) == 1) {										
					$output_cohort = $course_lists[0];
					$selected_cohort = $output_cohort["group_id"];
					?>
					<h2 class="pull-left"><?php echo $course_list["group_name"];?></h2>				
		            <?php
				} else {
					$output_cohort = false;
					$classlist_found = false;
					foreach ($course_lists as $key => $course_list) {
						if (!$classlist_found) {
							$output_cohort = $course_list;
							if (isset($selected_classlist) && $selected_classlist && $selected_classlist == $course_list["group_id"]) {
								$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["course_list"] = $selected_classlist;
								$classlist_found = true;
							}
							if ($key == (count($course_lists) - 1) && !$classlist_found) {
								$selected_classlist = $course_list["group_id"];
							}
						}
					} 
                    if (!empty($course_lists)) {
                        ?>
                        <div class="span12 clearfix">
                            <h2 class="pull-left"><?php echo $output_cohort["group_name"];?></h2>
                            <form class="pull-right form-horizontal" style="margin-bottom:0;">
                                <div class="control-group">
                                    <label for="course_list-quick-select" class="control-label content-small">
                                        Target Audience:
                                    </label>
                                    <div class="controls">
                                        <select id="course_list-quick-select" name="course_list-quick-select" onchange="window.location='<?php echo ENTRADA_URL;?>/admin/gradebook?section=view&id=<?php echo $COURSE_ID;?>&course_list='+this.options[this.selectedIndex].value">
                                            <?php
                                            foreach ($course_lists as $key => $course_list) { ?>
                                                <option value="<?php echo $course_list["group_id"];?>" <?php echo (($course_list["group_id"] == $selected_classlist) ? "selected=\"selected\"" : "");?>>
                                                    <?php echo $course_list["group_name"];?>
                                                </option>
                                                <?php
                                                $selected_audience_name = $course_list["group_name"];
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <?php
                    }
				}
			} else {
				$query =  "SELECT a.`course_id`, b.`group_name`, b.`group_id` 
							FROM `assessments` AS a
							JOIN `groups` AS b
							ON a.`cohort` = b.`group_id`
							JOIN `group_organisations` AS c
							ON b.`group_id` = c.`group_id`
							WHERE a.`course_id` =". $db->qstr($COURSE_ID)."
							AND c.`organisation_id` = ".$db->qstr($ENTRADA_USER->getActiveOrganisation())."
							AND a.`active` = '1'
							GROUP BY b.`group_id`
							ORDER BY b.`group_name`";
				$cohorts = $db->GetAll($query);

                $output_cohort = false;
                $cohort_found = false;
                if ($cohorts) {
                    foreach ($cohorts as $key => $cohort) {
                        if (!$cohort_found) {
                            $output_cohort = $cohort;
                            if (isset($selected_cohort) && $selected_cohort && $selected_cohort == $cohort["group_id"]) {
                                $_SESSION[APPLICATION_IDENTIFIER][$MODULE]["cohort"] = $selected_cohort;
                                $cohort_found = true;
                            }
                            if ($key == (count($cohorts) - 1) && !$cohort_found) {
                                $selected_cohort = $cohort["group_id"];
                            }
                        }
                    }
                }
                if (!empty($cohorts)) {
                    ?>
                    <div class="span12 clearfix">
                        <h2 class="pull-left"><?php echo $output_cohort["group_name"];?></h2>
                        <form class="pull-right form-horizontal" style="margin-bottom:0;">
                            <div class="control-group">
                                <label for="cohort-quick-select" class="control-label content-small">
                                    Target Audience:
                                </label>
                                <div class="controls">
                                    <select id="cohort-quick-select" name="cohort-quick-select" onchange="window.location='<?php echo ENTRADA_URL;?>/admin/gradebook?section=view&id=<?php echo $COURSE_ID;?>&cohort='+this.options[this.selectedIndex].value">
                                        <?php
                                        foreach ($cohorts as $key => $cohort) {
                                            ?>
                                            <option value="<?php echo $cohort["group_id"];?>" <?php echo (($cohort["group_id"] == $selected_cohort) ? "selected=\"selected\"" : "");?>>
                                                <?php echo $cohort["group_name"];?>
                                            </option>
                                            <?php
                                            if ($cohort["group_id"] == $selected_cohort) {
                                                $selected_audience_name = $cohort["group_name"];
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                    <?php
                }
            }
            echo "  </div>\n";
            if ($ENTRADA_ACL->amIAllowed("gradebook", "create", false) && $ENTRADA_ACL->amIAllowed(new CourseContentResource($COURSE_ID, $course_details["organisation_id"]), "update")) { 
                ?>
                <div class="pull-right">
                    <a id="gradebook_assessment_add" href="<?php echo ENTRADA_URL; ?>/admin/<?php echo $MODULE . "/assessments/?" . replace_query(array("section" => "add", "step" => false)); ?>" class="btn btn-primary">Add New Assessment</a>
                </div>
                <div style="clear: both"></div>
                <?php
            }
			if($cohorts) {
				if ($total_pages > 1) {
					echo "<div id=\"pagination-links\">\n";
					echo "	Pages: ".$pagination->GetPageLinks();
					echo "</div>\n";
				}
				if ($ENTRADA_ACL->amIAllowed("gradebook", "delete", false)) {
					echo "<form action=\"".ENTRADA_URL . "/admin/gradebook/assessments?".replace_query(array("section" => "delete", "step" => 1, "cohort" => (isset($selected_cohort) && $selected_cohort ? $selected_cohort : (isset($selected_classlist) && $selected_classlist ? $selected_classlist : NULL))))."\" method=\"post\">";
				}

                $HEAD[] = "<script type=\"text/javascript\" src=\"".ENTRADA_RELATIVE."/javascript/wizard.js?release=".html_encode(APPLICATION_VERSION)."\"></script>";
                $HEAD[] = "<link href=\"".ENTRADA_URL."/css/wizard.css?release=".html_encode(APPLICATION_VERSION)."\" rel=\"stylesheet\" type=\"text/css\" media=\"all\" />";
				?>
                <iframe id="upload-frame" name="upload-frame" onload="frameLoad()" style="display: none;"></iframe>
                <a id="false-link" href="#placeholder"></a>
                <div id="placeholder" class="modalStats" style="display: none"></div>
                <script type="text/javascript">
                    jQuery(document).ready(function(){
                        jQuery('.edit_grade').live('click',function(e){
                            var id = e.target.id.substring(5);
                            jQuery('#'+id).trigger('click');
                        });
                        
						jQuery('#export-grades').live('click',function(){
	                        var ids = [];
	                        jQuery('#assessment_list .modified input:checked').each(function() {
	                            ids.push(jQuery(this).val());
	                        });
	                        if(ids.length > 0) {
	                            window.location = '<?php echo ENTRADA_URL."/admin/".$MODULE."?".replace_query(array("section" => "io", "download" => "csv", "assessment_ids" => false, "cohort" => (isset($selected_cohort) && $selected_cohort ? $selected_cohort : "0"))); ?>&assessment_ids='+ids.join(',');
	                        } else {
	                            var cohort = jQuery('#cohort-quick-select').val();
	                            window.location = '<?php echo ENTRADA_URL."/admin/".$MODULE."?".replace_query(array("section" => "io", "download" => "csv", "assessment_ids" => false)); ?>&cohort='+cohort;
	                        }
	                        return false;
	                    });                          

                        var reordering = false;
                        var orderChanged = false;

                        jQuery('#reorder').click(function(){
                            jQuery('.ordermsg').remove();
                            if (reordering == false) {
                                jQuery('#saveorder').show();
                                jQuery('#delete, #export').hide();

                                jQuery('#assessment_list tbody tr td.modified .delete').hide();
                                jQuery('#assessment_list tbody tr td.modified').append('<span class="handle"></span>');
                                jQuery('#assessment_list tbody').sortable({
                                    items: '.assessment',
                                    containment: 'parent',
                                    handle: '.handle',
                                    change: function(event,ui){
                                        orderChanged = true;
                                    }
                                });
                                reordering = true;
                                jQuery('#reorder').attr('value', 'Cancel Reorder');
                                jQuery('.display-success, .display-error').fadeOut(500,function(){
                                    $(this).remove();
                                });
                            } else {
                                jQuery('#saveorder').hide();
                                jQuery('#assessment_list tbody tr td.modified .handle').remove();
                                jQuery('#assessment_list tbody tr td.modified .delete').show();
                                jQuery('#reorder').attr('value', 'Reorder');
                                reordering = false;
                                jQuery('#delete, #export').show();
                                if (orderChanged == true) {
                                    // if you try to cancel the sortable and the order hasn't changed javascript breaks.
                                    jQuery('#assessment_list tbody').sortable('cancel').sortable('destroy');
                                } else {
                                    jQuery('#assessment_list tbody').sortable('destroy');
                                }
                            }
                            return false;
                        });

                        jQuery('#saveorder').click(function(){
                            jQuery('.ordermsg').remove();

                            // assign order to assessment
                            jQuery('#assessment_list tbody tr td.modified .order').each(function(){
                                jQuery(this).attr('value',jQuery(this).parent().parent().index()-1);
                            });

                            // serialize the form data to pass to the ajax updater
                            var formData = jQuery('#assessment_list').parent().serialize();

                            var ajaxParams = 'mode=ajax&'+formData;
                            var ajaxURL = '<?php echo ENTRADA_RELATIVE; ?>/admin/gradebook?section=view&id=<?php echo $COURSE_ID; ?>';

                            jQuery.ajax({
                                data: ajaxParams,
                                url: ajaxURL,
                                type: 'POST',
                                success: function(data) {
                                    if (data == 1) {
                                        jQuery('#assessment_list').parent().append('<div class=\'display-success\'><ul><li>These assessment order have been reordered.</li></ul></div>');
                                    } else {
                                        jQuery('#assessment_list').parent().append('<div class=\'display-error\'><ul><li>An error occurred while reordering these assessments.</li></ul></div>');
                                    }
                                }
                            });

                            reordering = false;

                            jQuery(this).hide();
                            jQuery('#assessment_list tbody tr td .handle').remove();
                            jQuery('#assessment_list tbody tr td.modified .delete').show();
                            jQuery('#reorder').attr('value', 'Reorder');
                            jQuery('#assessment_list tbody').sortable('destroy');
                            jQuery('#delete, #export').show();
                        });

                        jQuery('#copyAssessments').on("submit", function () {
                            jQuery('input.delete:checked').each(function () {
                                jQuery('#copy_assessment_ids').append(jQuery('<input type="hidden" name="assessment_ids[]" id="copy_assessment_' + jQuery(this).val() + '" value="' + jQuery(this).val() + '" />'));
                            });
                        });

                        jQuery('#copy-assessments-confirmation-box').modal({show: false});

                        jQuery('#copy-assessments-confirmation-box').on("shown", function () {
                            if (jQuery('input.delete:checked').length < 1) {
                                jQuery('#copy-assessments-confirmation-box .modal-notice').show();
                                jQuery('#copy-assessments-confirmation-box .modal-content').hide();
                                jQuery('#copy-assessments-confirmation-box .submit-button').attr("disabled", "disabled");
                            } else {
                                jQuery('#copy-assessments-confirmation-box .modal-notice').hide();
                                jQuery('#copy-assessments-confirmation-box .modal-content').show();
                                jQuery('#copy-assessments-confirmation-box .submit-button').removeAttr("disabled");
                            }
                        });

                        jQuery('#assessments-control-copy').on('click', function () {
                            jQuery('#copy-assessments-confirmation-box').modal('show');
                        });
                    });                   
                    var ajax_url = '';
                    var modalDialog;
                    document.observe('dom:loaded', function() {
                        modalDialog = new Control.Modal($('false-link'), {
                            position:		'center',
                            overlayOpacity:	0.75,
                            closeOnClick:	'overlay',
                            className:		'modal',
                            fade:			true,
                            fadeDuration:	0.30,
                            beforeOpen: function(request) {
                                eval($('scripts-on-open').innerHTML);
                            },
                            afterClose: function() {
                                if (uploaded == true) {
                                                                location.reload();
                                }
                            }
                        });
                    });

                    function openDialog (url) {
                        if (url) {
                            ajax_url = url;
                            new Ajax.Request(ajax_url, {
                                method: 'get',
                                onComplete: function(transport) {
                                    modalDialog.container.update(transport.responseText);
                                    modalDialog.open();
                                }
                            });
                        } else {
                            $('scripts-on-open').update();
                            modalDialog.open();
                        }
                    }
                </script>
                <br />
				<table class="tableList" cellspacing="0" summary="List of Assessments" id="assessment_list">
					<tfoot>
						<tr>
							<td style="padding-top: 10px; border-bottom:0;" colspan="2">
								<input type="submit" class="btn btn-danger" id="delete" value="Delete Selected" />								
								<a class="btn" role="button" id="assessments-control-copy"><i class="icon-share"></i> Copy Selected</a>
								<input type="button" class="btn" id="reorder" value="Reorder" />
								<input type="button" class="btn btn-primary" id="saveorder" value="Save Order" />
							</td>
							<td style="padding-top: 10px; border-bottom: 0; text-align:right;" colspan="2">
								<input type="button" id="fullscreen-edit" class="btn" data-href="<?php echo ENTRADA_URL . "/admin/gradebook?" . replace_query(array("section" => "api-edit")); ?>" value="Grade Spreadsheet" />
								<input type="button" id="export-grades" class="btn" value="Export Grades"/>
							</td>
						</tr>
						<tr>
							<td style="border-bottom:0;"></td>
						</tr>
					</tfoot>
					<tbody>
					<?php
					if ($cohorts) {
                        if ($output_cohort) {
							echo "<tr>";
							echo "<td style=\"width: 20px;\"></td>";
							echo "<td style=\"width: 300px;\"><h3 style=\"border-bottom: 0;\">Assessment</h3></td>";
							echo "<td><h3 style=\"border-bottom: 0;\">Grade Weighting</h3></td>";
							echo "<td><h3 style=\"border-bottom: 0;\">Assignment</h3></td>";
                            echo "<td class=\"assessment_col_5\"><h3>Views</h3></td>";
							echo "</tr>";

                            $assessments = Models_Gradebook_Assessment::fetchAllRecords($output_cohort["group_id"], $COURSE_ID);
							if ($assessments) {
								$total_grade_weight = 0;
								$count = 0;
								foreach ($assessments as $assessment) {
                                    $result = $assessment->toArray();
									if ($ENTRADA_ACL->amIAllowed(new AssessmentResource($course_details["course_id"], $course_details["organisation_id"], $result["assessment_id"]), "update")) {
										//Display this row if the user is a Dropbox Contact for an assignment associated with this assessment or if they are the Course Owner.
										$query =  "	SELECT a.`course_id`, a.`assignment_id`, a.`assignment_title` 
													FROM `assignments` a
													JOIN `assignment_contacts`	b
													ON a.`assignment_id` = b.`assignment_id`
													WHERE a.`assessment_id` = " . $db->qstr($result["assessment_id"]) . "
													AND b.`proxy_id` = " . $db->qstr($ENTRADA_USER->getActiveId()) . "
													AND a.`assignment_active` = 1";
										$assignment_contact = $db->GetRow($query);	
										if ($assignment_contact || $ENTRADA_ACL->amIAllowed(new CourseContentResource($course_details["course_id"], $course_details["organisation_id"]), "update")) {
											$count++;
											$total_grade_weight += $result["grade_weighting"];

											$url = ENTRADA_URL."/admin/gradebook/assessments?section=grade&amp;id=".$COURSE_ID."&amp;assessment_id=".$result["assessment_id"];
											echo "<tr id=\"assessment-".$result["assessment_id"]."\" class=\"assessment\">";
											if ($ENTRADA_ACL->amIAllowed("gradebook", "delete", false)) {
												echo "	<td class=\"modified\"><input type=\"hidden\" name=\"order[".$result["assessment_id"]."][]\" value=\"".$result["order"]."\" class=\"order\" /><input class=\"delete\" type=\"checkbox\" name=\"delete[]\" value=\"".$result["assessment_id"]."\" /></td>\n";
											} else {
												echo "	<td class=\"modified\" width=\"20\"><input type=\"hidden\" name=\"order[".$result["assessment_id"]."][]\" value=\"sortorder\" class=\"order\" /><img src=\"".ENTRADA_URL."/images/pixel.gif\" width=\"19\" height=\"19\" alt=\"\" title=\"\" /></td>";
											}
											if ($ENTRADA_ACL->amIAllowed(new CourseContentResource($course_details["course_id"], $course_details["organisation_id"]), "update")) {
												echo "<td><a href=\"$url\">".html_encode($result["name"])."</a></td>";
												echo "<td><a href=\"$url\">".$result["grade_weighting"]. "%</a></td>";
											} else {
												echo "<td>".html_encode($result["name"])."</td>";
												echo "<td>".$result["grade_weighting"]. "%</td>";
											}
											
											$query =  "	SELECT a.`course_id`, a.`assignment_id`, a.`assignment_title` 
														FROM `assignments` a
														WHERE a.`assessment_id` = ".$db->qstr($result["assessment_id"])."
														AND a.`assignment_active` = 1";
											$assignment = $db->GetRow($query);	
                                            $action_field = "assessment_id";
                                            $action = "view";

                                            $query = "SELECT b.`id` AS `proxy_id`, CONCAT_WS(', ', b.`lastname`, b.`firstname`) AS `fullname`, b.`number`
                                                        FROM `".AUTH_DATABASE."`.`user_data` AS b
                                                        JOIN `".AUTH_DATABASE."`.`user_access` AS c
                                                        ON c.`user_id` = b.`id`
                                                        AND c.`app_id` IN (".AUTH_APP_IDS_STRING.")
                                                        AND c.`account_active` = 'true'
                                                        AND (c.`access_starts` = '0' OR c.`access_starts`<=".$db->qstr(time()).")
                                                        AND (c.`access_expires` = '0' OR c.`access_expires`>=".$db->qstr(time()).")
                                                        JOIN `group_members` AS c
                                                        ON b.`id` = c.`proxy_id`
                                                        WHERE c.`group` = 'student'
                                                        AND c.`group_id` = ".$db->qstr($output_cohort["group_id"])."
                                                        AND c.`member_active` = '1'
                                                        ORDER BY b.`lastname` ASC, b.`firstname` ASC";
                                            $students = $db->GetAll($query);

											$params = array(
                                                "module" => "gradebook",
                                                "action" => "view",
                                                "action_field" => "assessment_id",
                                                "action_value" => $result["assessment_id"]
                                            );
                                            $assessment_views = Models_Statistic::getCountByParams($params);
                                            
											if ($assignment && $ENTRADA_ACL->amIAllowed(new AssignmentResource($course_details["course_id"], $course_details["organisation_id"], $assignment["assignment_id"]), "update")) {
												$url = ENTRADA_URL."/admin/gradebook/assignments?section=grade&amp;id=".$COURSE_ID."&amp;assignment_id=".$assignment["assignment_id"];
												echo "<td id=\"assignment-".$assignment["assignment_id"]."\">";
												echo "  <a href=\"".ENTRADA_URL."/admin/gradebook/assignments?section=download-submissions&assignment_id=".$assignment["assignment_id"]."&id=" . $COURSE_ID . "\"><i class=\"icon-download-alt\"></i></a>";
												if ($ENTRADA_ACL->amIAllowed(new CourseContentResource($course_details["course_id"], $course_details["organisation_id"]), "update")) {
													echo "<a href=\"".ENTRADA_URL."/admin/gradebook/assignments?section=delete&id=".$COURSE_ID."&delete=".$assignment["assignment_id"]."\"><i class=\"icon-minus-sign\"></i></a>";
												}
												echo "  <a href=\"".$url."\">".html_encode($assignment["assignment_title"])."</a>";
												echo "</td>";
											} else {
												echo "<td>\n";
												if ($ENTRADA_ACL->amIAllowed(new CourseContentResource($course_details["course_id"], $course_details["organisation_id"]), "update")) {
													echo "<a href=\"".ENTRADA_URL."/admin/gradebook/assignments?section=add&id=".$COURSE_ID."&assessment_id=".$result["assessment_id"]."\"><i class=\"icon-plus-sign\"></i> Add New Assignment</a>";
												} else {
													echo "&nbsp;";
												}
												echo "</td>\n";
											}
                                            echo "  <td class=\"assessment_col_5\"><a href=\"#assessment-view-details\" class=\"assessment-view\" data-toggle=\"modal\" data-assessment-id=\"" . $result["assessment_id"] . "\">" . (int) $assessment_views["views"] . "</a></td>";
											echo "</tr>";											
										}
									}
								}
								if ($count == 0) {
									?>
									<tr>
										<td colspan="4">
											There are currently no assessments entered for this course. <br />You can create new ones by clicking the <strong>Add New Assessment</strong> link above.
										</td>
									</tr>
									<?php
								}
								echo "<tr>";
								echo "	<td style=\"border-bottom: 0\" colspan=\"2\">&nbsp;</td>";
								echo "	<td style=\"".(($total_grade_weight < "100") ? "color: #ff2431; " : "")."border-bottom: 0\">". $total_grade_weight."%</td>";
								echo "</tr>";
							} else {
								?>
								<tr>
									<td colspan="4">
										There are currently no assessments entered for this course. <br />You can create new ones by clicking the <strong>Add New Assessment</strong> link above.
									</td>
								</tr>
								<?php
							}
						} else {
							?>
							<tr>
								<td colspan="4">
									There are currently no assessments entered for this course. <br />You can create new ones by clicking the <strong>Add New Assessment</strong> link above.
								</td>
							</tr>
							<?php
						}
					}
					?>
					</tbody>
				</table>
                <?php
                if ($ENTRADA_ACL->amIAllowed("gradebook", "delete", false)) {
                    echo "</form>";
                }
                $HEAD[] = "<script type=\"text/javascript\" src=\"".ENTRADA_RELATIVE."/javascript/jquery/jquery.dataTables.min.js?release=".html_encode(APPLICATION_VERSION)."\"></script>";
                ?>
                <form action="<?php echo ENTRADA_URL; ?>/admin/gradebook/assessments?<?php echo replace_query(array("section" => "copy")); ?>" method="post" id="copyAssessments" class="form-horizontal">
                <div class="modal hide" id="copy-assessments-confirmation-box" style="width: 500px;">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h4 class="modal-title">Copy <strong>Assessments</strong> Confirmation</h4>
                    </div>
                    <div class="modal-body">
                        <div class="body" style="height: auto;">
                            <div class="row-fluid modal-notice" style="display: none;">
                                <?php
                                echo display_notice("Please ensure you select at least one assessment to copy forward.");
                                ?>
                            </div>
                            <div class="row-fluid modal-content">
                                <div id="copy-assessments-message-holder" class="display-generic">If you would like to create new assessments based on the selected assessments, select a valid target audience and press <strong>Copy Assessments</strong>.</div>
                                <div class="span12 clearfix">
                                    <div id="copy_assessment_ids"></div>
                                    <?php
                                    if ($selected_audience_name) {
                                        ?>
                                        <div class="control-group">
                                            <label for="cohort-quick-select" class="control-label content-small">
                                                Current Target Audience:
                                            </label>
                                            <div class="controls pad-above-small">
                                                <?php echo html_encode($selected_audience_name); ?>
                                            </div>
                                        </div>
                                    <?php
                                    }
                                    ?>
                                    <div class="control-group">
                                        <label for="cohort-quick-select" class="control-label content-small">
                                            New Target Audience:
                                        </label>
                                        <div class="controls">

                                            <?php
                                            $query = "SELECT *
                                                        FROM `groups`
                                                        WHERE `group_type` = 'course_list'
                                                        AND `group_value` = ".$db->qstr($COURSE_ID)."
                                                        AND `group_active` = '1'
                                                        ORDER BY `group_name`";
                                            $course_lists = $db->GetAll($query);
                                            if($course_lists) {
                                                if (count($course_lists) == 1) {
                                                    ?>
                                                    <span class="pad-above-small"><?php echo $course_details["course_code"];?> Course List</span>
                                                    <input id="course_list" class="course-list" name="course_list" type="hidden" value="<?php echo $course_lists[0]["group_id"]; ?>">
                                                    <?php
                                                } else {
                                                    ?>
                                                    <select id="course-list-select" name="course_list">
                                                        <?php
                                                        foreach ($course_lists as $key => $course_list) { ?>
                                                            <option value="<?php echo $course_list["group_id"];?>" <?php echo (($course_list["group_id"] == $selected_classlist) ? "selected=\"selected\"" : "");?>>
                                                                <?php echo html_encode($course_list["group_name"]);?>
                                                            </option>
                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                    <?php
                                                }
                                            } else {
                                                ?>
                                                <select id="cohort-select" name="cohort">
                                                    <?php
                                                    $active_cohorts = groups_get_all_cohorts($ENTRADA_USER->getActiveOrganisation());
                                                    foreach ($active_cohorts as $cohort) {
                                                        ?>
                                                        <option value="<?php echo $cohort["group_id"];?>" <?php echo (($cohort["group_id"] == $selected_cohort) ? "selected=\"selected\"" : "");?>>
                                                            <?php echo html_encode($cohort["group_name"]);?>
                                                        </option>
                                                    <?php
                                                    }
                                                    ?>
                                                </select>
                                                <?php
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="button" class="btn close-button" data-dismiss="modal" aria-hidden="true" value="Cancel" style="float: left; margin: 8px 0px 4px 10px" />
                        <input type="submit" class="btn btn-primary submit-button" value="Copy Assessments" style="float: right; margin: 8px 10px 4px 0px" />
                    </div>
                </div>
                </form>
                <script type="text/javascript">
                    jQuery(function($) {
                        var gradebook_views_table = $("#gradebook-views").DataTable({
                            "bPaginate": false,
                            "bInfo": false,
                            "bFilter": false
                        });
                        $(".assessment-view").on("click", function() {
                            var assessment_id = $(this).data("assessment-id");
                            $.ajax({
                                url : "<?php echo ENTRADA_URL; ?>/api/gradebook-stats.api.php",
                                data : {assessment_id : assessment_id},
                                success: function(data) {
                                    var jsonResponse = JSON.parse(data);
                                    if (jsonResponse.status == "success") {
                                        if (jsonResponse.data.length > 0) {
                                            gradebook_views_table.fnAddData(jsonResponse.data);
                                        }
                                    }
                                }
                            });
                        });
                        $("#assessment-view-details").on("hidden", function(e) {
                            gradebook_views_table.fnClearTable();
                        });
                    });
                </script>
                <div id="assessment-view-details" class="modal hide fade">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h3>Gradebook Views</h3>
                    </div>
                    <div class="modal-body">
                        <table class="table table-bordered table-striped" id="gradebook-views">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Views</th>
                                    <th>First View</th>
                                    <th>Last View</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <a href="#" class="btn" data-dismiss="modal" aria-hidden="true">Close</a>
                    </div>
                </div>
				<div class="gradebook_edit" style="display: none;"></div>
                <?php
			} else {
				// No assessments in this course.
				?>
				<div class="display-generic">
					<h3>No Assessments for <?php echo $course_details["course_name"]; ?></h3>
					There are currently no assessments entered for this course. You can create new ones by clicking the <strong>Add New Assessment</strong> link above.
				</div>
				<?php
			}
		} else {
			$url = ENTRADA_URL."/admin/gradebook";
			$ONLOAD[] = "setTimeout('window.location=\\'".$url."\\'', 5000)";
			
			$ERROR++;
			$ERRORSTR[] = "You do not have permission to view this Gradebook.<br /><br />You will now be redirected to the <strong>Gradebook index</strong> page.  This will happen <strong>automatically</strong> in 5 seconds or <a href=\"".$url."\" style=\"font-weight: bold\">click here</a> to continue.";

			echo display_error();

			application_log("notice", "Failed to provide a valid course identifer when attempting to view a gradebook");
		}
	} else {
		$ERROR++;
		$ERRORSTR[] = "In order to edit a course you must provide the courses identifier.";

		echo display_error();

		application_log("notice", "Failed to provide course identifer when attempting to view a gradebook");
	}
}
?>
