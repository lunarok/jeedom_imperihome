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
	require_once dirname(__FILE__) . '/../../ressources/imperihomeInterpreter.class.php';
	include_file('core', 'authentification', 'php');

	if (!isConnect('admin')) {
		throw new Exception('401 Unauthorized');
	}

	if (init('action') == 'saveISSConfig') {
		$cache = new cache();
		$cache->setKey('issConfig');
		$cache->setValue(init('config'));
		$cache->setLifetime(0);
		$cache->save();
		ajax::success();
	}

	if (init('action') == 'loadISSConfig') {
		$cache = cache::byKey('issConfig');
		ajax::success(json_decode($cache->getValue('{}'), true));
	}

/*
$interpreter = new imperihomeInterpreter();

if (init('action') == 'saveLocalConf') {
$conf = init('conf');
log::add('imperihome', 'debug', 'Ajax: saveLocalConf - conf = ' . $conf);

$ISSLocalConfig_file = file_get_contents(dirname(__FILE__)."/../config/ISS-LocalConfig-".config::byKey('LocalConfigIsSet', 'imperihome').".json");
$ISSLocalConfig = json_decode($ISSLocalConfig_file,true);

foreach($conf as $eqId => $eqConf){
if($eqConf['confType'] == 'auto'){
unset($ISSLocalConfig[$eqId]); // suppression de la conf locale si elle existe
}else{
unset($ISSLocalConfig[$eqId]);
$ISSLocalConfig[$eqId] = $eqConf;
}
}

$res = file_put_contents(dirname(__FILE__)."/../config/ISS-LocalConfig-".config::byKey('LocalConfigIsSet', 'imperihome').".json", json_encode($ISSLocalConfig, JSON_FORCE_OBJECT));

if($res){
ajax::success();
}else{
throw new Exception("Impossible d'écrire dans le fichier");
}
}

if (init('action') == 'transmitList') {
$transmitList = json_decode(init('transmitList'),true);
log::add('imperihome', 'debug', 'Ajax: transmitList');

$ISSLocalConfig_file = file_get_contents(dirname(__FILE__)."/../config/ISS-LocalConfig-".config::byKey('LocalConfigIsSet', 'imperihome').".json");
$ISSLocalConfig = json_decode($ISSLocalConfig_file,true);
log::add('imperihome', 'debug', 'TransmitList: Debut Count ISSLocalConfig: ' . count($ISSLocalConfig));

foreach($transmitList as $eq){
log::add('imperihome', 'debug', 'TransmitList: ID: ' . $eq['id'] . ' Transmit: ' . $eq['transmit']);
log::add('imperihome', 'debug', 'TransmitList: Count ISSLocalConfig: ' . count($ISSLocalConfig));

if($eq['transmit'] == '0'){
if(array_key_exists($eq['id'], $ISSLocalConfig)){
log::add('imperihome', 'debug', 'TransmitList: Transmit = 0 & Existe --> Suppression');
unset($ISSLocalConfig[$eq['id']]); // suppression de la conf si elle existe
}else{
log::add('imperihome', 'debug', 'TransmitList: Transmit = 0 mais n existe pas');
}
}else{
if(!array_key_exists($eq['id'], $ISSLocalConfig)){
log::add('imperihome', 'debug', 'TransmitList: Transmit = 1 & n existe pas --> Ajout');
$ISSLocalConfig[$eq['id']] = array('confType' => 'auto');
}else{
log::add('imperihome', 'debug', 'TransmitList: Transmit = 1 mais existe');
}
}
log::add('imperihome', 'debug', 'TransmitList: Count ISSLocalConfig: ' . count($ISSLocalConfig));
}
log::add('imperihome', 'debug', 'TransmitList: Fin: Count ISSLocalConfig: ' . count($ISSLocalConfig));

$res = file_put_contents(dirname(__FILE__)."/../config/ISS-LocalConfig-".config::byKey('LocalConfigIsSet', 'imperihome').".json", json_encode($ISSLocalConfig, JSON_FORCE_OBJECT));

if($res){
ajax::success();
}else{
throw new Exception("Impossible d'écrire dans le fichier");
}
}

if (init('action') == 'ISSStructure') {
log::add('imperihome', 'debug', 'Ajax: ISSStructure');

$ISSStructure = $interpreter->getISSStructure();

if(is_array($ISSStructure)){
ajax::success($ISSStructure);
}else{
throw new Exception("Impossible d'obtenir la structure ISS");
}
}

if (init('action') == 'ISSLocalConfig') {
log::add('imperihome', 'debug', 'Ajax: ISSLocalConfig');

$ISSLocalConfig = $interpreter->getISSLocalConfig();

if(is_array($ISSLocalConfig)){
ajax::success($ISSLocalConfig);
}else{
throw new Exception("Impossible d'obtenir la configuration locale");
}
}

if (init('action') == 'devices') {
log::add('imperihome', 'debug', 'Ajax: devices');

// récupération de la liste des Devices
$devices = $interpreter->devices();

if(is_array($devices)){
ajax::success($devices);
}else{
throw new Exception("Impossible d'obtenir la liste des Devices");
}
}

if (init('action') == 'listDevices') {
log::add('imperihome', 'debug', 'Ajax: listDevices');

// récupération de la liste des Devices
$devices = $interpreter->listDevices();

if(is_array($devices)){
ajax::success($devices);
}else{
throw new Exception("Impossible d'obtenir la liste des Devices");
}
}

if (init('action') == 'getDevice') {
$deviceId = init('deviceId');
$forceType = init('forceType');

log::add('imperihome', 'debug', 'Ajax: getDevice - deviceId: ' . $deviceId);

// récupération de la conf de Devices
$device = $interpreter->getDevice($deviceId, true, $forceType);

if(is_array($device)){
ajax::success($device);
}else{
throw new Exception("Impossible d'obtenir la configuration du device");
}
}

if (init('action') == 'convertType') {
$cmd = init('cmd');

log::add('imperihome', 'debug', 'Ajax: convertType - cmd:' . $cmd['id']);

$res = $interpreter->convertType($cmd);

if(is_string($res)){
ajax::success($res);
}else{
throw new Exception("Impossible d'obtenir le type");
}
}

if (init('action') == 'convertParam') {
$cmd = init('cmd');
$eqLogic = init('eqLogic');
$cmdType = init('cmdType');

log::add('imperihome', 'debug', 'Ajax: convertType - cmd:' . $cmd['id']);

$res = $interpreter->convertParam($eqLogic, $cmd, $cmdType);

if(is_string($res)){
ajax::success($res);
}else{
throw new Exception("Impossible d'obtenir le paramètre");
}
}

if (init('action') == 'convertState') {
$state = init('state');
$type = init('type');

log::add('imperihome', 'debug', 'Ajax: convertState');

$res = $interpreter->convertState($state, $type);

if(is_string($res)){
ajax::success($res);
}else{
throw new Exception("Impossible d'obtenir le paramètre");
}
}

if (init('action') == 'isConfMan') {
$deviceId = init('deviceId');

log::add('imperihome', 'debug', 'Ajax: isConfMan - deviceId: ' . $deviceId);

$res = $interpreter->isConfMan($deviceId);

if(is_bool($res)){
ajax::success($res);
}else{
throw new Exception("Impossible d'obtenir le type de configuration");
}
}*/

	throw new Exception(__('Aucune methode correspondante à : ', __FILE__) . init('action'));

} catch (Exception $e) {
	ajax::error(displayExeption($e), $e->getCode());
}

?>
