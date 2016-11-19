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

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception('401 Unauthorized');
    }

    ajax::init();

    if (init('action') == 'saveISSConfig') {
        imperihome::setIssConfig(json_decode(init('config'), true));
        imperihome::generateISSTemplate();
        ajax::success();
    }

    if (init('action') == 'loadISSConfig') {
        ajax::success(imperihome::getIssConfig());
    }

    if (init('action') == 'getISSStructure') {
        ajax::success(imperihome::getIssStructure(true));
    }

    if (init('action') == 'loadAdvancedDeviceISSConfig') {
		$deviceId = init('deviceId');

		$issAdvancedConfig = imperihome::getIssAdvancedConfig();

		if(array_key_exists($deviceId, $issAdvancedConfig)){
			$cmd = cmd::byId($deviceId);
			if(is_object($cmd)){
				$issAdvancedConfig[$deviceId]['humanName'] = $cmd->getHumanName();
			}else{
				$issAdvancedConfig[$deviceId]['humanName'] = "Cmd support inconnue";
			}

			foreach($issAdvancedConfig[$deviceId]['params'] as $paramName => $param){
				if(strpos(strtolower($param['type']), 'info') !== false){
					$cmd_param = cmd::byId(str_replace("#", "", $param['value']));
					if(is_object($cmd_param)){
						$issAdvancedConfig[$deviceId]['params'][$paramName]['humanName'] = $cmd_param->getHumanName();
					}else{
						$issAdvancedConfig[$deviceId]['params'][$paramName]['humanName'] = "Cmd inconnue";
					}
				}
			}

			foreach($issAdvancedConfig[$deviceId]['actions'] as $actionName => $action){
				if($action['type']=='item'){
					foreach($issAdvancedConfig[$deviceId]['actions'][$actionName]['item'] as $actionItem => $item){
						$cmd_action = cmd::byId($item['cmdId']);
						if(is_object($cmd_action)){
							$issAdvancedConfig[$deviceId]['actions'][$actionName]['item'][$actionItem]['humanName'] = $cmd_action->getHumanName();
						}else{
							$issAdvancedConfig[$deviceId]['actions'][$actionName]['item'][$actionItem]['humanName'] = "Cmd inconnue";
						}
					}
				}else{
					$cmd_action = cmd::byId($action['cmdId']);
					if(is_object($cmd_action)){
						$issAdvancedConfig[$deviceId]['actions'][$actionName]['humanName'] = $cmd_action->getHumanName();
					}else{
						$issAdvancedConfig[$deviceId]['actions'][$actionName]['humanName'] = "Cmd inconnue";
					}
				}
			}

			ajax::success($issAdvancedConfig[$deviceId]);
		}else{
			$device = array();
			$device['id'] = $deviceId;
			$device['type'] = 'noDevice';

			$cmd = cmd::byId($deviceId);
			if(is_object($cmd)){
				$device['humanName'] = $cmd->getHumanName();
			}else{
				$device['humanName'] = "Cmd support inconnue";
			}

			ajax::success($device);
		}
	}

	if (init('action') == 'loadAdvancedISSConfig') {
		$issAdvancedConfig = imperihome::getIssAdvancedConfig();

		foreach ($issAdvancedConfig as $cmd_id => $value) {
			$cmd = cmd::byId($cmd_id);
			if(is_object($cmd)){
				$issAdvancedConfig[$cmd_id]['humanName'] = $cmd->getHumanName();
			}else{
				$issAdvancedConfig[$cmd_id]['humanName'] = "Cmd support inconnue";
			}
		}

		ajax::success($issAdvancedConfig);
	}

	if (init('action') == 'saveAdvancedDevice') {
		$device = json_decode(init('config'), true);
		$issAdvancedConfig = imperihome::getIssAdvancedConfig();
		$issAdvancedConfig[$device['id']] = $device;

		imperihome::setIssAdvancedConfig($issAdvancedConfig);
		imperihome::generateISSTemplate();
		ajax::success();
	}

	if (init('action') == 'deleteAdvancedDevice') {
		$deviceId = init('deviceId');
		$issAdvancedConfig = imperihome::getIssAdvancedConfig();

		if(array_key_exists($deviceId, $issAdvancedConfig)){
			unset($issAdvancedConfig[$deviceId]);
			imperihome::setIssAdvancedConfig($issAdvancedConfig);
			imperihome::generateISSTemplate();
			ajax::success();
		}else{
			throw new Exception(__('Aucun équipement correspondant à cet ID trouvé pour le supprimer: : ', __FILE__) . init('deviceId'));
		}
	}

    throw new Exception(__('Aucune methode correspondante à : ', __FILE__) . init('action'));

    } catch (Exception $e) {
        ajax::error(displayExeption($e), $e->getCode());
    }

?>
