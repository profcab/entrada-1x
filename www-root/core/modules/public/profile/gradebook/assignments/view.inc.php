<?php
/**
 * Entrada [ http://www.entrada-project.org ]
 *
 * Used to view the details of / download the specified file within a folder.
 *
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Brandon Thorn <brandon.thorn@queensu.ca>
 * @copyright Copyright 2012 Queen's University. All Rights Reserved.
 *
*/
if ((!defined("IN_PUBLIC_ASSIGNMENTS"))) {
	exit;
}

if (!$RECORD_ID) {
	if (isset($_GET["assignment_id"]) && $tmp = clean_input($_GET["assignment_id"], "int")) {
		$RECORD_ID = $tmp;
	}
}
if (!isset($DOWNLOAD) || !$DOWNLOAD) {
	if (isset($_GET["download"]) && $tmp = clean_input($_GET["download"], array("trim","notags"))) {
		$DOWNLOAD = $tmp;
	}
}

$query = "SELECT * FROM `assignment_contacts` WHERE `assignment_id` = ".$db->qstr($RECORD_ID)." AND `proxy_id` = ".$ENTRADA_USER->getID();
$iscontact = $db->GetRow($query);

if ($RECORD_ID) {

	$query = "	SELECT a.*,b.`organisation_id`
				FROM `assignments` a
				JOIN `courses` b
				ON a.`course_id` = b.`course_id`
				WHERE a.`assignment_id` = ".$db->qstr($RECORD_ID)."
				AND a.`assignment_active` = '1'";
	$assignment = $db->GetRow($query);



	if (isset($_GET["pid"]) && $tmp = clean_input($_GET["pid"], "int")) {
		if ($iscontact) {
			$USER_ID = $tmp;
		} elseif ($assignment && $ENTRADA_ACL->amIAllowed(new CourseResource($assignment["course_id"], $assignment["organisation_id"]), "update")) {
			$iscontact = true;
			$USER_ID = $tmp;
		}else {
			$USER_ID = false;
		}

	} elseif($iscontact) {
		header("Location: ".ENTRADA_URL."/admin/gradebook/assignments?section=grade&id=".$assignment["course_id"]."&assignment_id=".$RECORD_ID);
	} else {
		$USER_ID = $ENTRADA_USER->getID();
	}

	if ($USER_ID) {
		if($assignment){
			$course_ids = groups_get_enrolled_course_ids($USER_ID);
			if(in_array($assignment["course_id"],$course_ids)){
				$query			= "
								SELECT a.*, b.`course_id`, b.`assignment_title`, c.`number`
								FROM `assignment_files` AS a
								JOIN `assignments` AS b
								ON a.`assignment_id` = b.`assignment_id`
								JOIN `".AUTH_DATABASE."`.`user_data` AS c
								ON a.`proxy_id` = c.`id`
								WHERE `file_active` = '1'
								AND b.`assignment_active` = '1'
								AND a.`assignment_id` = ".$db->qstr($RECORD_ID)."
								AND a.`proxy_id` = ".$db->qstr($USER_ID);
				$file_record	= $db->GetRow($query);
				if ($file_record) {
					$FILE_ID = $file_record["afile_id"];
					if ((isset($DOWNLOAD)) && ($DOWNLOAD)) {
						/**
						 * Check for valid permissions before checking if the file really exists.
						 */
						if(isset($_GET["file_id"]) && $tmp_id = (int)$_GET["file_id"]){
							$dfile_id = $tmp_id;
						}else{
							$dfile_id = 0;
						}
						$file_version = false;
						if ((int) $DOWNLOAD) {
							/**
							 * Check for specified version.
							 */
							$query	= "
									SELECT *
									FROM `assignment_file_versions`
									WHERE `assignment_id` = ".$db->qstr($RECORD_ID)."
									AND `afile_id` = ".$db->qstr($dfile_id)."
									AND `file_active` = '1'
									AND `file_version` = ".$db->qstr((int) $DOWNLOAD);
							$result	= $db->GetRow($query);
							if ($result) {
								$file_version = array();
								$file_version["afversion_id"] = $result["afversion_id"];
								$file_version["file_mimetype"] = $result["file_mimetype"];
								$file_version["file_filename"] = $result["file_filename"];
								$file_version["file_filesize"] = (int) $result["file_filesize"];
							}
						} else {
							/**
							 * Download the latest version.
							 */
							$query	= "
									SELECT *
									FROM `assignment_file_versions`
									WHERE `assignment_id` = ".$db->qstr($RECORD_ID)."
									AND `afile_id` = ".$db->qstr($dfile_id)."
									AND `file_active` = '1'
									ORDER BY `file_version` DESC
									LIMIT 0, 1";
							$result	= $db->GetRow($query);
							if ($result) {
								$file_version = array();
								$file_version["afversion_id"] = $result["afversion_id"];
								$file_version["file_mimetype"] = $result["file_mimetype"];
								$file_version["file_filename"] = $result["file_filename"];
								$file_version["file_filesize"] = (int) $result["file_filesize"];
							}
						}

						if (($file_version) && (is_array($file_version))) {
							$download_file = FILE_STORAGE_PATH."/A".$file_version["afversion_id"];
							if ((file_exists($download_file)) && (is_readable($download_file))) {
								ob_clear_open_buffers();
                                header("Pragma: public");
                                header("Expires: 0");
                                header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                                header("Content-Type: application/force-download");
                                header("Content-Type: application/octet-stream");
                                header("Content-Type: ".$file_version["file_mimetype"]);
                                if (isset($assignment["anonymous_marking"]) && $assignment["anonymous_marking"]) {
                                    $file_extension = pathinfo($file_version['file_filename'], PATHINFO_EXTENSION);
                                    header("Content-Disposition: attachment; filename=\"" . $file_record["number"] . "_" . $file_version["afversion_id"] . "." . $file_extension . "\"");
                                } else {
                                header("Content-Disposition: attachment; filename=\"".$file_version["file_filename"]."\"");
                                }
                                header("Content-Length: ".@filesize($download_file));
                                header("Content-Transfer-Encoding: binary\n");
								add_statistic("community:".$COMMUNITY_ID.":shares", "file_download", "csfile_id", $RECORD_ID);
								echo @file_get_contents($download_file, FILE_BINARY);
								exit;
							}
						}



						if ((!$ERROR) || (!$NOTICE)) {
							$ERROR++;
							$ERRORSTR[] = "<strong>Unable to download the selected file.</strong><br /><br />The file you have selected cannot be downloaded at this time, please try again later.";
						}

						if ($NOTICE) {
							echo display_notice();
						}
						if ($ERROR) {
							echo display_error();
						}

					} else {
						if (isset($iscontact) && $iscontact) {
							$query = "SELECT CONCAT_WS(' ', `firstname`,`lastname`) AS `uploader` FROM `".AUTH_DATABASE."`.`user_data` WHERE `id` = ".$db->qstr($file_record["proxy_id"]);
							$user_name = $db->GetOne($query);
							$BREADCRUMB = array();
							$BREADCRUMB[] = array("url" => ENTRADA_URL."/admin/gradebook", "title" => "Gradebooks");
							$BREADCRUMB[] = array("url" => ENTRADA_URL."/admin/gradebook?".replace_query(array("section" => "view", "id" => $file_record["course_id"])), "title" => "Assignments");
							$BREADCRUMB[] = array("url" => ENTRADA_URL."/admin/gradebook/assignments?".replace_query(array("section" => "grade", "id" => $file_record["course_id"], "assignment_id"=>$file_record["assignment_id"], "step" => false)), "title" => $file_record["assignment_title"]);
                            $BREADCRUMB[] = array("url" => ENTRADA_URL."/admin/gradebook/assignments?".replace_query(array("section" => "grade", "id" => $file_record["course_id"], "assignment_id"=>$file_record["assignment_id"], "step" => false)), "title" => ($assignment["anonymous_marking"] ? html_encode($file_record["number"]) : html_encode($user_name)."'s Submission"));
						} else {
							$BREADCRUMB[] = array("url" => ENTRADA_URL."/profile/gradebook/assignments?section=view&assignment_id=".$RECORD_ID, "title" => limit_chars($file_record["assignment_title"], 32));
						}
						$ADD_COMMENT	= true;//shares_module_access($file_record["cshare_id"], "add-comment");
						$ADD_REVISION	= $assignment["assignment_uploads"] == 1 ? true : false;//shares_file_module_access($file_record["csfile_id"], "add-revision");
						$MOVE_FILE		= false;//shares_file_module_access($file_record["csfile_id"], "move-file");
						$NAVIGATION		= false;//shares_file_navigation($file_record["cshare_id"], $RECORD_ID);
						//$community_shares_select = community_shares_in_select($file_record["cshare_id"]);
						?>
						<script type="text/javascript">

                            jQuery(document).ready(function(){
                                jQuery('.delete').click(function(){
                                    id = jQuery(this).attr('id').substring(7);
                                    jQuery("#dialog-confirm").dialog({
                                        resizable: false,
                                        height:180,
                                        modal: true,
                                        buttons: {
                                            'Delete': function() {
                                                var url = '<?php echo ENTRADA_URL."/profile/gradebook/assignments";?>?section=delete-comment&acomment_id='+id;
                                                window.location = url;
                                                return true;
                                            },
                                            Cancel: function() {
                                                jQuery(this).dialog('close');
                                            }
                                        }
                                        });
                                });
                                jQuery('.delete-version').click(function(){
                                    id = jQuery(this).attr('id').substring(7);
                                    jQuery("#dialog-confirm").dialog({
                                        resizable: false,
                                        height:180,
                                        modal: true,
                                        buttons: {
                                            'Delete': function() {
                                                window.location = '<?php echo ENTRADA_URL."/profile/gradebook/assignments";?>?section=delete-version&afversion_id='+id;
                                                return true;
                                            },
                                            Cancel: function() {
                                                jQuery(this).dialog('close');
                                            }
                                        }
                                        });
                                });
                            });

                            <?php if ($community_shares_select != "") { ?>
                            function fileMove(id) {
                                Dialog.confirm('Do you really wish to move the '+ $('file-' + id + '-title').innerHTML +' file?<br /><br />If you confirm this action, you will be moving the file and all comments to the selected folder.<br /><br /><?php echo $community_shares_select; ?>',
                                    {
                                        id:				'requestDialog',
                                        width:			350,
                                        height:			165,
                                        title:			'Delete Confirmation',
                                        className:		'medtech',
                                        okLabel:		'Yes',
                                        cancelLabel:	'No',
                                        closable:		'true',
                                        buttonClass:	'btn',
                                        ok:				function(win) {
                                                            window.location = '<?php echo COMMUNITY_URL.$COMMUNITY_URL.":".$PAGE_URL; ?>?section=move-file&id='+id+'&share_id='+$F('share_id');
                                                            return true;
                                                        }
                                    }
                                );
                            }
                            <?php
                            }
                            if (false){//shares_file_module_access($RECORD_ID, "delete-revision")) {
                                ?>

                                function revisionDelete(id) {
                                    Dialog.confirm('Do you really wish to deactivate the '+ $('file-version-' + id + '-title').innerHTML +' revision?<br /><br />If you confirm this action, you will no longer be able to download this version of the file.',
                                        {
                                            id:				'requestDialog',
                                            width:			350,
                                            height:			125,
                                            title:			'Delete Confirmation',
                                            className:		'medtech',
                                            okLabel:		'Yes',
                                            cancelLabel:	'No',
                                            closable:		'true',
                                            buttonClass:	'btn',
                                            ok:				function(win) {
                                                                window.location = '<?php echo COMMUNITY_URL.$COMMUNITY_URL.":".$PAGE_URL; ?>?section=delete-revision&id='+id;
                                                                return true;
                                                            }
                                        }
                                    );
                                }
                                <?php
                            }
                            ?>
                        </script>
                        <?php

						if ($NOTICE) {
							echo display_notice();
						}
						?>
						<a name="top"></a>
                        <h1>Assignment Submission</h1>
						<?php if (COMMUNITY_NOTIFICATIONS_ACTIVE && $_SESSION["details"]["notifications"]) { ?>
							<div id="notifications-toggle" style="height: 2em;"></div>
							<script type="text/javascript">
							function promptNotifications(enabled) {
								Dialog.confirm('Do you really wish to '+ (enabled == 1 ? "stop" : "begin") +' receiving notifications for new comments and revisions for this file?',
									{
										id:				'requestDialog',
										width:			350,
										height:			75,
										title:			'Notification Confirmation',
										className:		'medtech',
										okLabel:		'Yes',
										cancelLabel:	'No',
										closable:		'true',
										buttonClass:	'btn',
										destroyOnClose:	true,
										ok:				function(win) {
															new Window(	{
																			id:				'resultDialog',
																			width:			350,
																			height:			75,
																			title:			'Notification Result',
																			className:		'medtech',
																			okLabel:		'close',
																			buttonClass:	'btn',
																			resizable:		false,
																			draggable:		false,
																			minimizable:	false,
																			maximizable:	false,
																			recenterAuto:	true,
																			destroyOnClose:	true,
																			url:			'<?php echo ENTRADA_URL."/api/notifications.api.php?community_id=".$COMMUNITY_ID."&assignment_id=".$RECORD_ID; ?>&type=file-notify&action=edit&active='+(enabled == 1 ? '0' : '1'),
																			onClose:			function () {
																								new Ajax.Updater('notifications-toggle', '<?php echo ENTRADA_URL."/api/notifications.api.php?community_id=".$COMMUNITY_ID."&assignment_id=".$RECORD_ID; ?>&type=file-notify&action=view');
																							}
																		}
															).showCenter();
															return true;
														}
									}
								);
							}

							</script>
							<?php
							$ONLOAD[] = "new Ajax.Updater('notifications-toggle', '".ENTRADA_URL."/api/notifications.api.php?community_id=".$COMMUNITY_ID."&assignment_id=".$RECORD_ID."&type=file-notify&action=view')";
						}

                        $files_query = "SELECT a.*, c.*, CONCAT_WS(' ', b.`firstname`, b.`lastname`) AS `uploader`, b.`username` AS `uploader_username`, b.`number`
                                  FROM `assignment_file_versions` AS a
                                  JOIN `assignment_files` AS c
                                  ON a.`afile_id`=c.`afile_id`
                                  LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS b
                                  ON a.`proxy_id`=b.`id`
                                  WHERE a.`assignment_id`=".$db->qstr($RECORD_ID)."
                                  AND a.`proxy_id`=".$db->qstr($USER_ID)."
                                  AND a.`file_active` = 1
                                  AND c.`file_active` = 1
                                  AND c.`file_type`='submission'
                                  AND a.`file_version`=
                                      (SELECT MAX(`file_version`)
                                       FROM `assignment_file_versions`
                                       WHERE `file_active`=1
                                       AND `afile_id`=a.`afile_id`)";
                        $file_records = $db->GetAll($files_query);
                        if ($file_records) {
                            foreach ($file_records as $file_record) {
                            ?>
                                <div id="file-<?php echo $file_record["afile_id"]; ?>" style="padding-top: 15px; clear: both">
                                    <?php
                                    //Student's submission
                                    $query		= "
                                                SELECT a.*,  CONCAT_WS(' ', b.`firstname`, b.`lastname`) AS `uploader`, b.`number`, b.`username` AS `uploader_username`
                                                FROM `assignment_file_versions` AS a
                                                JOIN `assignment_files` AS c
                                                ON a.`afile_id` = c.`afile_id`
                                                LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS b
                                                ON a.`proxy_id` = b.`id`
                                                WHERE a.`afile_id` = ".$db->qstr($file_record["afile_id"])."
                                                AND a.`assignment_id` = ".$db->qstr($RECORD_ID)."
                                                AND a.`file_active` = '1'
                                                AND c.`file_type` = 'submission'
                                                ORDER BY a.`file_version` DESC";
                                    $results	= $db->GetAll($query);
                                    if ($results) {
                                        $total_releases	= count($results);
                                        echo "<h2 id=\"file-{$file_record["afile_id"]}-title\">" . ($assignment["anonymous_marking"] ? html_encode($file_record["number"]) : html_encode($file_record["file_title"])) . "</h2>\n";
                                        echo "<p>" . html_encode($file_record["file_description"]) . "</p>\n";
                                        echo "<table style=\"width: 100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">\n";
                                        echo "<colgroup>\n";
                                        echo "	<col style=\"width: 8%\" />\n";
                                        echo "	<col style=\"width: 92%\" />\n";
                                        echo "</colgroup>\n";
                                        echo "<tbody>\n";
                                        echo "	<tr>\n";
                                        echo "		<td style=\"vertical-align: top\"><a href=\"" . ENTRADA_URL . "/profile/gradebook/assignments?section=view&amp;assignment_id=" . $RECORD_ID . "&amp;file_id=" . $file_record["afile_id"] . "&amp;" . (isset($iscontact) && $iscontact ? "pid=" . $USER_ID . "&amp;" : "") . "download=latest\"><img src=\"" . ENTRADA_URL . "/templates/default/images/btn_save.gif\" width=\"32\" height=\"32\" alt=\"Save Latest Version\" title=\"Save Latest Version\" align=\"left\" style=\"margin-right: 15px; border: 0px\" /></a></td>";
                                        echo "		<td style=\"vertical-align: top\">\n";
                                        echo "			<div id=\"file-download-latest\">\n";
                                        echo "				<a href=\"" . ENTRADA_URL . "/profile/gradebook/assignments?section=view&amp;assignment_id=" . $RECORD_ID . "&amp;" . (isset($iscontact) && $iscontact ? "pid=" . $USER_ID . "&amp;" : "") . "file_id=" . $file_record["afile_id"] . "&amp;download=latest\" target=\"_blank\">Download Latest (v" . $results[0]["file_version"] . ")</a>\n";
                                        echo "				<div class=\"content-small\">\n";
                                        if (!isset($assignment["anonymous_marking"]) || !$assignment["anonymous_marking"]) {
                                            echo "					Filename: <span id=\"file-version-" . $results[0]["afversion_id"] . "-title\">" . html_encode($results[0]["file_filename"]) . " (v" . $results[0]["file_version"] . ")</span> " . readable_size($results[0]["file_filesize"]);
                                        } else {
                                            $file_extension = pathinfo($results[0]['file_filename'], PATHINFO_EXTENSION);
                                            echo "					Filename: <span id=\"file-version-" . $results[0]["afversion_id"] . "-title\">" . html_encode($file_record["number"] . "_" . $results[0]["afversion_id"] . "." . $file_extension) . " (v" . $results[0]["file_version"] . ")</span> " . readable_size($results[0]["file_filesize"]);
                                        }
                                        if ($total_releases > 1 && $results[0]["proxy_id"] == $ENTRADA_USER->getID()) {
                                            echo 				"(<a class=\"action delete-version\" id=\"delete_".$results[0]["afversion_id"]."\" href=\"javascript:void(0)')\" style=\"font-size: 10px; font-weight: normal\">delete</a>)";
                                        }
                                        echo "					<br />\n";
                                        if (isset($assignment["anonymous_marking"]) && $assignment["anonymous_marking"] && $USER_ID != $ENTRADA_USER->getID()) {
                                            echo "			Uploaded " . date(DEFAULT_DATE_FORMAT, $results[0]["updated_date"]) .  " by " . $results[0]["number"] . ".\n";
                                        } else {
                                            echo "			Uploaded " . date(DEFAULT_DATE_FORMAT, $results[0]["updated_date"]) . " by <a href=\"" . ENTRADA_URL . "/people?profile=" . html_encode($results[0]["uploader_username"]) . "\" style=\"font-size: 10px; font-weight: normal\">" . html_encode($results[0]["uploader"]) . "</a>.<br />";
                                        }
                                        echo "				</div>\n";
                                        echo "			</div>\n";
                                        echo "		</td>\n";
                                        echo "	</tr>\n";
                                        if ($total_releases > 1) {
                                            echo "<tr>\n";
                                            echo "	<td>&nbsp;</td>\n";
                                            echo "	<td style=\"padding-top: 15px\">\n";
                                            echo "		<h2>Older Versions</h2>\n";
                                            echo "		<div id=\"file-download-releases\">\n";
                                            echo "			<ul>\n";
                                            foreach($results as $progress => $result) {
                                                if ((int) $progress > 0) { // Because I don't want to display the first file again.
                                                    echo "		<li>\n";
                                                    echo "			<a href=\"".ENTRADA_URL."/profile/gradebook/assignments?section=view&amp;id=".$RECORD_ID."&amp;".(isset($iscontact) && $iscontact?"pid=".$USER_ID."&amp;":"")."download=".$result["file_version"]."\" style=\"vertical-align: middle\" target=\"_blank\"><span id=\"file-version-".$result["afversion_id"]."-title\">".html_encode($result["file_filename"])." (v".$result["file_version"].")</span></a> <span class=\"content-small\" style=\"vertical-align: middle\">".readable_size($result["file_filesize"])."</span>\n";
                                                    if($result["proxy_id"] == $ENTRADA_USER->getID()){
                                                        echo "			(<a class=\"action delete-version\" id=\"delete_".$result["afversion_id"]."\" href=\"javascript:void(0)\">delete</a>)";
                                                    }
                                                    echo "			<div class=\"content-small\">\n";
                                                    if (isset($assignment["anonymous_marking"]) && $assignment["anonymous_marking"] && $USER_ID != $ENTRADA_USER->getID()) {
                                                        echo "			Uploaded " . date(DEFAULT_DATE_FORMAT, $result["updated_date"]) .  ".\n";
                                                    } else {
                                                        echo "			Uploaded " . date(DEFAULT_DATE_FORMAT, $result["updated_date"]) . " by <a href=\"" . ENTRADA_URL . "/people?profile=" . html_encode($result["uploader_username"]) . "\" style=\"font-size: 10px; font-weight: normal\">" . html_encode($result["uploader"]) . "</a>.\n";
                                                    }
                                                    echo "			</div>\n";
                                                    echo "		</li>";
                                                }
                                            }
                                            echo "			</ul>\n";
                                            echo "		</div>\n";
                                            echo "	</td>\n";
                                            echo "</tr>\n";
                                        }
                                        echo "</tbody>\n";
                                        echo "</table>\n";
                                    }

                                    //Teacher response
                                    $query		= "
                                                SELECT a.*,  CONCAT_WS(' ', b.`firstname`, b.`lastname`) AS `uploader`, b.`username` AS `uploader_username`
                                                FROM `assignment_file_versions` AS a
                                                JOIN `assignment_files` AS c
                                                ON a.`afile_id` = c.`afile_id`
                                                LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS b
                                                ON a.`proxy_id` = b.`id`
                                                WHERE c.`parent_id` = ".$db->qstr($file_record["afile_id"])."
                                                AND a.`assignment_id` = ".$db->qstr($RECORD_ID)."
                                                AND a.`file_active` = '1'
                                                AND c.`file_type` = 'response'
                                                ORDER BY a.`file_version` DESC";
                                    $results	= $db->GetAll($query);
                                    $teacher_file = false;
                                    if ($results) {
                                        $teacher_file = true;
                                        $TEACHER_FILE_RECORD = $results[0]["afile_id"];
                                        echo "<h2>Teacher's Response</h2>";
                                        $total_releases	= count($results);
                                        echo "<table style=\"width: 100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">\n";
                                        echo "<colgroup>\n";
                                        echo "	<col style=\"width: 8%\" />\n";
                                        echo "	<col style=\"width: 92%\" />\n";
                                        echo "</colgroup>\n";
                                        echo "<tbody>\n";
                                        echo "	<tr>\n";
                                        echo "		<td style=\"vertical-align: top\"><a href=\"".ENTRADA_URL."/profile/gradebook/assignments?section=view&amp;assignment_id=".$RECORD_ID."&amp;".(isset($iscontact) && $iscontact?"pid=".$USER_ID."&amp;":"")."file_id=".$TEACHER_FILE_RECORD."&amp;download=latest\"><img src=\"".ENTRADA_URL."/templates/default/images/btn_save.gif\" width=\"32\" height=\"32\" alt=\"Save Latest Version\" title=\"Save Latest Version\" align=\"left\" style=\"margin-right: 15px; border: 0px\" /></a></td>";
                                        echo "		<td style=\"vertical-align: top\">\n";
                                        echo "			<div id=\"file-download-latest\">\n";
                                        echo "				<a href=\"".ENTRADA_URL."/profile/gradebook/assignments?section=view&amp;assignment_id=".$RECORD_ID."&amp;".(isset($iscontact) && $iscontact?"pid=".$USER_ID."&amp;":"")."file_id=".$TEACHER_FILE_RECORD."&amp;download=latest\" target=\"_blank\">Download Latest (v".$results[0]["file_version"].")</a>\n";
                                        echo "				<div class=\"content-small\">\n";
                                        if (!isset($assignment["anonymous_marking"]) || !$assignment["anonymous_marking"]) {
                                        echo "					Filename: <span id=\"file-version-".$results[0]["afversion_id"]."-title\">".html_encode($results[0]["file_filename"])." (v".$results[0]["file_version"].")</span> ".readable_size($results[0]["file_filesize"]);
                                        } else {
                                            $file_extension = pathinfo($results[0]['file_filename'], PATHINFO_EXTENSION);
                                            echo "					Filename: <span id=\"file-version-" . $results[0]["afversion_id"] . "-title\">" . html_encode($file_record["number"] . "_" . $results[0]["afversion_id"] . "." . $file_extension) . " (v" . $results[0]["file_version"] . ")</span> " . readable_size($results[0]["file_filesize"]);
                                        }
                                        if ($results[0]["proxy_id"] == $ENTRADA_USER->getID()) {
                                            echo 				"(<a class=\"action delete-version\" href=\"javascript:void(0)\" id=\"delete_".$results[0]["afversion_id"]."\" style=\"font-size: 10px; font-weight: normal\">delete</a>)";
                                        }
                                        echo "					<br />\n";
                                        if (isset($assignment["anonymous_marking"]) && $assignment["anonymous_marking"] && $results[0]["proxy_id"] != $ENTRADA_USER->getID()) {
                                            echo "			Uploaded " . date(DEFAULT_DATE_FORMAT, $results[0]["updated_date"]) .  ".\n";
                                        } else {
                                        echo "					Uploaded ".date(DEFAULT_DATE_FORMAT, $results[0]["updated_date"])." by <a href=\"".ENTRADA_URL."/people?profile=".html_encode($results[0]["uploader_username"])."\" style=\"font-size: 10px; font-weight: normal\">".html_encode($results[0]["uploader"])."</a>.<br />";
                                        }
                                        echo "				</div>\n";
                                        echo "			</div>\n";
                                        echo "		</td>\n";
                                        echo "	</tr>\n";
                                        if ($total_releases > 1) {
                                            echo "<tr>\n";
                                            echo "	<td>&nbsp;</td>\n";
                                            echo "	<td style=\"padding-top: 15px\">\n";
                                            echo "		<h2>Older Versions</h2>\n";
                                            echo "		<div id=\"file-download-releases\">\n";
                                            echo "			<ul>\n";
                                            foreach($results as $progress => $result) {
                                                if ((int) $progress > 0) { // Because I don't want to display the first file again.
                                                    echo "		<li>\n";
                                                    echo "			<a href=\"".ENTRADA_URL."/profile/gradebook/assignments?section=view&amp;file_id=".$TEACHER_FILE_RECORD."&amp;assignment_id=".$RECORD_ID."&amp;".(isset($iscontact) && $iscontact?"pid=".$USER_ID."&amp;":"")."download=".$result["file_version"]."\" style=\"vertical-align: middle\" target=\"_blank\"><span id=\"file-version-".$result["afversion_id"]."-title\">".html_encode($result["file_filename"])." (v".$result["file_version"].")</span></a> <span class=\"content-small\" style=\"vertical-align: middle\">".readable_size($result["file_filesize"])."</span>\n";
                                                    if($result["proxy_id"] == $ENTRADA_USER->getID()){
                                                        echo "			(<a class=\"action delete-version\" id=\"delete_".$result["afversion_id"]."\" href=\"javascript:void(0)\">delete</a>)";
                                                    }
                                                    echo "			<div class=\"content-small\">\n";
                                                    if (isset($assignment["anonymous_marking"]) && $assignment["anonymous_marking"] && $USER_ID != $ENTRADA_USER->getID()) {
                                                        echo "			Uploaded " . date(DEFAULT_DATE_FORMAT, $result["updated_date"]) .  ".\n";
                                                    } else {
                                                    echo "			Uploaded ".date(DEFAULT_DATE_FORMAT, $result["updated_date"])." by <a href=\"".ENTRADA_URL."/people?profile=".html_encode($result["uploader_username"])."\" style=\"font-size: 10px; font-weight: normal\">".html_encode($result["uploader"])."</a>.\n";
                                                    }
                                                    echo "			</div>\n";
                                                    echo "		</li>";
                                                }
                                            }
                                            echo "			</ul>\n";
                                            echo "		</div>\n";
                                            echo "	</td>\n";
                                            echo "</tr>\n";
                                        }
                                        echo "</tbody>\n";
                                        echo "</table>\n";
                                        echo "<br /><br />";
                                    }
                                ?>

                                </div>

                                <?php
                                if (($ADD_REVISION) || ($MOVE_FILE)) {
                                    ?>
                                    <div class="page-action">
                                        <?php if (isset($iscontact) && $iscontact) {
                                            if ($teacher_file) {?>
                                            <a href="<?php echo ENTRADA_URL."/admin/gradebook/assignments"; ?>?section=response-revision&assignment_id=<?php echo $RECORD_ID; ?>&fid=<?php echo $file_record['afile_id']; ?>"><button class="btn">Upload Response Revision</button></a>
                                        <?php } else {
                                            ?><a href="<?php echo ENTRADA_URL."/admin/gradebook/assignments"; ?>?section=submit-response&assignment_id=<?php echo $RECORD_ID; ?>&fid=<?php echo $file_record['afile_id']; ?>"><button class="btn">Hand Back Response</button></a><?php
                                            }
                                        } elseif ($ADD_REVISION) {?>
                                            <a href="<?php echo ENTRADA_URL."/profile/gradebook/assignments"; ?>?section=add-revision&assignment_id=<?php echo $RECORD_ID; ?>&fid=<?php echo $file_record['afile_id']; ?>"><button class="btn">Upload Revised File</button></a>
                                        <?php } ?>
                                    </div>
                                    <?php
                                }
                            }
                            ?>

                            <h2 style="margin-bottom: 0px">Assignment Comments</h2>
                            <?php

                            $query		= "
                                        SELECT DISTINCT a.*, CONCAT_WS(' ', b.`firstname`, b.`lastname`) AS `commenter_fullname`, b.`username` AS `commenter_username`, b.`id` AS `proxy_id`
                                        FROM `assignment_comments` AS a
                                        LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS b
                                        ON b.`id` = a.`proxy_id`
                                        WHERE a.`proxy_to_id` = ".$db->qstr($USER_ID)."
                                        AND a.`assignment_id` = ".$db->qstr($RECORD_ID)."
                                        AND a.`comment_active` = '1'
                                        ORDER BY a.`release_date` ASC";
                            $results	= $db->GetAll($query);
                            $comments	= 0;
                            if ($results) { ?>
                                <table class="discussions posts" style="width: 100%" cellspacing="0" cellpadding="0" border="0">
                                    <colgroup>
                                        <col style="width: 30%" />
                                        <col style="width: 70%" />
                                    </colgroup>
                                    <tbody>
                                    <?php
                                    foreach($results as $result) {
                                        $comments++;
                                        ?>
                                        <tr>
                                            <?php
                                            if (!isset($assignment["anonymous_marking"]) || !$assignment["anonymous_marking"] || $result["proxy_id"] == $ENTRADA_USER->getID()) {
                                                ?>
                                                <td style="border-bottom: none; border-right: none">
                                                    <span class="content-small">By:</span>
                                                    <a href="<?php echo ENTRADA_URL . "/people?profile=" . html_encode($result["commenter_username"]); ?>" style="font-size: 10px"><?php echo html_encode($result["commenter_fullname"]); ?></a>
                                                </td>
                                                <?php
                                            } else {
                                                ?>
                                                <td style="border-bottom: none; border-right: none">
                                                    <span class="content-small">By:</span>
                                                    <span style="font-size: 10px">Anonymous Commenter</span>
                                                </td>
                                                <?php
                                            }
                                            ?>
                                            <td style="border-bottom: none">
                                                <div style="float: left">
                                                    <span class="content-small"><strong>Commented:</strong> <?php echo date(DEFAULT_DATE_FORMAT, $result["updated_date"]); ?></span>
                                                </div>
                                                <div style="float: right">
                                                <?php
                                                echo (($result["proxy_id"] == $ENTRADA_USER->getID()) ? " (<a class=\"action\" href=\"".ENTRADA_URL."/profile/gradebook/assignments?section=edit-comment&amp;assignment_id=".$RECORD_ID."&amp;cid=".$result["acomment_id"]."\">edit</a>)" : "");
                                                echo (($result["proxy_id"] == $ENTRADA_USER->getID()) ? " (<a class= \"action delete\" id=\"delete_".$result["acomment_id"]."\" href=\"javascript:void(0)\">delete</a>)":"");
                                                ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" class="content">
                                            <a name="comment-<?php echo (int) $result["cscomment_id"]; ?>"></a>
                                            <?php
                                                echo ((trim($result["comment_title"])) ? "<div style=\"font-weight: bold\">".html_encode(trim($result["comment_title"]))."</div>" : "");
                                                echo $result["comment_description"];

                                                if ($result["release_date"] != $result["updated_date"]) {
                                                    echo "<div class=\"content-small\" style=\"margin-top: 15px\">\n";
                                                    echo "	<strong>Last updated:</strong> ".date(DEFAULT_DATE_FORMAT, $result["updated_date"])." by ".(($result["proxy_id"] == $result["updated_by"]) ? html_encode($result["commenter_fullname"]) : html_encode(get_account_data("firstlast", $result["updated_by"]))).".";
                                                    echo "</div>\n";
                                                }
                                            ?>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                    </tbody>
                                </table>

                            <?php
                            } else {
                                echo "<p>No comments found.</p>";
                            }
                            if ($ADD_COMMENT) {
                            ?>
                                <a href="<?php echo ENTRADA_URL."/profile/gradebook/assignments"; ?>?section=add-comment&assignment_id=<?php echo $RECORD_ID; ?>&pid=<?php echo $USER_ID; ?>">
                                    <button class="btn btn-default">Add Assignment Comment</button>
                                </a>
                            <?php
                            }
                            ?>

                            <div id="dialog-confirm" title="Delete?" style="display: none">
                                <p><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 20px 0;"></span>This item will be permanently deleted and cannot be recovered. Are you sure you want to delete it?</p>
                            </div>

                            <?php
                        }

                        $max_files = (int)$db->GetOne("SELECT `max_file_uploads` FROM `assignments` WHERE `assignment_id`=".$db->qstr($RECORD_ID));
                        $num_files = (int)$db->GetOne("SELECT COUNT(*) FROM `assignment_files` WHERE `assignment_id` = ".$db->qstr($RECORD_ID)." AND `proxy_id` = ".$db->qstr($ENTRADA_USER->getActiveID())." AND `file_active` = 1");
                        ?>
                        <div style="padding-top:15px; text-align:right;">
                            <p>You may upload <?php echo $max_files; ?> file<?php echo $max_files !== 1 ? 's' : ''; ?> for this assignment.</p>
                            <?php if ($num_files < $max_files) { ?>
                            <a href="<?php echo ENTRADA_URL."/profile/gradebook/assignments?section=submit&assignment_id=".$RECORD_ID; ?>">
                                <button class="btn btn-primary">Add Another File</button>
                            </a>
                            <?php } ?>
                        </div>
                        <?php
					}
				} else {
					header("Location: ".ENTRADA_URL."/profile/gradebook/assignments?section=submit&assignment_id=".$RECORD_ID);
					exit;
				}
			}else{
				echo display_error("You do not have authorization to view this resource.");
			}
		}else{
				application_log("error", "The provided file id was invalid [".$RECORD_ID."] (View File).");
				add_error('Invalid id specified. No assignment found for that id.');
				echo display_error();
				exit;
		}

	} else {
		add_error('You do not have authorization to view this resource');
		echo display_error();
	}
} else {
	$url = ENTRADA_URL."/admin/gradebook";
	$ONLOAD[] = "setTimeout('window.location=\\'".$url."\\'', 5000)";

	application_log("error", "No course_id, assignment id or permission was provided to view. (View File)");
	add_error('You are not permitted to view this assignment.<br /><br />You will now be redirected to the <strong>Gradebook index</strong> page.  This will happen <strong>automatically</strong> in 5 seconds or <a href=\"".$url."\" style=\"font-weight: bold\">click here</a> to continue.');

	echo display_error();
}
?>
