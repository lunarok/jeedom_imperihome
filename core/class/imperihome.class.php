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

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class imperihome extends eqLogic {
	public static function migrateConfig() {
		if (!file_exists(dirname(__FILE__) . '/../../data/ISSConfig.json')) {
            		if (file_exists(dirname(__FILE__) . '/../config/ISSConfig.json')) {
                		copy(dirname(__FILE__) . '/../config/ISSConfig.json', dirname(__FILE__) . '/../../data/ISSConfig.json');
    			}
		}
        	if (!file_exists(dirname(__FILE__) . '/../../data/ISSAdvancedConfig.json')) {
            		if (file_exists(dirname(__FILE__) . '/../config/ISSAdvancedConfig.json')) {
    				copy(dirname(__FILE__) . '/../config/ISSAdvancedConfig.json', dirname(__FILE__) . '/../../data/ISSAdvancedConfig.json');
    			}
		}
	}

	public static function getIssStructure() {
		return json_decode(file_get_contents(dirname(__FILE__) . "/../config/ISS-Structure.json"), true);
	}

	public static function getIssConfig() {
		if (!file_exists(dirname(__FILE__) . '/../../data/ISSConfig.json')) {
			return json_decode(json_encode("{}"), true);
		} else {
			return json_decode(file_get_contents(dirname(__FILE__) . "/../../data/ISSConfig.json"), true);
		}
	}

    	public static function getIssAdvancedConfig(){
        	if (!file_exists(dirname(__FILE__) . '/../../data/ISSAdvancedConfig.json')) {
            		$return = array();
			return $return;
		} else {
			return json_decode(file_get_contents(dirname(__FILE__) . "/../../data/ISSAdvancedConfig.json"), true);
		}
	}

	public static function setIssConfig($_content = '') {
		if (!file_exists(dirname(__FILE__) . '/../../data')) {
			mkdir(dirname(__FILE__) . '/../../data');
		}
		return file_put_contents(dirname(__FILE__) . "/../../data/ISSConfig.json", json_encode($_content));
	}

    	public static function setIssAdvancedConfig($_content = ''){
        	if (!file_exists(dirname(__FILE__) . '/../../data')) {
            		mkdir(dirname(__FILE__) . '/../../data');
       		}
        	return file_put_contents(dirname(__FILE__) . "/../../data/ISSAdvancedConfig.json", json_encode($_content));
	}

	public static function getIssTemplate($_decode = false) {
		$cache = cache::byKey('issTemplate');
		if ($cache->getValue('') != '') {
			imperihome::generateISSTemplate();
			$cache = cache::byKey('issTemplate');
		}
		if ($_decode) {
			return json_decode($cache->getValue('{}'), true);
		}
		return $cache->getValue('{}');
	}

	public static function generateISSTemplate() {
		$ISSStructure = imperihome::getIssStructure();
		$issConfig = imperihome::getIssConfig();
		$template = array('devices' => array());
		$alreadyUsed = array();
		foreach ($issConfig as $cmd_id => $value) {
			if (strpos($cmd_id, 'scenario') !== false) {
				if (!isset($value['scenario_transmit']) || $value['scenario_transmit'] != 1) {
					continue;
				}
				$scenario = scenario::byId(str_replace('scenario', '', $cmd_id));
				if (!is_object($scenario)) {
					continue;
				}
				$object = $scenario->getObject();
				$info_device = array(
					"id" => 'scenario' . $scenario->getId(),
					"name" => $scenario->getName(),
					"room" => (is_object($object)) ? $object->getId() : 99999,
					"type" => 'DevScene',
					'params' => array(),
				);
				$cmd_params = self::generateParam($scenario, $info_device['type'], $ISSStructure);
				$info_device['params'] = $cmd_params['params'];
				$info_device['params'][0]['value'] = '#scenarioLastRun' . $scenario->getId() . '#';
				$template['devices'][] = $info_device;
				continue;
			}
			if (!isset($value['cmd_transmit']) || $value['cmd_transmit'] != 1) {
				continue;
			}
			$cmd = cmd::byId($cmd_id);
			if (!is_object($cmd)) {
				continue;
			}
			if (method_exists($cmd, 'imperihomeCmd') && !$cmd->imperihomeCmd()) {
				continue;
			}
			if ($cmd->getType() != 'info') {
				continue;
			}
			if (isset($alreadyUsed[$cmd_id])) {
				continue;
			}
			$alreadyUsed[$cmd_id] = true;
			$eqLogic = $cmd->getEqLogic();
			if (!is_object($eqLogic)) {
				continue;
			}
			$object = $eqLogic->getObject();
			if (method_exists($cmd, 'imperihomeCmd') && !$cmd->imperihomeCmd()) {
				continue;
			}
			if (method_exists($cmd, 'imperihomeGenerate')) {
				$info_device = $cmd->imperihomeGenerate($ISSStructure);
			} else {
				$info_device = array(
					"id" => $cmd->getId(),
					"name" => ($cmd->getName() == __('Etat', __FILE__)) ? $eqLogic->getName() : $eqLogic->getName().' - '.$cmd->getName(),
					"room" => (is_object($object)) ? $object->getId() : 99999,
					"type" => self::convertType($cmd, $ISSStructure),
					'params' => array(),
				);
				if ($info_device['type'] == 'DevTempHygro') {
					$info_device['name'] = $eqLogic->getName();
				}
				$cmd_params = self::generateParam($cmd, $info_device['type'], $ISSStructure);
				$info_device['params'] = $cmd_params['params'];
				foreach ($cmd_params['cmd_id'] as $cmd_used_id) {
					$alreadyUsed[$cmd_used_id] = true;
				}
			}
			$template['devices'][] = $info_device;
		}

        	$issAdvancedConfig = imperihome::getIssAdvancedConfig();
		foreach ($issAdvancedConfig as $device_id => $device) {
			$cmd = cmd::byId($device_id);
			if (!is_object($cmd)) {
				continue;
			}
			$eqLogic = $cmd->getEqLogic();
			if (!is_object($eqLogic)) {
				continue;
			}
			$object = $eqLogic->getObject();
			$info_device = array(
				"id" => 'manual' . $cmd->getId(),
				"name" => $eqLogic->getName() . '-' . $cmd->getName(),
				"room" => (is_object($object)) ? $object->getId() : 99999,
				"type" => $device['type'],
				'params' => array(),
			);

			foreach ($ISSStructure[$device['type']]['params'] as $param) {
				if ((array_key_exists('key', $param)) and (array_key_exists($param['key'], $device['params'])) and (array_key_exists('value', $device['params'][$param['key']]))) {
					$param['value'] = $device['params'][$param['key']]['value'];
				}
				$info_device['params'][] = $param;
			}

			$template['devices'][] = $info_device;
		}

		$cache = new cache();
		$cache->setKey('issTemplate');
		$cache->setValue(json_encode($template));
		$cache->setLifetime(0);
		$cache->save();
	}

	public static function devices() {
		$return = cmd::cmdToValue(imperihome::getIssTemplate(false), false, true);
		preg_match_all("/#scenarioLastRun([0-9]*)#/", $return, $matches);
		foreach ($matches[1] as $scenario_id) {
			if (is_numeric($scenario_id)) {
				$scenario = scenario::byId($scenario_id);
				if (is_object($scenario)) {
					$return = str_replace('#scenarioLastRun' . $scenario_id . '#', trim(json_encode(strtotime($scenario->getLastLaunch()) * 1000), '"'), $return);
				}
			}
		}

		$return = json_decode($return, true);
		if (is_array($return['devices'])) {
			foreach ($return['devices'] as &$device) {
				if ($device['type'] == 'DevRGBLight') {
					$device['params'][0]['value'] = ($device['params'][0]['value'] != '#000000' && $device['params'][0]['value'] != '#00000000' && $device['params'][0]['value'] != '#0000000000') ? 1 : 0;
					$device['params'][5]['value'] = str_replace(array('#', '"'), '', $device['params'][5]['value']);
					if (strlen($device['params'][5]['value']) == 6) {
						$device['params'][5]['value'] = 'FF' . $device['params'][5]['value'];
					} else {
						if (strlen($device['params'][5]['value']) == 8) {
							$device['params'][5]['value'] = 'FF' . substr($device['params'][5]['value'], 0, 6);
						} else {
							if (strlen($device['params'][5]['value']) == 10) {
								$device['params'][5]['value'] = 'FF' . substr($device['params'][5]['value'], 0, 6);
							}
						}
					}
					continue;
				}
				foreach ($device['params'] as &$param) {
					if (isset($param['type'])) {
						if ($param['type'] == 'infoBinary' && ($param['value'] > 0 || $param['value'])) {
							$param['value'] = 1;
						}
						if ($param['type'] == 'infoNumeric' && isset($param['min']) && isset($param['max'])) {
							$param['value'] = 100 * ($param['value'] - $param['min']) / ($param['max'] - $param['min']);
						}
					}
					if ($param['key'] == 'lasttrip') {
						$param['value'] = strtotime($param['value']) * 1000;
					}
				}
				if ($device['type'] == 'DevMultiSwitch') {
					$value = $device['params'][0]['value'];
					$choice = $device['params'][1]['value'];
					if (strpos($choice, $value) === false) {
						$choices = explode(',', $choice);
						if (isset($choices[$device['params'][0]['value'] - 1])) {
							$device['params'][0]['value'] = $choices[$device['params'][0]['value'] - 1];
						}
					}
					$device['params'][0]['value'] = str_replace('"', '', $device['params'][0]['value']);
				}
				if ($device['type'] == 'DevGenericSensor') {
					$device['params'][0]['value'] = str_replace(array('"', '<br/>', '<br>'), array('', ' ', ' '), $device['params'][0]['value']);
				}
			}
		}
		return json_encode($return);
	}

    	public static function action($_cmd_id, $_action, $_value = '') {
		log::add('imperihome', 'debug', 'Reception d\'une action "' . $_action . '(' . $_value . ')" sur ' . $_cmd_id);

		if ($_action == 'launchScene' && strpos($_cmd_id, 'scenario') !== false) {
			$scenario = scenario::byId(str_replace('scenario', '', $_cmd_id));
			if (!is_object($scenario)) {
				return array("success" => false, "errormsg" => __('Commande inconnue', __FILE__));
			}
			$scenario->launch(false, 'imperihome', __('Lancement provoque par Imperihome ', __FILE__));
			return array("success" => true, "errormsg" => "");
		}

		if (strpos(strtolower($_cmd_id), 'manual') !== false) {
			$_cmd_id = str_replace("manual", "", $_cmd_id);

			log::add('imperihome', 'debug', 'Type manuelle: id=' . $_cmd_id);

			$issAdvancedConfig = imperihome::getIssAdvancedConfig();

			$action = $issAdvancedConfig[$_cmd_id]['actions'][$_action];
			if ($action['type'] == 'item') {
				$actionCmdId = $action['item'][$_value]['cmdId'];
			} else {
				$actionCmdId = $action['cmdId'];
			}

			if (($_action == 'setLevel') and ($actionCmdId == '') and ($issAdvancedConfig[$_cmd_id]['type'] == 'DevShutter')) {
				log::add('imperihome', 'debug', 'Type manuelle devShutter: SetLevel ActionId vide, value=' . $_value . ' -> transformation en pulseShutter');
				if ($_value == 100) {
					$actionCmdId = $issAdvancedConfig[$_cmd_id]['actions']['pulseShutter']['item']['up']['cmdId'];
					log::add('imperihome', 'debug', 'Type manuelle devShutter: SetLevel ActionId vide, value=' . $_value . ' -> transformation en pulseShutter(up) sur cmdId=' . $actionCmdId);
				}
				if ($_value == 0) {
					$actionCmdId = $issAdvancedConfig[$_cmd_id]['actions']['pulseShutter']['item']['down']['cmdId'];
					log::add('imperihome', 'debug', 'Type manuelle devShutter: SetLevel ActionId vide, value=' . $_value . ' -> transformation en pulseShutter(down) sur cmdId=' . $actionCmdId);
				}

				$cmd = cmd::byId($actionCmdId);
				if (is_object($cmd)) {
					$cmd->execCmd();
					return array("success" => true, "errormsg" => "");
					log::add('imperihome', 'debug', 'Type manuelle devShutter: SetLevel ActionId vide, execution de la cmd id=' . $cmd->getId() . ' - ' . $cmd->getName());
				}
				return array("success" => false, "errormsg" => __('Commande inconnue', __FILE__));
			}

//         		if (($_action == 'setChoice') and ($actionCmdId == '')) {
			if ((($_action == 'setChoice') or ($_action == 'setMode')) and ($actionCmdId != $_cmd_id)) {
		       		log::add('imperihome', 'debug', 'Commande actionCmdId : ' . $actionCmdId);
		     	  	log::add('imperihome', 'debug', 'Commande _cmd_id : ' . $_cmd_id);				
				$cmd = cmd::byId($_cmd_id);
				if (!is_object($cmd)) {
					return array("success" => false, "errormsg" => __('Commande inconnue', __FILE__));
				}
				$eqlogic = $cmd->getEqLogic();
				$action = cmd::byEqLogicIdCmdName($eqlogic->getId(), $_value);
				if (is_object($action)) {
					$action->execCmd();
					log::add('imperihome', 'debug', 'Type ' . $_action . ': execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
				}
				//return array("success" => true, "errormsg" => "");
			}

			log::add('imperihome', 'debug', 'Type manuelle: ActionId=' . $actionCmdId);

			$cmd = cmd::byId($actionCmdId);

			if (!is_object($cmd)) {
				log::add('imperihome', 'debug', 'Commande introuvable');
				return array("success" => false, "errormsg" => __('Commande inconnue', __FILE__));
			}

			if ($cmd->getSubtype() == 'color') {
				$cmd->execCmd(array('color' => '#' . substr($_value, 2)));
				log::add('imperihome', 'debug', 'Action Color éxécutée');
				return array("success" => true, "errormsg" => "");
			}

			if ($cmd->getSubtype() == 'slider') {
				if ($_action != 'setSetPoint') {
					$_value = ($cmd->getConfiguration('maxValue', 100) - $cmd->getConfiguration('minValue', 0)) * ($_value / 100) + $cmd->getConfiguration('minValue', 0);
				}
				$cmd->execCmd(array('slider' => $_value));
				log::add('imperihome', 'debug', 'Action ' . $_action . ' éxécutée, value = ' . $_value);
				return array("success" => true, "errormsg" => "");
			}

			if ($cmd->getSubtype() == 'message') {
				$cmd->execCmd(array('message' => $_value));
				log::add('imperihome', 'debug', 'Action Message éxécutée, value = ' . $_value);
				return array("success" => true, "errormsg" => "");
			}

			if ($cmd->getSubtype() == 'other') {
				$cmd->execCmd();
				log::add('imperihome', 'debug', 'Action Other éxécutée');
				return array("success" => true, "errormsg" => "");
			}
		}

		$cmd = cmd::byId($_cmd_id);
		if (method_exists($cmd, 'imperihomeAction')) {
			$cmd->imperihomeAction($_action, $_value);
			log::add('imperihome', 'debug', 'Action imperihome associée à la commande connue');
			return array("success" => true, "errormsg" => "");
		}
		if (method_exists($cmd, 'imperihomeAction')) {
			return $cmd->imperihomeAction($_action, $_value);
			log::add('imperihome', 'debug', 'Action imperihome associée à la commande connue');
		}
		if ($_action == 'setChoice') {
			if (!is_object($cmd)) {
				return array("success" => false, "errormsg" => __('Commande inconnue', __FILE__));
			}
			if ($cmd->getEqType() == 'presence') {
				$eqlogic = $cmd->getEqLogic();
				$action = cmd::byEqLogicIdCmdName($eqlogic->getId(), $_value);
				if (is_object($action)) {
					$action->execCmd();
					log::add('imperihome', 'debug', 'Type setChoice: execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
				}
			}
			return array("success" => true, "errormsg" => "");
		}
		$actions = cmd::byValue($_cmd_id, 'action');
		if (count($actions) == 0) {
			$actions = $cmd->getEqLogic()->getCmd('action');
		}
		if (count($actions) > 0) {
			foreach ($actions as $action) {
				log::add('imperihome', 'debug', '--> '.$action->getId().' = '.$action->getName());
				if ($action->getSubtype() == 'color') {
					if ($_action == 'setColor') {
						$action->execCmd(array('color' => '#' . substr($_value, 2)));
						log::add('imperihome', 'debug', 'Type setColor: execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
						return array("success" => true, "errormsg" => "");
					}
					if ($_action == 'setStatus') {
						if ($_value == 0) {
							$action->execCmd(array('color' => '#000000'));
							log::add('imperihome', 'debug', 'Type color setStatus(0): execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
							return array("success" => true, "errormsg" => "");
						} else {
							$action->execCmd(array('color' => '#FFFFFF'));
							log::add('imperihome', 'debug', 'Type color setStatus(1): execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
							return array("success" => true, "errormsg" => "");
						}
					}
				}

				if ($action->getSubtype() == 'slider') {
					if ($_action == 'setLevel') {
						$_value = ($action->getConfiguration('maxValue', 100) - $action->getConfiguration('minValue', 0)) * ($_value / 100) + $action->getConfiguration('minValue', 0);
						$action->execCmd(array('slider' => $_value));
						log::add('imperihome', 'debug', 'Type setLevel: execution de la cmd id=' . $action->getId() . ' - ' . $action->getName() . ' Val=' . $_value);
						return;
					}
					if ($_action == 'setStatus') {
						if ($_value == 0) {
							$action->execCmd(array('slider' => $action->getConfiguration('minValue', 0)));
							log::add('imperihome', 'debug', 'Type slider setStatus(0): execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
							return array("success" => true, "errormsg" => "");
						} else {
							$action->execCmd(array('slider' => $action->getConfiguration('maxValue', 100)));
							log::add('imperihome', 'debug', 'Type slider setStatus(0): execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
							return array("success" => true, "errormsg" => "");
						}
					}
				}

				if ($_action == 'setStatus' && $action->getSubtype() == 'other') {
					if ($_value == 0 && strpos(strtolower($action->getName()), 'off') !== false) {
						$action->execCmd();
						log::add('imperihome', 'debug', 'Type other setStatus(0): execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
						return array("success" => true, "errormsg" => "");
					}
					if ($_value == 1 && strpos(strtolower($action->getName()), 'on') !== false && strpos(strtolower($action->getName()), 'impulsion') === false) {
						$action->execCmd();
						log::add('imperihome', 'debug', 'Type other setStatus(1): execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
						return array("success" => true, "errormsg" => "");
					}
				}

				if ($_action == 'pulse' && $action->getSubtype() == 'other') {
					$action->execCmd();
					log::add('imperihome', 'debug', 'Type other pulse(): execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
					return array("success" => true, "errormsg" => "");
				}

				if ($_action == 'stopShutter' && $action->getSubtype() == 'other' && strpos(strtolower($action->getName()), 'stop') !== false) {
					$action->execCmd();
					log::add('imperihome', 'debug', 'Type other stopShutter: execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
					return array("success" => true, "errormsg" => "");
				}
				if ($_action == 'pulseShutter' && $action->getSubtype() == 'other') {
					if ($_value == 'down' && (strpos(strtolower($action->getName()), 'descendre') !== false || strpos(strtolower($action->getName()), 'down') !== false || strpos(strtolower($action->getName()), 'ferme') !== false || strpos(strtolower($action->getName()), 'bas') !== false)) {
						$action->execCmd();
						log::add('imperihome', 'debug', 'Type other pulseShutter down: execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
						return array("success" => true, "errormsg" => "");
					}
					if ($_value == 'up' && (strpos(strtolower($action->getName()), 'monter') !== false || strpos(strtolower($action->getName()), 'up') !== false || strpos(strtolower($action->getName()), 'ouvre') !== false || strpos(strtolower($action->getName()), 'haut') !== false)) {
						$action->execCmd();
						log::add('imperihome', 'debug', 'Type other pulseShutter up: execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
						return array("success" => true, "errormsg" => "");
					}
				}
				if ($_action == 'setLevel' && $action->getSubtype() == 'other') {
					if ($_value == '0' && (strpos(strtolower($action->getName()), 'descendre') !== false || strpos(strtolower($action->getName()), 'down') !== false || strpos(strtolower($action->getName()), 'ferme') !== false || strpos(strtolower($action->getName()), 'bas') !== false)) {
						$action->execCmd();
						log::add('imperihome', 'debug', 'Type other setLevel(0): execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
						return array("success" => true, "errormsg" => "");
					}
					if ($_value == '100' && (strpos(strtolower($action->getName()), 'monter') !== false || strpos(strtolower($action->getName()), 'up') !== false || strpos(strtolower($action->getName()), 'ouvre') !== false || strpos(strtolower($action->getName()), 'haut') !== false)) {
						$action->execCmd();
						log::add('imperihome', 'debug', 'Type other setLevel(1): execution de la cmd id=' . $action->getId() . ' - ' . $action->getName());
						return array("success" => true, "errormsg" => "");
					}
				}
			}
		}
	}

	public static function generateParam($cmd, $cmdType, $ISSStructure) {
		if ($cmdType == "DevScene") {
			return array('params' => $ISSStructure[$cmdType]['params'], 'cmd_id' => array());
		}
		$eqLogic = $cmd->getEqLogic();
		$return = array('params' => $ISSStructure[$cmdType]['params'], 'cmd_id' => array());
		foreach ($return['params'] as &$param) {
			if (isset($param['type']) && $param['type'] == 'optionBinary') {
				continue;
			}
			$param['value'] = ($cmd->getType() == 'info') ? '#' . $cmd->getId() . '#' : '';
			if (isset($param['unit'])) {
				$param['unit'] = $cmd->getUnite();
			}
			if (isset($param['graphable'])) {
				$param['graphable'] = ($cmd->getIsHistorized() == 1) ? true : false;
			}
			if (isset($param['min']) && isset($param['max'])) {
				$param['min'] = $cmd->getConfiguration('minValue', 0);
				$param['max'] = $cmd->getConfiguration('maxValue', 100);

				if ($cmd->getSubType() == 'binary') {
					$param['max'] = 1;
				}
			}
			if ($param['key'] == 'lasttrip') {
				$param['value'] = ($cmd->getType() == 'info') ? '#valueDate' . $cmd->getId() . '#' : 0;
			}
			if ($cmdType == 'DevSwitch' && $param['key'] == 'pulseable' && $cmd->getType() == 'action') {
				$param['value'] = 1;
			}
			if (($cmdType == 'DevSwitch' || $cmdType == 'DevRGBLight' || $cmdType == 'DevDimmer') && $param['key'] == 'energy') {
				$param['value'] = '';
				foreach ($cmd->getEqLogic()->getCmd('info') as $info) {
					if (strtolower($info->getUnite()) == 'w') {
						$param['unit'] = $info->getUnite();
						$param['value'] = '#' . $info->getId() . '#';
						$param['graphable'] = false;
						break;
					}
				}
			}
			if ($cmdType == 'DevElectricity' && $param['key'] == 'consototal') {
				$param['value'] = '';
				foreach ($cmd->getEqLogic()->getCmd('info') as $info) {
					if (strtolower($info->getUnite()) == 'kwh') {
						$param['unit'] = $info->getUnite();
						$param['value'] = '#' . $info->getId() . '#';
						$param['graphable'] = false;
						break;
					}
				}
			}
			if ($cmdType == 'DevTempHygro') {
				if ($param['key'] == 'temp') {
					if ($cmd->getUnite() == '°C') {
						$param['value'] = '#' . $cmd->getId() . '#';
					} else {
						foreach ($cmd->getEqLogic()->getCmd('info') as $info) {
							if ($info->getUnite() == '°C') {
								$param['value'] = '#' . $info->getId() . '#';
								$param['unit'] = $info->getUnite();
								$return['cmd_id'][] = $info->getId();
								break;
							}
						}
					}
				}

				if ($param['key'] == 'hygro') {
					if (strpos(strtolower($cmd->getName()), __('humidité', __FILE__)) !== false) {
						$param['value'] = '#' . $cmd->getId() . '#';
					} else {
						foreach ($cmd->getEqLogic()->getCmd('info') as $info) {
							if (strpos(strtolower($info->getName()), __('humidité', __FILE__)) !== false) {
								$param['value'] = '#' . $info->getId() . '#';
								$param['unit'] = $info->getUnite();
								$return['cmd_id'][] = $info->getId();
								break;
							}
						}
					}
				}
			}
		}
		return $return;
	}

	public static function convertType($cmd, $ISSStructure, $_direct = false) {
		if (!$_direct && method_exists($cmd, 'imperihomeGenerate')) {
			$info_device = $cmd->imperihomeGenerate($ISSStructure);
			return $info_device['type'];
		}
		$issConfig = imperihome::getIssConfig();
		switch ($cmd->getDisplay('generic_type')) {
			case "LIGHT_STATE":
			foreach ($cmd->getEqLogic()->getCmd('action') as $action) {
				if ($action->getDisplay('generic_type') == 'LIGHT_SLIDER') {
					return 'DevDimmer';
				}
			}
			return 'DevSwitch';
			case "LIGHT_COLOR":
			return 'DevRGBLight';
			case "ENERGY_STATE":
			foreach ($cmd->getEqLogic()->getCmd('action') as $action) {
				if ($action->getDisplay('generic_type') == 'ENERGY_SLIDER') {
					return 'DevDimmer';
				}
			}
			return 'DevSwitch';return 'DevSwitch';
			case "FLAP_STATE":
			case "FLAP_BSO_STATE":
			return 'DevShutter';
			case "HEATING_STATE":
			return 'DevSwitch';
			case "LOCK_STATE":
			return 'DevLock';
			case "SIREN_STATE":
			return 'DevSwitch';
			case "THERMOSTAT_STATE":
			return 'DevThermostat';
			case "MODE_STATE":
			return 'DevMultiSwitch';
			case "ALARM_STATE":
			case "ALARM_ENABLE_STATE":
			return 'DevSwitch';
			case "ALARM_MODE":
			return 'DevMultiSwitch';
			case "POWER":
			return 'DevElectricity';
			case "CONSUMPTION":
			return 'DevElectricity';
			case "TEMPERATURE":
			foreach ($cmd->getEqLogic()->getCmd('info') as $info) {
				if ($info->getDisplay('generic_type') == 'HUMIDITY') {
					if (isset($issConfig[$info->getId()]) && $issConfig[$info->getId()]['cmd_transmit'] == 1) {
						return 'DevTempHygro';
					}
				}
			}
			return 'DevTemperature';
			case "BRIGHTNESS":
			return 'DevLuminosity';
			case "PRESENCE":
			return 'DevMotion';
			case "BATTERY":
			return 'DevGenericSensor';
			case "SMOKE":
			return 'DevSmoke';
			case "FLOOD":
			return 'DevFlood';
			case "HUMIDITY":
			foreach ($cmd->getEqLogic()->getCmd('info') as $info) {
				if ($info->getDisplay('generic_type') == 'TEMPERATURE') {
					if (isset($issConfig[$info->getId()]) && $issConfig[$info->getId()]['cmd_transmit'] == 1) {
						return 'DevTempHygro';
					}
				}
			}
			return 'DevHygrometry';
			case "UV":
			return 'DevUV';
			case "OPENING":
			return 'DevDoor';
			case "SABOTAGE":
			return 'DevDoor';
			case "CO2":
			return 'DevCO2';
			case "VOLTAGE":
			return 'DevElectricity';
			case "NOISE":
			return 'DevNoise';
			case "PRESSURE":
			return 'DevPressure';
			case "RAIN_CURRENT":
			case "RAIN_TOTAL":
			return 'DevRain';
			case "WIND_SPEED":
			return 'DevWind';
			case "SHOCK":
			return 'DevMotion';
		}
		if (strpos(strtolower($cmd->getName()), __('humidité', __FILE__)) !== false) {
			foreach ($cmd->getEqLogic()->getCmd('info') as $info) {
				if ($info->getUnite() == '°C') {
					if (isset($issConfig[$info->getId()]) && $issConfig[$info->getId()]['cmd_transmit'] == 1) {
						return 'DevTempHygro';
					}
				}
			}
			return 'DevHygrometry';
		}
		if (strtolower($cmd->getName()) == __('uv', __FILE__)) {
			return 'DevUV';
		}
		$eqlogic = $cmd->getEqLogic();
		if (strpos(strtolower($cmd->getName()), __('etat', __FILE__)) !== false) {
			if (strpos(strtolower($eqlogic->getName()), __('fenêtre', __FILE__)) !== false || strpos(strtolower($eqlogic->getName()), __('fenetre', __FILE__)) !== false || strpos(strtolower($eqlogic->getName()), __('porte', __FILE__)) !== false) {
				return 'DevDoor';
			}
		}

		switch ($cmd->getSubtype()) {
			case 'numeric':
			switch (strtolower($cmd->getUnite())) {
				case '°c':
				$issConfig = imperihome::getIssConfig();
				foreach ($cmd->getEqLogic()->getCmd('info') as $info) {
					if (strpos(strtolower($info->getName()), __('humidité', __FILE__)) !== false) {
						if (isset($issConfig[$info->getId()]) && $issConfig[$info->getId()]['cmd_transmit'] == 1) {
							return 'DevTempHygro';
						}
					}
				}
				return 'DevTemperature';
				case '%':
				if (count(cmd::byValue($cmd->getId(), 'action')) == 0) {
					return 'DevGenericSensor';
				}
				return 'DevDimmer';
				case 'pa':
				case 'hpa':
				case 'bar':
				return 'DevPressure';
				case '% rh':
				return 'DevHygrometry';
				case 'db':
				return 'DevNoise';
				case 'km/h':
				return 'DevWind';
				case 'mm/h':
				return 'DevRain';
				case 'mm':
				return 'DevRain';
				case 'm3':
				return 'DevFlood';
				case 'ppm':
				return 'DevCO2';
				case 'lux':
				return 'DevLuminosity';
				case 'w':
				case 'kwh':
				case 'a':
				case 'v':
				case 'w/min':
				return 'DevElectricity';
			}
			return 'DevGenericSensor';
			case 'binary':
			return 'DevGenericSensor';

		}
		if ($cmd->getType() == 'action') {
			return 'DevSwitch';
		}
		return 'DevGenericSensor';
	}

	public function rooms() {
		$response = array();
		foreach (object::all() as $object) {
			$response[] = array(
				'id' => $object->getId(),
				'name' => $object->getName(),
			);
		}
		$response[] = array(
			'id' => 99999,
			'name' => __('Aucun', __FILE__),
		);
		return json_encode(array("rooms" => $response));
	}

	public function system() {
		return json_encode(array('id' => config::byKey('api'), 'apiversion' => "1"));
	}

	public function history($_cmd_id, $_paramKey, $_startdate, $_enddate) {
		$issTemplate = imperihome::getIssTemplate(true);
		foreach ($issTemplate['devices'] as $device) {
			if ($device['id'] == $_cmd_id) {
				foreach ($device['params'] as $param) {
					if (strtolower($param['key']) == strtolower($_paramKey)) {
						if (preg_match("/#([0-9]*)#/", $param['value'], $matches)) {
							$cmd_id = $matches[1];
							$cmd = cmd::byId($cmd_id);
							$history = array();
							if (is_object($cmd)) {
								foreach ($cmd->getHistory(date('Y-m-d H:i:s', ($_startdate / 1000)), date('Y-m-d H:i:s', ($_enddate / 1000))) as $histoItem) {
									$history[] = array('value' => floatval($histoItem->getValue()), 'date' => strtotime($histoItem->getDatetime()) * 1000);
								}
							}
						}
						return array('values' => $history);
					}
				}
				break;
			}
		}
	}

	public function postUpdate() {
    		$reco = $this->getCmd(null, 'reco');
		if (!is_object($reco)) {
			$reco = new imperihomeCmd();
			$reco->setLogicalId('reco');
			$reco->setName(__('Reconnaissance Vocale', __FILE__));
		}
		$reco->setType('action');
		$reco->setSubType('other');
		$reco->setEqLogic_id($this->getId());
		$reco->save();
		$page = $this->getCmd(null, 'page');
		if (!is_object($page)) {
			$page = new imperihomeCmd();
			$page->setLogicalId('page');
			$page->setName(__('Ouvrir Page', __FILE__));
		}
		$page->setType('action');
		$page->setSubType('message');
		$page->setEqLogic_id($this->getId());
		$page->save();
        	$page = $this->getCmd(null, 'wakeup');
		if (!is_object($page)) {
			$page = new imperihomeCmd();
			$page->setLogicalId('wakeup');
			$page->setName(__('Réveiller', __FILE__));
		}
		$page->setType('action');
		$page->setSubType('other');
		$page->setEqLogic_id($this->getId());
		$page->save();
		$tts = $this->getCmd(null, 'tts');
		if (!is_object($tts)) {
			$tts = new imperihomeCmd();
			$tts->setLogicalId('tts');
			$tts->setName(__('TTS', __FILE__));
			$tts->setEqLogic_id($this->getId());
		}
		$tts->setType('action');
		$tts->setSubType('message');
		$tts->setEqLogic_id($this->getId());
		$tts->save();
        	$tts = $this->getCmd(null, 'camera');
		if (!is_object($tts)) {
			$tts = new imperihomeCmd();
			$tts->setLogicalId('camera');
			$tts->setName(__('Ouvrir Camera', __FILE__));
			$tts->setEqLogic_id($this->getId());
		}
		$tts->setType('action');
		$tts->setSubType('message');
		$tts->setEqLogic_id($this->getId());
		$tts->save();
  }

}

class imperihomeCmd extends cmd {
	public function preSave() {
		if ($this->getSubtype() == 'message') {
            		if ($this->getLogicalId() == "tts") {
                		$this->setDisplay('title_disable', 0);
            		} else {
                		$this->setDisplay('title_disable', 1);
            		}
		}
	}

	public static function cleanSMS($_message) {
  	$caracteres = array(
      'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ä' => 'a', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', '@' => 'a',
      'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', '€' => 'e',
      'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
      'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Ö' => 'o', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
      'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'µ' => 'u',
      'Œ' => 'oe', 'œ' => 'oe', ' ' => '+',
      '$' => 's');
  	return preg_replace('#[^A-Za-z0-9 \n\.\'=\*:]+#', '', strtr($_message, $caracteres));
	}

	public function execute($_options = null) {
		$imperihome = $this->getEqLogic();
		$imperihome_ip = $imperihome->getConfiguration('addr');
		if ($this->getLogicalId() == "tts") {
			$message = imperihomeCmd::cleanSMS(trim($_options['message']), true);
			$url = 'http://' . $imperihome_ip . '/api/rest/speech/tts?text=' . $message;
            		if ($_options['message'] != '' && is_numeric($_options['message'])) {
                		$url .= '&vol=' . trim($_options['message']);
            		}
		}
		if ($this->getLogicalId() == 'reco') {
			$url = 'http://' . $imperihome_ip . '/api/rest/speech/launchreco';
		}
        	if ($this->getLogicalId() == 'wakeup') {
			$url = 'http://' . $imperihome_ip . '/api/rest/dashboard/wakeup';
		}
		if ($this->getLogicalId() ==  'page') {
			$message = trim($_options['message']);
			$url = 'http://' . $imperihome_ip . '/api/rest/dashboard/gotopage?pageIdx=' . $message;
		}
       		if ($this->getLogicalId() ==  'camera') {
			$message = trim($_options['message']);
			$url = 'http://' . $imperihome_ip . '/api/rest/camera/view?devid=' . $message;
		}

		curl_setopt_array($ch = curl_init(), array(
			CURLOPT_URL => $url,
			//CURLOPT_POSTFIELDS => array(
			//    "Name" => $this->getConfiguration('name'),
			//    "ImpAction" =>  $this->getConfiguration('ImpAction'),
			//    "Param" => $_options['Param'] ,
			//     ),
			CURLOPT_SAFE_UPLOAD => true,
		));
		curl_exec($ch);
		curl_close($ch);
	}
}
