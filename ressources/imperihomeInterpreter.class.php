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
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

//********************************************
// Définition de la classe d'interpretation
class imperihomeInterpreter {

	var $ISSLocalConfig;
	var $ISSStructure;
	var $cmds = array();
	var $eqLogics = array();
	var $objects = array();

	// Fonction appelé par l'API avec les arguments
	public function interpret($args) {
		switch ($args[2]) {
			case "objectsfull":
				// Récupération de l'ensemble des données via le cache (si CRON non éxistant, on le créé)
				$cache = cache::byKey('api::object::full');
				$cron = cron::byClassAndFunction('object', 'fullData');
				if (!is_object($cron)) {
					$cron = new cron();
				}
				$cron->setClass('object');
				$cron->setFunction('fullData');
				$cron->setSchedule('* * * * * 2000');
				$cron->setTimeout(10);
				$cron->save();
				if (!$cron->running()) {
					$cron->run(true);
				}
				if ($cache->getValue() != '') {
					$devices = json_decode($cache->getValue(), true);
				}
				print_r($devices);
				break;
			case "devices":
				// Traitement de la demande
				if (!isset($args[3])) {
					// Liste des devices
					$response = $this->devices();
				} elseif ($args[4] == 'action') {
					// Action
					if (isset($args[6])) {
						// si actionParam est présent:
						$response = $this->action($args[3], $args[5], $args[6]);
					} else {
						$response = $this->action($args[3], $args[5]);
					}
				} elseif ($args[5] == 'histo') {
					// Historique
					$response = $this->histo($args[3], $args[4], $args[6], $args[7]);
				} else {
					http_response_code(404);
					$response = array("success" => false, "errormsg" => "Format inconnu");
				}
				break;
			case "rooms":
				$response = $this->rooms();
				break;
			case "system":
				$response = $this->system();
				break;
			default:
				http_response_code(404);
				$response = array("success" => false, "errormsg" => "Format inconnu");
				break;
		}

		return $response;
	}

	public function init() {
		$this->ISSLocalConfig = json_decode(file_get_contents(dirname(__FILE__) . "/../core/config/ISS-LocalConfig-" . config::byKey('LocalConfigIsSet', 'imperihome') . ".json"), true);
		if (!is_array($this->ISSLocalConfig)) {
			$this->ISSLocalConfig = array();
		}
		$this->ISSStructure = json_decode(file_get_contents(dirname(__FILE__) . "/ISS-Structure.json"), true);
		if (!is_array($this->ISSStructure)) {
			$this->ISSStructure = array();
		}
		// Récupération de l'ensemble des données via le cache (si CRON non éxistant, on le créé)
		if (config::byKey('useCache', 'imperihome', 1)) {
			$cache = cache::byKey('api::object::full');
			$cron = cron::byClassAndFunction('object', 'fullData');
			if (!is_object($cron)) {
				$cron = new cron();
			}
			$cron->setClass('object');
			$cron->setFunction('fullData');
			$cron->setSchedule('* * * * * 2000');
			$cron->setTimeout(10);
			$cron->save();
			if (!$cron->running()) {
				$cron->run(true);
			}
			if ($cache->getValue() != '') {
				$devices = json_decode($cache->getValue(), true);
			}
			foreach ($devices as $object) {
				$this->objects[$object['id']] = $object;
				foreach ($object['eqLogics'] as $eqLogic) {
					$this->eqLogics[$eqLogic['id']] = $eqLogic;
					foreach ($eqLogic['cmds'] as $cmd) {
						$this->cmds[$cmd['id']] = $cmd;
					}
				}
			}
		} else {
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

	}

	public function getISSStructure() {
		if (!is_array($this->ISSStructure)) {
			$this->init();
		}
		return $this->ISSStructure;
	}

	public function getISSLocalConfig() {
		if (!is_array($this->ISSLocalConfig)) {
			$this->init();
		}
		return $this->ISSLocalConfig;
	}

	// Retourne la liste des équipements et des scénarios pour Imperihome
	public function devices() {
		if (!is_array($this->ISSStructure)) {
			$this->init();
		}

		// Construction de la réponse:
		$response = array();
		foreach ($this->ISSLocalConfig as $deviceId => $deviceConf) {
			$arraydeviceId = explode("-", $deviceId);
			if (count($arraydeviceId) > 1) {
				$deviceId = $arraydeviceId[0];
				$scenario = scenario::byId($deviceId);
				// Cas du scenario rattaché à aucun objet
				if (is_numeric($scenario->getObject_id())) {
					$room = $scenario->getObject()->getId();
				} else {
					$room = null;
				}
				$response[] = array(
					"id" => $scenario->getId() . '-scn',
					"name" => $scenario->getName(),
					"type" => "DevScene",
					"room" => $room,
					"params" => array(
						array(
							"key" => "LastRun",
							"value" => strtotime($scenario->getLastLaunch()) * 1000,
						),
					),
				);
			} else {
				if (!isset($this->cmds[$deviceId])) {
					continue;
				}
				$cmd = $this->cmds[$deviceId];
				if (!isset($this->eqLogics[$cmd['eqLogic_id']])) {
					continue;
				}
				$eqLogic = $this->eqLogics[$cmd['eqLogic_id']];
				// On détermine le type de la commande connu par imperihome:
				$cmdType = $this->convertType($cmd);
				// On détermine les paramètres de la commande
				$cmdParam = $this->convertParam($cmd, $cmdType);
				// On enregistre la commande en tant que Device à retourner à ImperiHome
				$response[] = array(
					"id" => $cmd['id'],
					"name" => $this->eqLogics[$cmd['eqLogic_id']]['name'] . '-' . $cmd['name'],
					"type" => $cmdType,
					"room" => $this->objects[$eqLogic['object_id']]['id'],
					"params" => $cmdParam,
				);
			}
		}
		$response = array("devices" => $response);
		return $response;
	}

	// Retourne la liste des équipements et des scénarios pour le paramétrage
	public function listDevices() {
		if (!is_array($this->ISSStructure)) {
			$this->init();
		}
		// Construction de la réponse:
		$response = array();
		//On passe chaque objet en revu
		foreach ($this->objects as $object) {
			// On passe chaque équipement en revu
			foreach ($object['eqLogics'] as $eqLogic) {
				$nbrCmdInfo = 0;
				$lastCmd = null;
				// On passe chaque commande en revu
				foreach ($eqLogic['cmds'] as $cmd) {
					$lastCmd = $cmd; // sauvegarde de la dernière commande

					if ($cmd['type'] == 'info') {
						$nbrCmdInfo++;

						$eqL = $this->eqLogics[$cmd['eqLogic_id']];

						// On détermine le type de la commande connu par imperihome:
						$cmdType = $this->convertType($cmd);

						if (is_numeric($eqL['object_id'])) {
							$roomName = $this->objects[$eqL['object_id']]['name'];
						} else {
							$roomName = "Aucun";
						}

						if ($this->isConfMan($cmd['id'])) {
							$confType = "Manuel";
						} else {
							$confType = "Automatique";
						}

						if ($this->isTransmit($cmd['id'])) {
							$isTransmit = "checked";
						} else {
							$isTransmit = "";
						}
						// On enregistre la commande en tant que Device à retourner
						$response[] = array(
							"id" => $cmd['id'],
							"name" => $this->eqLogics[$cmd['eqLogic_id']]['name'] . '-' . $cmd['name'],
							"type" => $cmdType,
							"roomName" => $roomName,
							"confType" => $confType,
							"isTransmit" => $isTransmit,
						);
					}
				}

				if ($nbrCmdInfo == 0) {
					// Si aucune commande info: on créer quand même un équipement sur une commande Action (la dernière passée)
					$cmd = $lastCmd;
					$eqL = $this->eqLogics[$cmd['eqLogic_id']];

					// On détermine le type de la commande connu par imperihome:
					$cmdType = $this->convertType($cmd);

					if (is_numeric($eqL['object_id'])) {
						$roomName = $this->objects[$eqL['object_id']]['name'];
					} else {
						$roomName = "Aucun";
					}
					if ($this->isConfMan($cmd['id'])) {
						$confType = "Manuel";
					} else {
						$confType = "Automatique";
					}

					if ($this->isTransmit($cmd['id'])) {
						$isTransmit = "checked";
					} else {
						$isTransmit = "";
					}
					// On enregistre la commande en tant que Device à retourner
					$response[] = array(
						"id" => $cmd['id'],
						"name" => $this->eqLogics[$cmd['eqLogic_id']]['name'],
						"type" => $cmdType,
						"roomName" => $roomName,
						"confType" => $confType,
						"isTransmit" => $isTransmit,
					);
				}
			}
		}
		// Récupération de l'ensemble des scenarios
		$scenarios = scenario::all();

		foreach ($scenarios as $scenario) {
			if ($scenario->getIsActive()) {
				// Cas du scenario rattaché à aucun objet
				if (is_numeric($scenario->getObject_id())) {
					$roomName = $scenario->getObject()->getName();
				} else {
					$roomName = "Aucun";
				}

				if ($this->isTransmit($scenario->getId() . '-scn')) {
					$isTransmit = "checked";
				} else {
					$isTransmit = "";
				}

				$response[] = array(
					"id" => $scenario->getId() . '-scn',
					"name" => $scenario->getName(),
					"type" => "DevScene",
					"roomName" => $roomName,
					"confType" => "Automatique",
					"isTransmit" => $isTransmit,
				);
			}
		}
		$response = array("devices" => $response);

		return $response;
	}

	// Retourne la liste des pièces (objects jeeDom)
	public function rooms() {
		// Récupération de l'ensemble des objets (équivalent des pièces)
		$rooms = object::all();
		// Construction de la réponse:
		$response = array();
		foreach ($rooms as $room) {
			$response[] = array(
				'id' => $room->getId(),
				'name' => $room->getName(),
			);
		}
		$response = array("rooms" => $response);

		return $response;
	}

	// Retourne les infos système
	public function system() {
		$response = array('id' => config::byKey('api'), 'apiversion' => "1");
		return $response;
	}

	// Execute l'action demandée
	public function action($deviceId, $actionName, $actionParam = null) {
		// Traitement des scénarios
		if ($actionName == "launchScene") {
			$arraydeviceId = explode("-", $deviceId);
			$deviceId = $arraydeviceId[0];
			$scenario = scenario::byId($deviceId);
			if (!is_object($scenario)) {
				http_response_code(404);
				$response = array("success" => false, "errormsg" => "Pas de scénario avec cet ID");
			} else {
				$scenario->launch(false, __('Lancement provoque par Imperihome ', __FILE__));
				$response = array("success" => true, "errormsg" => "");
			}

		} else {
			$cmd = cmd::byId($deviceId);
			if (!is_object($cmd)) {
				http_response_code(404);
				$response = array("success" => false, "errormsg" => "Pas de commande avec cet ID");
			}
			$cmd_return = utils::o2a($cmd);
			$this->cmds[$cmd->getId()] = $cmd_return;
			// Traitement des commandes
			if ($actionName == "setColor") {
				$actionParam = '#' . substr($actionParam, 2, 6);
			}
			$cmdType = $this->convertType($this->cmds[$deviceId]);
			$action = $this->convertAction($this->cmds[$deviceId], $cmdType, $actionName, $actionParam);
			$cmdId = $action['cmdId'];
			$cmdParam = $action['cmdParam'];
			// Finalement, on execute la commande
			if ($cmdId != null) {
				$cmd = cmd::byId($cmdId);
				$cmdSubType = $cmd->getSubType();
				$cmdName = $cmd->getName();
				if ($cmdSubType == 'other') {
					$cmd->execCmd();
					$response = array("success" => true, "errormsg" => "");
				} elseif ($cmdSubType == "slider") {
					$ISSMin = 0;
					$ISSMax = 100;

					$cmdMin = $cmd->getConfiguration('minValue', $ISSMin);
					$cmdMax = $cmd->getConfiguration('maxValue', $ISSMax);

					$newcmdParam = $cmdMin + ($cmdMax - $cmdMin) * ($cmdParam / ($ISSMax - $ISSMin));
					//log::add('imperihome', 'debug', 'Action sur cmdId type Slide: conversion Min: ' . $cmdMin . '; Max: ' . $cmdMax . '; cmdParam: ' . $cmdParam . '; newcmdParam: ' . $newcmdParam . '');

					$cmd->execCmd(array($cmdSubType => $newcmdParam));
					$response = array("success" => true, "errormsg" => "");
				} else {
					$cmd->execCmd(array($cmdSubType => $cmdParam));
					$response = array("success" => true, "errormsg" => "");
				}
			} else {
				http_response_code(404);
				$response = array("success" => false, "errormsg" => "Commande introuvable");
			}

		}
		return $response;
	}

	// Renvoie l'historique demandé
	public function histo($deviceId, $paramKey, $startdate, $enddate) {
		if (!is_array($this->ISSStructure)) {
			$this->init();
		}
		$paramKey = strtolower($paramKey);
		//log::add('imperihome', 'debug', 'Demande d\'historique sur DeviceID: ' . $deviceId . ' pour le paramètre: ' . $paramKey);
		$startdatejeedom = date('Y-m-d H:i:s', ($startdate / 1000));
		$enddatejeedom = date('Y-m-d H:i:s', ($enddate / 1000));
		//log::add('imperihome', 'debug', 'Date de début: ' . $startdatejeedom . '(' . $startdate . ')');
		//log::add('imperihome', 'debug', 'Date de fin: ' . $enddatejeedom . '(' . $enddate . ')');
		$cmdId = null;
		if ($this->isConfMan($deviceId)) {
			//log::add('imperihome', 'debug', 'Type de configuration : Manuel');
			$cmdId = $this->ISSLocalConfig[$deviceId]['params'][$paramKey]['cmdId'];
		} else {
			//log::add('imperihome', 'debug', 'Type de configuration : Auto');
			// Sinon, on regarde si la commande demandée est 'potentialJeeDomState'...
			// on commence par trouver le type de la commande:
			$cmdType = $this->convertType($this->cmds[$deviceId]);
			// on cherche le paramètre du type de la commande
			$param = $this->ISSStructure[$cmdType]['params'][$paramKey];
			//log::add('imperihome', 'debug', 'params: ' . print_r($param, true));
			// on regarde si le paramètre demandé est le potentialJeeDomState
			if (array_key_exists('potentialJeeDomState', $param)) {
				// Si oui, on selectionne celui-ci
				$cmdId = $deviceId;
			}
		}

		//log::add('imperihome', 'debug', 'Historique de: ' . $deviceId . ' -> Numéro de la commande à rechercher:' . $cmdId);

		if ($cmdId != null) {
			// si on a bien trouvé une commande à retourner
			$cmd = cmd::byId($cmdId);
			$histo = array();
			foreach (utils::o2a($cmd->getHistory($startdatejeedom, $enddatejeedom)) as $histoItem) {
				$histo[] = array('value' => floatval($histoItem['value']), 'date' => strtotime($histoItem['datetime']) * 1000);
			}
			$response['values'] = $histo;
			//log::add('imperihome', 'debug', 'Historique de: ' . $deviceId . ' -> Nbr de valeurs:' . count($histo));
		} else {
			http_response_code(404);
			$response = array("success" => false, "errormsg" => "Commande demandée inconnue");
		}

		return $response;
	}

	// Determine le type ImperiHome
	public function convertType($cmd) {
		if (!is_array($this->ISSStructure)) {
			$this->init();
		}
		$response = '';
		// Test si existance d'une configuration manuelle
		if ($this->isConfMan($cmd['id'])) {
			// Récupération de la configuration manuelle
			$response = $this->ISSLocalConfig[$cmd['id']]['type'];
		} else {
			switch ($cmd['eqType']) {
				case "alarm":
					$response = 'DevMotion';
					break;

				case "thermostat":
					$response = 'DevThermostat';
					break;

				case "presence":
					$response = 'DevMultiSwitch';
					break;

				case "camera":
					$response = 'DevCamera';
					break;

				default:
					switch ($cmd['subType']) {
					case 'numeric':
							switch ($cmd['unite']) {
						case '°C':
									$response = 'DevTemperature';
									break;

						case '%':
									if (isset($cmd['template']['dashboard'])) {
										switch ($cmd['template']['dashboard']) {
								case 'door':
								case 'window':
								case 'porte_garage':
												$response = 'DevDoor';
												break;

								case 'store':
												$response = 'DevShutter';
												break;

								case 'light':
												$response = 'DevDimmer';
												break;

								default:
												$response = 'DevDimmer';
												break;
										}
									} else {
										$response = 'DevDimmer';
									}
									break;

						case 'Pa':
									$response = 'DevPressure';
									break;

						case 'km/h':
									$response = 'DevWind';
									break;

						case 'mm/h':
									$response = 'DevRain';
									break;

						case 'mm':
									$response = 'DevRain';
									break;

						case 'Lux':
									$response = 'DevLuminosity';
									break;

						case 'W':
									$response = 'DevElectricity';
									break;

						case 'KwH':
									$response = 'DevElectricity';
									break;

						default:
									if (isset($cmd['eqType'])) {
										switch ($cmd['eqType']) {
								case 'Store':
												$response = 'DevShutter';
												break;
								default:
												$response = 'DevGenericSensor';
												break;
										}
									} else {
										$response = 'DevGenericSensor';
										break;
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
										$response = 'DevDoor';
										break;

							case 'fire':
										$response = 'DevSmoke';
										break;

							case 'presence':
										$response = 'DevMotion';
										break;

							case 'store':
										$response = 'DevShutter';
										break;

							default:
										$response = 'DevSwitch';
										break;
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
										$response = 'DevRGBLight';
										break;
									} else {
										$response = 'DevGenericSensor';
									}
								}
							}
							break;
					}
					break;
			}
			// Détermination d'un type automatique
		}

		return $response;
	}

	// Determine les paramètres Imperihome
	public function convertParam($cmd, $cmdType, $confMode = false) {
		if (!is_array($this->ISSStructure)) {
			$this->init();
		}
		$response = array();
		// Test si existance d'une configuration manuelle
		if ($this->isConfMan($cmd['id'])) {
			// Récupération de la configuration manuelle
			foreach ((array) $this->ISSLocalConfig[$cmd['id']]['params'] as $paramKey => $param) {
				$tempParam = array();
				// récupération du type du paramètre (pour vérifier sa cohérence)
				$type = $this->ISSStructure[$cmdType]['params'][$paramKey]['type'];
				if ($confMode) {
					$tempParam['type'] = $type;
					$tempParam['Description'] = $this->ISSStructure[$cmdType]['params'][$paramKey]['Description'];
				}
				// détermination de la valeur en fonction du type
				if (($param['cmdId'] != '') and (($type == 'infoBinary') or ($type == 'infoNumeric') or ($type == 'infoText') or ($type == 'infoColor'))) {
					if ($this->cmds[$param['cmdId']]['isHistorized'] == '1') {
						$graphable = true;
					} else {
						$graphable = false;
					}
					$unit = $this->cmds[$param['cmdId']]['unite'];
					if (array_key_exists('state', $this->cmds[$param['cmdId']])) {
						$state = $this->getCmdState($cmdType, $paramKey, $this->cmds[$param['cmdId']]);
						$value = $this->convertState($state, $type);
					} else {
						$value = $this->convertState("", $type);
					}
					if ($confMode) {
						$tempParam['cmdId'] = $param['cmdId'];

						$cmdRes = cmd::byId($param['cmdId']);
						if ($cmdRes != null) {
							$cmdHumanName = $cmdRes->getHumanName();
						} else {
							//log::add('imperihome', 'error', 'Device: ' . $cmd['id'] . ' Param: ' . $paramKey . ' -> Commande définie manuellement introuvable!');
							$cmdHumanName = null;
						}
						$tempParam['cmdHumanName'] = $cmdHumanName;
					}
				} else {
					$graphable = false;
					$unit = "";
					$value = $this->convertState($param['value'], $type);
				}
				$tempParam['key'] = $paramKey;
				$tempParam['value'] = $value;
				$tempParam['unit'] = $unit;
				$tempParam['graphable'] = $graphable;

				$response[] = $tempParam;
			}
		} else {
			// Détermination des paramètres automatiques
			switch ($cmd['eqType']) {
				case "alarm":
					$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'armable', null, '1', $confMode);
					$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'ackable', null, '0', $confMode);
					$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'armed', 'Actif', null, $confMode);
					$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'tripped', 'Statut', null, $confMode);
					break;
				case "thermostat":
					$modes = $this->eqLogics[$cmd['eqLogic_id']]['configuration']['existingMode'];
					$modeList = 'Aucun,Off';
					foreach ($modes as $mode) {
						$modeList = $modeList . ',' . $mode['name'];
					}
					$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'availablemodes', null, $modeList, $confMode);
					$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'step', null, '0.5', $confMode);
					$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'curtemp', 'Température', null, $confMode);
					$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'cursetpoint', 'Consigne', null, $confMode);
					$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'curmode', 'Mode', null, $confMode);
					break;
				case "presence":
					$modes = array('1' => 'Présent', '2' => 'Absent', '3' => 'Nuit', '4' => 'Travail', '5' => 'Vacances');
					$modeList = 'Présent,Absent,Nuit,Travail,Vacances';
					$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'choices', null, $modeList, $confMode);
					$mode = $this->searchParamFromCmdName($cmd, $cmdType, 'value', 'Mode', null, $confMode);
					$mode['value'] = $modes[$mode['value']];
					$response[] = $mode;
					break;
				case "camera":
					if (class_exists('camera')) {
						$camera = camera::byId($cmd['eqLogic_id']);
						if (is_object($camera)) {
							$login = $camera->getConfiguration('username');
							$password = $camera->getConfiguration('password');
							$url = $camera->getUrl($camera->getConfiguration('urlStream'), '', 'protocole');
							$urlExt = $camera->getUrl($camera->getConfiguration('urlStream'), '', 'protocoleExt');
							$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'login', null, $login, $confMode);
							$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'password', null, $password, $confMode);
							$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'localjpegurl', null, $url, $confMode);
							$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'remotejpegurl', null, $urlExt, $confMode);
							$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'localmjpegurl', null, $url, $confMode);
							$response[] = $this->searchParamFromCmdName($cmd, $cmdType, 'remotemjpegurl', null, $urlExt, $confMode);
						}
					}
					break;
				default:
					// Récupération liste des paramètres dispos en fonction du type
					if (isset($this->ISSStructure[$cmdType])) {
						$params = $this->ISSStructure[$cmdType]['params'];
					} else {
						$params = array();
					}
					// On passe chaque paramètre en revu
					foreach ($params as $paramKey => $param) {
						if (array_key_exists('potentialJeeDomState', $param)) {
							$tempParam = array();
							// Nom du paramètre
							$tempParam['key'] = $paramKey;
							// récupération du type du paramètre (pour vérifier sa cohérence)
							$type = $this->ISSStructure[$cmdType]['params'][$paramKey]['type'];
							if (array_key_exists('state', $cmd)) {
								$state = $this->getCmdState($cmdType, $paramKey, $cmd);
								$tempParam['value'] = $this->convertState($state, $type);
							} else {
								$tempParam['value'] = $this->convertState("", $type);
							}
							if ($confMode) {
								$tempParam['type'] = $type;
								$tempParam['cmdId'] = $cmd['id'];
								$tempParam['Description'] = $this->ISSStructure[$cmdType]['params'][$paramKey]['Description'];
								$cmdRes = cmd::byId($cmd['id']);
								if ($cmdRes != null) {
									////log::add('imperihome', 'debug', 'Device: ' . $cmd['id'] . ' Param: ' . $paramKey . ' -> Commande définie manuellement trouvée');
									$cmdHumanName = $cmdRes->getHumanName();
								} else {
									$cmdHumanName = null;
									//log::add('imperihome', 'error', 'Device: ' . $cmd['id'] . ' Param: ' . $paramKey . ' -> Commande définie manuellement introuvable!');
								}
								$tempParam['cmdHumanName'] = $cmdHumanName;
							}
							if (array_key_exists('unit', $param)) {
								$tempParam['unit'] = $cmd['unite'];
							}
							if (array_key_exists('graphable', $param)) {
								if ($cmd['isHistorized'] == '1') {
									$graphable = true;
								} else {
									$graphable = false;
								}
								$tempParam['graphable'] = $graphable;
							}
						} else {
							//équivalent recherché
							$eq = explode(";", $this->ISSStructure[$cmdType]['params'][$paramKey]['equivalent']);
							$value = $this->ISSStructure[$cmdType]['params'][$paramKey]['value'];
							$tempParam = array();
							$tempParam = $this->searchParamFromCmdName($cmd, $cmdType, $paramKey, $eq, $value, $confMode);
						}
						// Ajoute se paramètre à la liste
						$response[] = $tempParam;
					}
					break;
			}
		}
		return $response;
	}

	// Convertie la valeur State en fonction du type
	public function convertState($state, $type) {
		$returnValue = "";
		switch ($type) {
			case "infoBinary":
			case "optionBinary":
				if (is_numeric($state)) {
					if (intval($state) == 0) {
						$returnValue = "0";
					} else {
						$returnValue = "1";
					}
				} else {
					$returnValue = "0";
				}

				break;
			case "infoNumeric":
			case "numeric":
				if (is_numeric($state)) {
					$returnValue = $state;
				} else {
					$returnValue = "";
				}
				break;
			case "infoText":
			case "text":
				if (is_numeric($state)) {
					$returnValue = strval($state);
				} elseif (is_string($state)) {
					$returnValue = $state;
				} else {
					$returnValue = "";
				}

				break;
			case "infoColor":
				if (strlen($state) == 7) {
					$returnValue = "FF" . substr($state, 1, 6);
				} else {
					$returnValue = "00000000";
				}
				break;
			default:
				$returnValue = $state;
				break;
		}

		return (string) $returnValue;
	}

	// Recherche la commande associée à l'action demandée
	public function convertAction($cmd, $cmdType, $actionName, $actionParam) {
		if (!is_array($this->ISSStructure)) {
			$this->init();
		}
		// Traitement des commandes
		$cmdId = null;
		$cmdParam = null;
		$cmdHumanName = null;
		// Traitement du cas particulier d'un setLevel d'un devShutter à 0 ou à 100 que l'on convertit en pulseShutter Up ou Down
		if (($cmdType == 'DevShutter') and ($actionName == 'setLevel')) {
			if ($actionParam == '0') {
				return $this->convertAction($cmd, $cmdType, 'pulseShutter', 'down');
			}
			if ($actionParam == '100') {
				return $this->convertAction($cmd, $cmdType, 'pulseShutter', 'up');
			}
		}
		// Test si existance d'une configuration manuelle, ou traitement automatique
		if ($this->isConfMan($cmd['id'])) {
			// Récupération des actions de la configuration manuelle
			$action = $this->ISSLocalConfig[$cmd['id']]['actions'][$actionName];
			// - Cas n°1: on a un type "items" (chaque valeur correspond à une commande différente (ex: switch: On et Off)
			// - Cas n°2: on à un type "direct" (le paramètre est passé directement à la commande (ex: dimmer: %)
			if ($action['type'] == 'item') {
				$actionOption = $action['options'][$actionParam];
				// On a trouvé la commande correpondante à l'action et la valeur demandée
				$cmdId = $actionOption['cmdId'];
				// on récupère le paramètre pré-enregistré (dans le cas où la commande ne serait pas "other"
				$cmdParam = $actionOption['cmdParam'];
			} elseif ($action['type'] == 'direct') {
				// On a trouvé la commande correpondante à l'action et la valeur demandée
				$cmdId = $action['options']['cmdId'];
				// le paramètre est directement passé
				$cmdParam = $actionParam;
			}
			$cmdRes = cmd::byId($cmdId);
			if ($cmdRes != null) {
				$cmdHumanName = $cmdRes->getHumanName();
			} else {
				$cmdId = null;
				$cmdHumanName = null;
			}
			return array('cmdId' => $cmdId, 'cmdParam' => $cmdParam, 'cmdHumanName' => $cmdHumanName);

		} else {
			// Détermination d'un type automatique
			// Traitement du cas particulier d'une setMode commande Thermostat.
			if (($cmd['eqType'] == 'thermostat') and ($actionName == 'setMode')) {
				if ($actionParam == 'Aucun') {
					$cmdId = null;
					$cmdHumanName = '';
					$cmdParam = null;
				} else {
					$cmdRes = cmd::byEqLogicIdCmdName($cmd['eqLogic_id'], $actionParam);
					if ($cmdRes != null) {
						$cmdId = $cmdRes->getId();
						$cmdHumanName = $cmdRes->getHumanName();
						$cmdParam = null;
					} else {
						$cmdId = '';
						$cmdHumanName = 'Selection Mode Thermostat Automatique';
						$cmdParam = null;
					}
				}
				return array('cmdId' => $cmdId, 'cmdParam' => $cmdParam, 'cmdHumanName' => $cmdHumanName);
			}

			// Traitement du cas particulier d'un setChoice commande Présence.
			if (($cmd['eqType'] == 'presence') and ($actionName == 'setChoice')) {
				$cmdRes = cmd::byEqLogicIdCmdName($cmd['eqLogic_id'], $actionParam);
				if ($cmdRes != null) {
					$cmdId = $cmdRes->getId();
					$cmdHumanName = $cmdRes->getHumanName();
					$cmdParam = null;
				} else {
					$cmdId = '';
					$cmdHumanName = 'Selection Mode Présence Automatique';
					$cmdParam = null;
				}
				return array('cmdId' => $cmdId, 'cmdParam' => $cmdParam, 'cmdHumanName' => $cmdHumanName);
			}
			// actions possibles pour ce type
			$actions = $this->ISSStructure[$cmdType]['actions'];
			// action demandée
			$action = $actions[$actionName];
			// On recherche les noms équivalents
			$cmdName = array(); // on peut avoir plusieurs propositions...
			if ($action['type'] == 'direct') {
				$cmdName = explode(";", $action['equivalent']);
				// dans ce cas on passera l'éventuel paramètre envoyé à la commande
				$cmdParam = $actionParam;
			} elseif ($action['type'] == 'item') {
				$cmdName = explode(";", $action['item'][$actionParam]);
			}
			if ($cmdName != null) {
				// on récupère l'ID de l'eqLogic:
				$eqLogicId = $this->cmds[$cmd['id']]['eqLogic_id'];
				foreach ($cmdName as $name) {
					// on cherche la commande et on récupère son ID:
					$cmdRes = cmd::byEqLogicIdCmdName($eqLogicId, $name);
					if ($cmdRes != null) {
						$type = $cmdRes->getType();
						if ($type == 'action') {
							$cmdId = $cmdRes->getId();
							$cmdHumanName = $cmdRes->getHumanName();
							// on s'arrête à la première correspondance (arbitrairement...)
							break;
						}

					}

				}
			}
			return array('cmdId' => $cmdId, 'cmdParam' => $cmdParam, 'cmdRecherche' => $cmdName, 'cmdHumanName' => $cmdHumanName);
		}
	}

	// Test si c'est une configuration manuelle
	public function isConfMan($deviceId) {
		if (!is_array($this->ISSStructure)) {
			$this->init();
		}

		if (array_key_exists($deviceId, $this->ISSLocalConfig)) {
			if ($this->ISSLocalConfig[$deviceId]['confType'] == 'man') {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	// Test si c'est une configuration manuelle
	public function isTransmit($deviceId) {
		if (!is_array($this->ISSStructure)) {
			$this->init();
		}

		return array_key_exists($deviceId, $this->ISSLocalConfig);
	}

	// récupération du state d'une commange avec traitement des min-max:
	public function getCmdState($cmdType, $paramKey, $cmd) {
		if (!is_array($this->ISSStructure)) {
			$this->init();
		}

		if ((array_key_exists('min', $this->ISSStructure[$cmdType]['params'][$paramKey])) and (array_key_exists('minValue', $cmd['configuration']))) {
			// Récupération des min/max de la structure ISS
			$ISSMin = $this->ISSStructure[$cmdType]['params'][$paramKey]['min'];
			$ISSMax = $this->ISSStructure[$cmdType]['params'][$paramKey]['max'];

			if ($cmd['subType'] == 'binary') {
				$state = ($cmd['state'] == 1 ? $ISSMax : $ISSMin);
			} elseif ($cmd['subType'] == 'numeric') {
				// Récupération des min/max de la commande
				$cmdMin = $cmd['configuration']['minValue'];
				$cmdMax = $cmd['configuration']['maxValue'];

				// Test de la valeur des min/max (cas où ce n'est pas configuré)
				$cmdMin = (is_numeric($cmdMin) ? $cmdMin : $ISSMin);
				$cmdMax = (is_numeric($cmdMax) ? $cmdMax : $ISSMax);

				$state = intval($ISSMin + ($ISSMax - $ISSMin) * ($cmd['state'] / ($cmdMax - $cmdMin)));
			} else {
				$state = $cmd['state'];
			}
		} else {
			$state = $cmd['state'];
		}

		return $state;
	}

	public function searchParamFromCmdName($cmd, $cmdType, $paramKey, $cmdName, $forcevalue = null, $confMode = false) {
		////log::add('imperihome', 'debug', 'Recherche: cmdId: ' . $cmd['id'] . ' Param: ' . $paramKey . ' -> Commande "' . $cmdName . '"');

		// récupération du type du paramètre (pour vérifier sa cohérence)
		$type = $this->ISSStructure[$cmdType]['params'][$paramKey]['type'];

		if ($cmdName != null) {
			if (!is_array($cmdName)) {
				$cmdName = array($cmdName);
			}

			foreach ($cmdName as $cmdNameItem) {
				////log::add('imperihome', 'debug', 'Device: ' . $cmd['id'] . ' Param: ' . $paramKey . ' -> Commande Recherchée: "' . $cmdNameItem . '"');

				// On rechecher la commande:
				$cmdRes = cmd::byEqLogicIdCmdName($cmd['eqLogic_id'], $cmdNameItem);

				if ($cmdRes != null) {
					$cmdId = $cmdRes->getId();
					////log::add('imperihome', 'debug', 'Device: ' . $cmd['id'] . ' Param: ' . $paramKey . ' -> Commande "' . $cmdNameItem . '" trouvée, ID: ' . $cmdId);
					$cmdHumanName = $cmdRes->getHumanName();
					break;
				} else {
					$cmdId = null;
					$cmdHumanName = null;
					////log::add('imperihome', 'debug', 'Device: ' . $cmd['id'] . ' Param: ' . $paramKey . ' -> Commande "' . $cmdNameItem . '" introuvable!');
				}
			}

			if ($cmdId != null) {
				if (array_key_exists('state', $this->cmds[$cmdId])) {
					$state = $this->getCmdState($cmdType, $paramKey, $this->cmds[$cmdId]);
					$value = $this->convertState($state, $type);
				} else {
					$value = $this->convertState("", $type);
				}

				if ($this->cmds[$cmdId]['isHistorized'] == '1') {
					$graphable = true;
				} else {
					$graphable = false;
				}
				$unit = $this->cmds[$cmdId]['unite'];
			} else {
				$value = $this->convertState($forcevalue, $type);
				$graphable = false;
				$unit = '';
			}

		} else {
			$value = $this->convertState($forcevalue, $type);
			$graphable = false;
			$unit = '';
			$cmdId = null;
			$cmdHumanName = null;
		}

		$tempParam = array(
			'key' => $paramKey,
			'value' => $value,
		);

		if (array_key_exists('unit', $this->ISSStructure[$cmdType]['params'][$paramKey])) {
			$tempParam['unit'] = $unit;
		}

		if (array_key_exists('graphable', $this->ISSStructure[$cmdType]['params'][$paramKey])) {
			$tempParam['graphable'] = $graphable;
		}

		if ($confMode) {
			$tempParam['type'] = $type;
			$tempParam['Description'] = $this->ISSStructure[$cmdType]['params'][$paramKey]['Description'];
			$tempParam['cmdId'] = $cmdId;
			$tempParam['cmdHumanName'] = $cmdHumanName;
		}
		return $tempParam;
	}

	public function getDevice($deviceId, $confMode = false, $forceType = null) {
		if (!is_array($this->ISSStructure)) {
			$this->init();
		}

		$devStruc = array();

		if (strstr($deviceId, '-scn')) {
			// On est dans le cas d'un scénario
			$arraydeviceId = explode("-", $deviceId);
			$deviceId = $arraydeviceId[0];

			$scenario = scenario::byId($deviceId);

			if (is_numeric($scenario->getObject_id())) {
				$room = $scenario->getObject()->getId();
				$roomName = $scenario->getObject()->getName();
			} else {
				$room = null;
				$roomName = '';
			}

			$devStruc["id"] = $scenario->getId() . '-scn';
			$devStruc["name"] = $scenario->getName();
			$devStruc["type"] = "DevScene";
			$devStruc["room"] = $room;
			$devStruc["params"] = array(
				array(
					"key" => "LastRun",
					"value" => strtotime($scenario->getLastLaunch()) * 1000,
				),
			);

			if ($confMode) {
				$devStruc['actions'] = array();
				$devStruc["roomName"] = $roomName;
			}
		} else {
			$cmd = $this->cmds[$deviceId];

			$devStruc['id'] = $deviceId;

			$eqLogic = $this->eqLogics[$cmd['eqLogic_id']];
			$devStruc['room'] = $eqLogic['object_id'];

			$devStruc['name'] = $this->eqLogics[$cmd['eqLogic_id']]['name'] . '-' . $cmd['name'];

			// On détermine le type de la commande connu par imperihome:
			if ($forceType != null) {
				$cmdType = $forceType;
			} else {
				$cmdType = $this->convertType($cmd);
			}

			$devStruc['type'] = $cmdType;

			// On détermine les paramètres de la commande
			$cmdParam = $this->convertParam($cmd, $cmdType, $confMode);
			$devStruc['params'] = $cmdParam;

			if ($confMode) {
				if ($forceType != null) {
					$devStruc['confMan'] = true;
				} else {
					$devStruc['confMan'] = $this->isConfMan($deviceId);
				}

				$devStruc['roomName'] = $this->objects[$eqLogic['object_id']]['name'];
				$devStruc['typeDesc'] = $this->ISSStructure[$cmdType]['Description'];

				$devStruc['actions'] = array();
				// On détermine les actions de la commande
				foreach ($this->ISSStructure[$cmdType]['actions'] as $actionName => $actionConf) {
					$devStruc['actions'][$actionName] = array();

					if ($actionConf['type'] == 'item') {
						$devStruc['actions'][$actionName]['type'] = 'item';
						$devStruc['actions'][$actionName]['options'] = array();

						foreach ($actionConf['item'] as $value => $correspondances) {
							$devStruc['actions'][$actionName]['options'][$value] = $this->convertAction($cmd, $cmdType, $actionName, $value);
						}
					}

					if ($actionConf['type'] == 'direct') {
						$devStruc['actions'][$actionName]['type'] = 'direct';

						$devStruc['actions'][$actionName]['options'] = $this->convertAction($cmd, $cmdType, $actionName, "");
					}

				}

			}
		}

		return $devStruc;
	}

}
//********************************************

?>