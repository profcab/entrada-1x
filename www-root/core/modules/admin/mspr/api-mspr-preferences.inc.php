<?php
/**
 * Entrada [ http://www.entrada-project.org ]
 *
 * Serves the categories list up in a select box.
 *
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Jonathan Fingland <jonathan.fingland@queensu.ca>
 * @copyright Copyright 2010 Queen's University. All Rights Reserved.
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

if ((isset($_SESSION["isAuthorized"])) && ((bool) $_SESSION["isAuthorized"])) {
	if ($ENTRADA_ACL->amIAllowed("mspr", "create", false)) {

		ob_clear_open_buffers();
		$PROXY_ID = $_SESSION["details"]["id"];
		$user = User::get($PROXY_ID);
		if (isset($_POST['showAll'])) {
			$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["showAll"] = ($_POST['showAll'] == "showAll");
		}
	}
	exit;
}
?>
