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

	if (init('action') == 'saveISSConfig') {
		$cache = new cache();
		$cache->setKey('issConfig');
		$cache->setValue(init('config'));
		$cache->setLifetime(0);
		$cache->save();
		imperihome::generateISSTemplate();
		ajax::success();
	}

	if (init('action') == 'loadISSConfig') {
		$cache = cache::byKey('issConfig');
		ajax::success(json_decode($cache->getValue('{}'), true));
	}

	if (init('action') == 'getISSStructure') {
		ajax::success(json_decode(file_get_contents(dirname(__FILE__) . "/../config/ISS-Structure.json"), true));
	}

	if (init('action') == 'loadAdvancedDeviceISSConfig') {
		$deviceId = init('deviceId');
		$cache = cache::byKey('issAdvancedConfig');
		if(!is_object($cache)){
			$cache = new cache();
			$cache->setKey('issAdvancedConfig');
		}

		$issAdvancedConfig = json_decode($cache->getValue('{}'), true);

		if(array_key_exists($deviceId, $issAdvancedConfig)){ 
			ajax::success($issAdvancedConfig[$deviceId]);
		}else{
			$device = array();
			$device['id'] = $deviceId;
			$device['type'] = 'noDevice';

			ajax::success($device);
		}
	}

	if (init('action') == 'loadAdvancedISSConfig') {
		$cache = cache::byKey('issAdvancedConfig');
		ajax::success(json_decode($cache->getValue('{}'), true));
	}

	if (init('action') == 'saveAdvancedDevice') {
		$device = json_decode(init('config'), true);
		$cache = cache::byKey('issAdvancedConfig');
		if(!is_object($cache)){
			$cache = new cache();
			$cache->setKey('issAdvancedConfig');
		}

		$issAdvancedConfig = json_decode($cache->getValue('{}'), true);
		
		$issAdvancedConfig[$device['id']] = $device;

		if(array_key_exists($device['id'], $issAdvancedConfig)){ 
			$cache->setValue(json_encode($issAdvancedConfig));
			$cache->setLifetime(0);
			$cache->save();
			imperihome::generateISSTemplate();
			ajax::success();
		}
	}

	if (init('action') == 'deleteAdvancedDevice') { 
		$deviceId = init('deviceId');
		$cache = cache::byKey('issAdvancedConfig');
		if(!is_object($cache)){
			$cache = new cache();
			$cache->setKey('issAdvancedConfig');
		}

		$issAdvancedConfig = json_decode($cache->getValue('{}'), true);

		if(array_key_exists($deviceId, $issAdvancedConfig)){
			unset($issAdvancedConfig[$deviceId]);
			$cache->setValue(json_encode($issAdvancedConfig));
			$cache->setLifetime(0);
			$cache->save();
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
