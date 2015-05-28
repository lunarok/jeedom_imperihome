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
//require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class imperihome {
	/*     * *************************Attributs****************************** */

	/*     * ***********************Methode static*************************** */

	public static function generateISSTemplate() {
		$template = array('devices' => array());
		$cache = cache::byKey('issConfig');
		$alreadyUsed = array();
		$issConfig = json_decode($cache->getValue('{}'), true);
		foreach ($issConfig as $cmd_id => $value) {
			if (!isset($value['cmd_transmit']) || $value['cmd_transmit'] != 1) {
				continue;
			}
			$cmd = cmd::byId($cmd_id);
			if (!is_object($cmd)) {
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

			$info_device = array(
				"id" => $cmd->getId(),
				"name" => $eqLogic->getName() . ' ' . $cmd->getName(),
				"room" => (is_object($object)) ? $object->getId() : 99999,
				"type" => self::convertType($cmd),
				'params' => array(),
			);
			$info_device['type'] = self::convertType($cmd);

			$cmd_params = self::generateParam($cmd, $info_device['type']);
			$info_device['params'] = $cmd_params['params'];

			foreach ($cmd_params['cmd_id'] as $cmd_used_id) {
				$alreadyUsed[$cmd_used_id] = true;
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
		$cache = cache::byKey('issTemplate');
		$return = json_decode(cmd::cmdToValue($cache->getValue('{}'), false, true), true);
		foreach ($return['devices'] as &$device) {
			foreach ($device['params'] as &$param) {
				if ($param['type'] == 'infoBinary' && ($param['value'] > 0 || $param['value'])) {
					$param['value'] = 1;
				}
			}
		}
		return json_encode($return);
	}

	public static function action($_cmd_id, $_action, $_value) {
		$actions = cmd::byValue($_cmd_id, 'action');
		if (count($actions) > 0) {
			foreach ($actions as $action) {
				if ($action->getSubtype() == 'slider') {
					if ($_action == 'setLevel') {
						$_value = ($_value > 99) ? 99 : $_value;
						$action->execCmd(array('slider' => $_value));
						return;
					}
					if ($_action == 'setStatus') {
						if ($_value == 0) {
							$action->execCmd(array('slider' => 0));
							return;
						} else {
							$action->execCmd(array('slider' => 99));
							return;
						}
					}
				}
				if ($_action == 'setStatus' && $action->getSubtype() == 'other') {
					if ($_value == 0 && strpos(strtolower($cmd->getName()), 'off') !== false) {
						$action->execCmd();
						return;
					}
					if ($_value == 1 && strpos(strtolower($cmd->getName()), 'on') !== false) {
						$action->execCmd();
						return;
					}
				}
			}
		}
		$cmd = cmd::byId($_cmd_id);
		if (!is_object($_cmd)) {
			return;
		}
		$eqLogic = $cmd->getEqLogic();
		$on = null;
		$off = null;
		$slider = null;
		foreach ($eqLogic->getCmd('action') as $action) {
			if ($action->getSubtype() == 'slider') {
				$slider = $action;
				continue;
			}
		}
	}

	public static function generateParam($cmd, $cmdType, $confMode = false) {
		if (method_exists($cmd, 'generateImperihome')) {
			return $cmd->generateImperihome();
		}
		$ISSStructure = json_decode(file_get_contents(dirname(__FILE__) . "/../config/ISS-Structure.json"), true);
		if (!isset($ISSStructure[$cmdType])) {
			return array('params' => array(), 'cmd_id' => array());
		}
		$eqLogic = $cmd->getEqLogic();
		$return = array('params' => $ISSStructure[$cmdType]['params'], 'cmd_id' => array());
		foreach ($return['params'] as $paramKey => &$param) {
			$param['value'] = ($cmd->getType() == 'info') ? '#' . $cmd->getId() . '#' : '';
			if (isset($param['unit'])) {
				$param['unit'] = $cmd->getUnite();
			}
			if (isset($param['graphable'])) {
				$param['graphable'] = ($cmd->getIsHistorized() == 1) ? true : false;
			}
		}
		return $return;
	}

	public function convertType($cmd) {
		switch ($cmd->getEqType()) {
			case "alarm":
				return 'DevMotion';
			case "thermostat":
				return 'DevThermostat';
			case "presence":
				return 'DevMultiSwitch';
			case "camera":
				return 'DevCamera';
			case 'Store':
				return 'DevShutter';
		}
		if (strpos(strtolower($cmd->getName()), 'off') !== false) {
			return 'DevSwitch';
		}
		if (strpos(strtolower($cmd->getTemplate('dashboard')), 'door') !== false) {
			return 'DevDoor';
		}
		if (strpos(strtolower($cmd->getTemplate('dashboard')), 'window') !== false) {
			return 'DevDoor';
		}
		if (strpos(strtolower($cmd->getTemplate('dashboard')), 'porte_garage') !== false) {
			return 'DevDoor';
		}
		if (strpos(strtolower($cmd->getTemplate('dashboard')), 'presence') !== false) {
			return 'DevMotion';
		}
		if (strpos(strtolower($cmd->getTemplate('dashboard')), 'store') !== false) {
			return 'DevShutter';
		}
		if (strpos(strtolower($cmd->getTemplate('dashboard')), 'fire') !== false) {
			return 'DevSmoke';
		}
		if (strpos(strtolower($cmd->getTemplate('dashboard')), 'light') !== false) {
			return 'DevDimmer';
		}
		if (strpos(strtolower($cmd->getName()), 'humidi') !== false) {
			return 'DevHygrometry';
		}
		switch ($cmd->getSubtype()) {
			case 'numeric':
				switch ($cmd->getUnite()) {
				case '°C':
						return 'DevTemperature';
				case '%':
						return 'DevDimmer';
				case 'Pa':
						return 'DevPressure';
				case 'km/h':
						return 'DevWind';
				case 'mm/h':
						return 'DevRain';
				case 'mm':
						return 'DevRain';
				case 'Lux':
						return 'DevLuminosity';
				case 'W':
						return 'DevElectricity';
				case 'KwH':
						return 'DevElectricity';
				}
				return 'DevGenericSensor';
			case 'binary':
				return 'DevSwitch';

		}
		foreach ($cmd->getEqLogic()->getCmd() as $cmd) {
			if ($cmd->getSubtype() == 'color') {
				return 'DevRGBLight';
			}
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

/*     * **********************Getteur Setteur*************************** */
}