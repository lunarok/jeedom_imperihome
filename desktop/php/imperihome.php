<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
$ISSStructure = json_decode(file_get_contents(dirname(__FILE__) . "/../../core/config/ISS-Structure.json"), true);
?>

<a class="btn btn-success pull-right bt_saveISSConfig" id=""><i class="fa fa-floppy-o"></i> Sauvegarder</a><br>
<br>
<table class="table table-bordered table-condensed tablesorter" id="cmdList">
    <thead>
        <tr>
            <th>{{Objet}}</th>
            <th>{{Equipement}}</th>
            <th>{{Type}}</th>
            <th>{{Commande}}</th>
        </tr>
    </thead>
    <tbody>
        <?php
foreach (eqLogic::all() as $eqLogic) {
	if ($eqLogic->getIsEnable() == 0 || $eqLogic->getIsVisible() == 0) {
		continue;
	}
	$object = $eqLogic->getObject();
	if (is_object($object) && $object->getIsVisible() == 0) {
		continue;
	}
	$cmds = $eqLogic->getCmd('info');
	if (count($cmds) == 0) {
		continue;
	}
	echo '<tr>';
	echo '<td>';
	if (is_object($object)) {
		echo $object->getName();
	} else {
		echo __('Aucun', __FILE__);
	}
	echo '</td>';
	echo '<td>';
	echo $eqLogic->getName();
	echo '</td>';
	echo '<td>';
	echo $eqLogic->getEqType_name();
	echo '</td>';

	echo '<td>';
	echo '<table class="table table-bordered table-condensed">';
	echo '<thead>';
	echo '<tr>';
	echo '<td>{{Nom}}</td>';
	echo '<td>{{Transmettre}}</td>';
	echo '<td>{{Type Imperihome}}</td>';
	echo '</tr>';
	echo '</thead>';
	echo '<tbody>';
	foreach ($eqLogic->getCmd('info') as $cmd) {
		echo '<tr class="imperihome" data-cmd_id="' . $cmd->getId() . '">';
		echo '<td>';
		echo $cmd->getName();
		echo '</td>';
		echo '<td>';
		echo '<input type="checkbox" class="imperihomeAttr" data-l1key="cmd_transmit" />';
		echo '</td>';
		echo '<td>';
		echo '<select class="form-control" class="imperihomeAttr" data-l1key="devtype">';
		$devtype = imperihome::convertType($cmd);
		foreach ($ISSStructure as $key => $value) {
			if ($devtype == $key) {
				echo '<option selected>' . $key . '</option>';
			} else {
				echo '<option>' . $key . '</option>';
			}
		}
		echo '<select>';
		echo '</td>';
		echo '</tr>';
	}

	echo '</tbody>';

	echo '</table>';
	echo '</td>';

	echo '</tr>';
}
?>
    </tbody>
</table>


<?php include_file('desktop', 'imperihome', 'js', 'imperihome');?>