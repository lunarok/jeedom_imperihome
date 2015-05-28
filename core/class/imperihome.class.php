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
require_once dirname(__FILE__) . '/../../ressources/imperihomeInterpreter.class.php';

class imperihome extends eqLogic {
	/*     * *************************Attributs****************************** */

	private static $_ISSLocalConfig;
	private static $_ISSStructure;
	private static $_cmds = array();
	private static $_eqLogics = array();
	private static $_objects = array();

	/*     * ***********************Methode static*************************** */

	// Fonction appelé par l'API avec les arguments
	public function interpret($args) {
		switch ($args[2]) {
			case "devices":
				if (!isset($args[3])) {
					return $this->devices();
				} elseif ($args[4] == 'action') {
					if (isset($args[6])) {
						return $this->action($args[3], $args[5], $args[6]);
					} else {
						return $this->action($args[3], $args[5]);
					}
				} elseif ($args[5] == 'histo') {
					return $this->histo($args[3], $args[4], $args[6], $args[7]);
				} else {
					http_response_code(404);
					return array("success" => false, "errormsg" => "Format inconnu");
				}
				break;
			case "rooms":
				return $this->rooms();
				break;
			case "system":
				return $this->system();
				break;
			default:
				http_response_code(404);
				return array("success" => false, "errormsg" => "Format inconnu");
				break;
		}
	}

	public function init() {
		self::$_ISSLocalConfig = json_decode(file_get_contents(dirname(__FILE__) . "/../core/config/ISS-LocalConfig-" . config::byKey('LocalConfigIsSet', 'imperihome') . ".json"), true);
		if (!is_array(self::$_ISSLocalConfig)) {
			self::$_ISSLocalConfig = array();
		}
		self::$_ISSStructure = json_decode(file_get_contents(dirname(__FILE__) . "/ISS-Structure.json"), true);
		if (!is_array(self::$_ISSStructure)) {
			self::$_ISSStructure = array();
		}
		foreach (object::all(true) as $object) {
			$this->objects[$object->getId()] = utils::o2a($object);
			foreach ($object->getEqLogic(true, true) as $eqLogic) {
				$this->eqLogics[$eqLogic->getId()] = utils::o2a($eqLogic);
				foreach ($eqLogic->getCmd() as $cmd) {
					if (isset($this->ISSLocalConfig[$cmd->getId()])) {
						$this->cmds[$cmd->getId()] = utils::o2a($cmd);
						if ($cmd->getType() == 'info') {
							$this->cmds[$cmd->getId()]['state'] = $cmd->execCmd(null, 2);
						}
					}
				}
			}
		}
	}

	public function convertType($cmd) {
		if (!is_array($this->ISSStructure)) {
			$this->init();
		}
		if ($this->isConfMan($cmd['id'])) {
			return $this->ISSLocalConfig[$cmd['id']]['type'];
		} else {
			switch ($cmd['eqType']) {
				case "alarm":
					return 'DevMotion';
					break;
				case "thermostat":
					return 'DevThermostat';
					break;
				case "presence":
					return 'DevMultiSwitch';
					break;
				case "camera":
					return 'DevCamera';
					break;
				default:
					switch ($cmd['subType']) {
					case 'numeric':
							switch ($cmd['unite']) {
						case '°C':
									return 'DevTemperature';
									break;
						case '%':
									if (isset($cmd['template']['dashboard'])) {
										switch ($cmd['template']['dashboard']) {
								case 'door':
								case 'window':
								case 'porte_garage':
												return 'DevDoor';
								case 'store':
												return 'DevShutter';
								case 'light':
												return 'DevDimmer';
								default:
												return 'DevDimmer';
										}
									} else {
										return 'DevDimmer';
									}
									break;

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
						default:
									if (isset($cmd['eqType'])) {
										switch ($cmd['eqType']) {
								case 'Store':
												return 'DevShutter';
								default:
												return 'DevGenericSensor';
										}
									} else {
										return 'DevGenericSensor';
									}
									break;
							}
							break;

					case 'binary':
							if (isset($cmd['template']['dashboard'])) {
								switch ($cmd['template']['dashboard']) {
							case 'door':
							case 'window':
							case 'porte_garage':
										return 'DevDoor';
							case 'fire':
										return 'DevSmoke';
							case 'presence':
										return 'DevMotion';

							case 'store':
										return 'DevShutter';
							default:
										return 'DevSwitch';
								}
							} else {
								$response = 'DevSwitch';
								break;
							}
							break;

					default:
							if (isset($this->eqLogics[$cmd['eqLogic_id']]['cmds'])) {
								foreach ((array) $this->eqLogics[$cmd['eqLogic_id']]['cmds'] as $nearestCmd) {
									if ($nearestCmd['subType'] == 'color') {
										return 'DevRGBLight';
									} else {
										return 'DevGenericSensor';
									}
								}
							}
					}
			}
		}
		return '';
	}

	// Retourne les infos système
	public function system() {
		$response = array('id' => config::byKey('api'), 'apiversion' => "1");
		return $response;
	}

/*     * **********************Getteur Setteur*************************** */

	public function getISSStructure() {
		if (!is_array(self::$_ISSStructure)) {
			$this->init();
		}
		return self::$_ISSStructure;
	}

	public function getISSLocalConfig() {
		if (!is_array(self::$_ISSLocalConfig)) {
			$this->init();
		}
		return self::$_ISSLocalConfig;
	}

}

// Commandes ImperiHome Control
class imperihomeCmd extends cmd {

	public function execute($_options = null) {

	}

	/*     * **********************Getteur Setteur*************************** */
}

?>