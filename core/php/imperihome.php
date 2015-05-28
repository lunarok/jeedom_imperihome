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

 // Renvoie uniquement du JSON
 header('Content-type: application/json');
 
 // Début CODE JeeDom OBLIGATOIRE DE SECURITE
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
require_once dirname(__FILE__) . '/../../ressources/imperihomeInterpreter.class.php';

// Récupération des paramètres de l'URL
$URLArgs = explode("/", $_GET['_url']);

if (config::byKey('api') != '') {
	try {
		if($URLArgs[1] != config::byKey('api')){
			if (php_sapi_name() != 'cli' || isset($_SERVER['REQUEST_METHOD']) || !isset($_SERVER['argc'])) {
				if (config::byKey('api') != init('apikey')) {
					connection::failed();
					echo 'Clef API non valide, vous n\'etes pas autorisé à effectuer cette action (jeeApi)';
					log::add('imperihome', 'error', 'Problème avec la clé API, modifiez la puis redémarrez le plugin');
					die();
				}
			}
		}
	} catch (Exception $e) {
        echo $e->getMessage();
        log::add('imperihome', 'error', $e->getMessage());
    }
}
 // Fin CODE JeeDom OBLIGATOIRE DE SECURITE
 


// Création de l'objet Interpreter
//$interpreter = imperihome::getInterpreter();
$interpreter = new imperihomeInterpreter();

// Interpretation des arguments, et impression du résultat
print(json_encode($interpreter->interpret($URLArgs)));

?>