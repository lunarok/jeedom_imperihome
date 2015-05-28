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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function deleteOldISSConfig(){
    $dossier_traite = "../../plugins/imperihome/core/config";
    $repertoire = opendir($dossier_traite); 
     
    while (false !== ($fichier = readdir($repertoire))){
        $chemin = $dossier_traite."/".$fichier;      
        if ($fichier != ".." AND $fichier != "." AND !is_dir($fichier) AND $fichier != "ISS-LocalConfig_mod.json"){
           unlink($chemin); 
        }
    }
    closedir($repertoire);
}

function imperihome_install() {
    if(config::byKey('LocalConfigIsSet', 'imperihome') != '1'){

        if(config::byKey('LocalConfigIsSet', 'imperihome') != '2'){
            // Protection de la configuration du créateur du plugin 
            config::save('LocalConfigIsSet',  '1', 'imperihome');
            deleteOldISSConfig();
            copy("../../plugins/imperihome/core/config/ISS-LocalConfig_mod.json","../../plugins/imperihome/core/config/ISS-LocalConfig-".config::byKey('LocalConfigIsSet', 'imperihome').".json");
        }
        
    }else{
        if(!file_exists("../../plugins/imperihome/core/config/ISS-LocalConfig-".config::byKey('LocalConfigIsSet', 'imperihome').".json")){
            deleteOldISSConfig();
        
            copy("../../plugins/imperihome/core/config/ISS-LocalConfig_mod.json","../../plugins/imperihome/core/config/ISS-LocalConfig-".config::byKey('LocalConfigIsSet', 'imperihome').".json");
        }
    }

    /*
    if((config::byKey('internalAddr') == "") or (config::byKey('internalProtocol') == "")){
        throw new Exception('Impossible d\'activer le plugin Imperihome: veuillez d\'abord compléter la configuration réseau dans la page Général -> Administration -> Configuration .');
    }else{

        $rules = array(
        "location /iss/ {\n try_files" . ' $uri $uri/ ' . "@rewriteiss;\n }",
        "location @rewriteiss {\n rewrite ^/iss/(.*)$ " . config::byKey('internalComplement') . "/plugins/imperihome/core/php/imperihome.php?_url=/$1;\n }",
        );

        try {
            network::nginx_saveRule($rules);
        } catch (Exception $e) {
            log::add('imperihome', 'debug', 'Installation: nginx_saveRule -> ' . $e->getMessage());
        }
    }*/
}

function imperihome_update() {
	copy(dirname(__FILE__)."/../core/config/ISS-LocalConfig-".config::byKey('LocalConfigIsSet', 'imperihome').".json", dirname(__FILE__)."/../core/config/ISS-LocalConfig-".config::byKey('LocalConfigIsSet', 'imperihome')."_Backup.json");

    $ISSLocalConfig_file = file_get_contents(dirname(__FILE__)."/../core/config/ISS-LocalConfig-".config::byKey('LocalConfigIsSet', 'imperihome').".json");
    $ISSLocalConfig = json_decode($ISSLocalConfig_file,true);

    foreach($ISSLocalConfig as $devId => $devConf){
        $ISSLocalConfig[$devId]['params'] =array_change_key_case($ISSLocalConfig[$devId]['params'], CASE_LOWER);

        if(!array_key_exists ( 'confType' , $devConf)){
            $ISSLocalConfig[$devId]['confType'] = 'man';
        }
    }
    
    $res = file_put_contents(dirname(__FILE__)."/../core/config/ISS-LocalConfig-".config::byKey('LocalConfigIsSet', 'imperihome').".json", json_encode($ISSLocalConfig, JSON_FORCE_OBJECT));
}

function imperihome_remove() {

    // Pour les premières versions
    $rules = array(
        "location /iss/ {\n",
        "location @rewriteiss {\n"
        );

    try {
        network::nginx_removeRule($rules);
    } catch (Exception $e) {
        log::add('imperihome', 'debug', 'Désinstallation: nginx_removeRule -> ' . $e->getMessage());
    }
    	
}

?>
