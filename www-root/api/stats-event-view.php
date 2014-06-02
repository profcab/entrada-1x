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
 * this file loads the views for the event sorted different way
 *
 * @author Organization: David Geffen School of Medicine at UCLA
 * @author Unit: Instructional Design and Technology Unit
 * @author Developer: Sam Payne <spayne@mednet.ucla.edu>
 * @copyright Copyright 2013 Regents of The University of California. All Rights Reserved.
 *
*/

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


if (!$ENTRADA_ACL->amIAllowed(new EventContentResource($event_info["event_id"], $event_info["course_id"], $event_info["organisation_id"]), "update")) {
        application_log("error", "Someone attempted to view statistics for an event [".$EVENT_ID."] that they were not the coordinator for.");

        header("Location: ".ENTRADA_URL."/admin/".$MODULE);
        exit;
} else { 

    $PROCESSED["proxy_id"] = $ENTRADA_USER->getID();
                                if ($_POST["sortID"] == "name") {
                                    $sortOrder = "users.lastname";
                                }
                                if ($_POST["sortID"] == "date") {
                                    $sortOrder = "lastViewedTime";
                                }
                                if ($_POST["sortID"] == "view") {
                                    $sortOrder = "views";
                                }                                
                                   //This will create a record set that has the proxyid, firstname, lastname, last timestamp, view per user. 
                                $viewsSQL = "   SELECT DISTINCT (stats.proxy_id), COUNT(*) AS views, users.firstname, users.lastname, MAX(stats.timestamp) as lastViewedTime 
                                                FROM  " . DATABASE_NAME . ".statistics AS stats,  " . AUTH_DATABASE . ".user_data AS users 
                                                WHERE stats.module = 'events' 
                                                AND stats.action = 'view' 
                                                AND stats.action_field = 'event_id' 
                                                AND stats.action_value = " . $_POST["eventID"] . " 
                                                AND stats.proxy_id = users.id 
                                                GROUP BY stats.proxy_id
                                                ORDER BY " . $sortOrder . " " . $_POST["sortOrder"];
                                $statistics = $db->GetAll($viewsSQL);
                                
                                
                                $totalViews = 0;   
                                $userViews = 0;
                                $statsHTML = "";
                                foreach ($statistics as $stats) {
                                    $statsHTML .=   "<li class='statsLI'><span class='sortStats sortStatsName'>" . $stats["lastname"] . ", " . $stats["firstname"] . "</span><span class='sortStats sortStatsViews'>" . $stats["views"] . "</span><span class='sortStats sortStatsDate'>" . date("m-j-Y g:ia", $stats["lastViewedTime"]) . "</span></li>";
                                    $userViews++;
                                    $totalViews = $totalViews + $stats["views"];
                                }
                                $record = array();
                                $record["userViews"] = $userViews;
                                $record["totalViews"] = $totalViews;
                                $record["statsHTML"] = $statsHTML;
                                $record["viewSQL"] = $viewsSQL;
    header("Content-type: application/json");
    echo json_encode($record);
}
?>