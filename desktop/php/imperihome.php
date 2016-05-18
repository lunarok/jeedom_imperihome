<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
?>

<ul class="nav nav-tabs" role="tablist">
	<li role="presentation" class="active"><a href="#configISS" role="tab" data-toggle="tab">{{Configuration ISS}}</a></li>
	<li role="presentation" class="expertModeVisible"><a href="#advancedMode" role="tab" data-toggle="tab">{{Mode avancé}}</a></li>
	<li role="presentation" class="expertModeVisible"><a href="#configTab" role="tab" data-toggle="tab">{{Configuration brute}}</a></li>
</ul>

<div class="tab-content">
	<div role="tabpanel" class="tab-pane active" id="configISS">
		<br/>
		<!--<a class="btn btn-default btn-xs" id="bt_selectAllISS"><i class="fa fa-check-square-o"></i> Sélectionner tout</a>
		<a class="btn btn-default btn-xs" id="bt_unselectAllISS"><i class="fa"></i> Désélectionner tout</a>
		--><a class="btn btn-success pull-right bt_saveISSConfig" id=""><i class="fa fa-floppy-o"></i> Sauvegarder</a><br>
		<br>
		<table class="table table-bordered table-condensed tablesorter" id="cmdList">
			<thead>
				<tr>
					<th>{{Objet}}</th>
					<th>{{Equipement}}</th>
					<th>{{Type}}</th>
					<th>{{Commande}}</th>
					<th>{{Transmettre}}</th>
					<th>{{Type Imperihome}}</th>
				</tr>
			</thead>
			<tbody>
				<?php
$ISSStructure = imperihome::getIssStructure(true);
			
foreach (eqLogic::all() as $eqLogic) {
	if ($eqLogic->getIsEnable() == 0) {
		continue;
	}
	$object = $eqLogic->getObject();
	if (is_object($object) && $object->getIsVisible() == 0) {
		continue;
	}

	$cmds = array();
	foreach ($eqLogic->getCmd() as $cmd) {
		if (method_exists($cmd, 'imperihomeCmd') ) {
			if ( !$cmd->imperihomeCmd()) {
				continue;
			}
		} elseif ($cmd->getType() != 'info') {
			continue;
		}
		array_push($cmds, $cmd);
	}

	$firstLine = true;
	foreach ($cmds as $cmd) {
		if ($firstLine) {
			$firstLine = false;
			
			echo '<tr class="imperihome" data-cmd_id="' . $cmd->getId() . '">';
			echo '<td rowspan="' . count($cmds) . '">';
			
			if (is_object($object)) {
				echo $object->getName();
			} else {
				echo __('Aucun', __FILE__);
			}
			
			echo '</td>';
			echo '<td rowspan="' . count($cmds) . '">';
			echo $eqLogic->getName();
			echo '</td>';
			echo '<td rowspan="' . count($cmds) . '">';
			echo $eqLogic->getEqType_name();
			echo '</td>';
		} else {
			echo '<tr class="tablesorter-childRow imperihome" data-cmd_id="' . $cmd->getId() . '">';
		}



	/*$cmds = $eqLogic->getCmd('info');
	if (count($cmds) == 0) {
		continue;
	}

	$countCmd = 0;
	foreach ($cmds as $cmd) {
		if (method_exists($cmd, 'imperihomeCmd') && !$cmd->imperihomeCmd()) {
			continue;
		}
		$countCmd++;
	}

	$firstLine = true;
	foreach ($cmds as $cmd) {
		if (method_exists($cmd, 'imperihomeCmd') ) {
	        if ( !$cmd->imperihomeCmd()) {
	        	continue;
	        }
	    } elseif ($cmd->getType() != 'info') {
	         continue;
	    }

		if ($firstLine) {
			$firstLine = false;
			echo '<tr class="imperihome" data-cmd_id="' . $cmd->getId() . '">';
			echo '<td rowspan="' . $countCmd . '">';
			if (is_object($object)) {
				echo $object->getName();
			} else {
				echo __('Aucun', __FILE__);
			}
			echo '</td>';
			echo '<td rowspan="' . $countCmd . '">';
			echo $eqLogic->getName();
			echo '</td>';
			echo '<td rowspan="' . $countCmd . '">';
			echo $eqLogic->getEqType_name();
			echo '</td>';
		} else {
			echo '<tr class="tablesorter-childRow imperihome" data-cmd_id="' . $cmd->getId() . '">';
		}

		*/

		echo '<td>';
		echo $cmd->getName();
		echo '</td>';
		echo '<td style="text-align: center;">';
		echo '<input type="checkbox" class="imperihomeAttr" data-size="small" data-l1key="cmd_transmit"/>';
		//echo '<input type="checkbox" class="imperihomeAttr bootstrapSwitch" data-size="small" data-label-text="{{Transmettre}}" data-l1key="cmd_transmit" />';
		echo '</td>';
		echo '<td>';
		echo '<span class="label label-info" style="font-size : 1em;">' . imperihome::convertType($cmd, $ISSStructure) . '</span>';
		echo '<span class="btn btn-warning btn-xs pull-right expertModeVisible bt_createManualConfig" data-id="' . $cmd->getId() . '"><i class="fa fa-wrench"></i></span>';
		echo '</td>';
		echo '</tr>';
	}
}

foreach (scenario::all() as $scenario) {
	$object = $scenario->getObject();
	echo '<tr class="imperihomeScenario" data-scenario_id="' . $scenario->getId() . '">';
	echo '<td>';
	if (is_object($object)) {
		echo $object->getName();
	} else {
		echo __('Aucun', __FILE__);
	}
	echo '</td>';
	echo '<td>';
	echo $scenario->getName();
	echo '</td>';
	echo '<td> {{Scénario}}';
	echo '</td>';
	echo '<td></td>';
	echo '<td>';
	echo '<input type="checkbox" class="imperihomeAttr" data-size="small" data-l1key="scenario_transmit" />';
	//echo '<input type="checkbox" class="imperihomeAttr bootstrapSwitch" data-size="small" data-l1key="scenario_transmit" data-label-text="{{Transmettre}}" />';
	echo '</td>';
	echo '<td>';
	echo ' <span class="label label-info" style="font-size : 1em;">DevScene</span>';
	echo '</td>';
	echo '</tr>';
}
?>

			</tbody>
		</table>
	</div>

	<div role="tabpanel" class="tab-pane" id="advancedMode">
		<br/>
		<a class="btn btn-warning pull-right bt_newAdvancedDevice" id=""><i class="fa fa-plus-circle"></i> Ajouter un équipement</a>
		<br><br>
		<table class="table table-bordered table-condensed tablesorter" id="cmdListAdvanced">
			<thead>
				<tr>
					<th>{{Equipement}}</th>
					<th>{{Type}}</th>
					<th>{{Action}}</th>
				</tr>
			</thead>
			<tbody>
			</tbody>
		</table>
	</div>

	<div role="tabpanel" class="tab-pane" id="configTab">
		<br>
		<b>Attention: cet onglet n'est pas mis à jour aprés avoir sauvegardé une modification. Pour mettre à jour aprés avoir fait une modification dans votre configuration, veuillez rafraichir la page.</b><br>
		<br>
		<div class="form-group">
		    <label class="col-sm-2 control-label">issConfig</label>
		    <div class="col-sm-10" style="overflow: scroll; height: 190px; background-color: #EEEEEE;">
		      		<?php 
						echo imperihome::getIssConfig(false);
		      		?>
		    </div>
		</div>
		<br><br>
		<div class="form-group">
		    <label class="col-sm-2 control-label">issAdvancedConfig</label>
		    <div class="col-sm-10" style="overflow: scroll; height: 190px; background-color: #EEEEEE;">
		      		<?php 
		      			echo imperihome::getIssAdvancedConfig(false);
		      		?>
		    </div>
		</div>
		<br><br>
		<div class="form-group">
		    <label class="col-sm-2 control-label">issTemplate</label>
		    <div class="col-sm-10" style="overflow: scroll; height: 190px; background-color: #EEEEEE;">
		      		<?php 
		      			echo imperihome::getIssTemplate(false);
		      		?>
		    </div>
		</div>
	</div>
</div>

<?php include_file('desktop', 'imperihome', 'js', 'imperihome');?>