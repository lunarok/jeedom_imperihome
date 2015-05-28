<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */
header('Content-type: application/json');
ob_start();
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
$args = explode("/", $_GET['_url']);

if ($args[1] != config::byKey('api') || config::byKey('api') == '') {
	connection::failed();
	echo 'Clef API non valide, vous n\'etes pas autorisé à effectuer cette action (jeeApi)';
	die();
}
log::add('imperihome', 'debug', print_r($args, true));
switch ($args[2]) {
	case "devices":
		if (!isset($args[3])) {
			echo imperihome::devices();
		} elseif ($args[4] == 'action') {
			if (isset($args[6])) {
				echo json_encode(imperihome::action($args[3], $args[5], $args[6]));
			} else {
				//echo json_encode($this->action($args[3], $args[5]));
			}
		} elseif ($args[5] == 'histo') {
			echo json_encode(imperihome::history($args[3], $args[4], $args[6], $args[7]));
		} else {
			http_response_code(404);
			echo json_encode(array("success" => false, "errormsg" => "Format inconnu"));
		}
		break;
	case "rooms":
		echo imperihome::rooms();
		break;
	case "system":
		echo imperihome::system();
		break;
	default:
		http_response_code(404);
		echo json_encode(array("success" => false, "errormsg" => "Format inconnu"));
		break;
}
$out = ob_get_clean();
echo trim(substr($out, strpos($out, '{')));
?>