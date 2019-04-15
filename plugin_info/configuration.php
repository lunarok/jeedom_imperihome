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
include_file('core', 'authentification', 'php');
if (!isConnect()) {
	include_file('desktop', '404', 'php');
	die();
}
?>
<form class="form-horizontal">
    <fieldset>
    	<legend>{{Lien de l'API ISS à indiquer dans ImperiHome}}</legend>
    	<div class="form-group">
            <label class="col-lg-4 control-label">{{Interne}} : </label>
            <div class="col-lg-8">
            <?php
				echo network::getNetworkAccess('internal') . "/plugins/imperihome/core/php/imperihome.php?_url=/" . jeedom::getApiKey('imperihome') . "/";
			?></div>
        </div>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Externe}} : </label>
            <div class="col-lg-8">
            <?php
				echo network::getNetworkAccess('external') . "/plugins/imperihome/core/php/imperihome.php?_url=/" . jeedom::getApiKey('imperihome') . "/";
			?></div>
        </div>
				</fieldset>
				<fieldset>
		    	<legend>{{Configuration pour ImperiHome}}</legend>
				<div class="form-group">
	            <label class="col-lg-4 control-label">{{Contenu ISS}} : </label>
	            <div class="col-lg-8">
								<a class="btn btn-primary" onclick="loadModal()"><i class="fas fa-spinner"></i> {{Configuration}}</a>
							</div>
	        </div>
	    </fieldset>
</form>

<script>
function loadModal() {
  var nodeId = $('#mac').text();
  $('#md_modal2').dialog({
    title: "Configuration ISS"
  });
  $('#md_modal2').load('index.php?v=d&plugin=imperihome&modal=imperihome').dialog('open');
}
</script>
