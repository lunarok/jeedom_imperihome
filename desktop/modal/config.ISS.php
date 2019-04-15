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

if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

?>
<div id="div_ISSAlert"></div>
<legend>Equipements</legend>
<form class="form-horizontal">
<fieldset>
    <div class="form-group">
        <label class="col-sm-3 control-label">Selectionnez un équipement:</label>
        <div class="col-sm-4">
            <select class="form-control" id="eqIdISS">
                <option value="">Aucun</option>
            </select>
        </div>
        <div class="col-sm-1">
            <a id="eqUpdateISS" class="btn btn-primary btn"><i class="fas fa-forward"></i>Actualiser</a>
        </div>
        <div class="col-sm-1">
            <a id="eqSave" class="btn btn-success btn"><i class="fas fa-check-circle"></i>Sauvegarder</a>
        </div>
    </div>
</fieldset>
</form>

<legend>Infos générales</legend>
<table class="table table-bordered table-condensed">
    <thead>
        <tr>
            <th class="col-sm-2">Infos</th>
            <th class="col-sm-8"></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>ID</td>
            <td id="deviceId"></td>
        </tr>
        <tr>
            <td>Pièce</td>
            <td id="deviceRoom"></td>
        </tr>
        <tr>
            <td>Type de configuration</td>
            <td id="deviceConfigType"></td>
        </tr>
        <tr>
            <td>Correspondance Imperihome</td>
            <td id="deviceType"></td>
        </tr>
    </tbody>
</table>

<legend>Paramètres</legend>
<table id="table_params" class="table table-bordered table-condensed">
    <thead>
        <tr>
            <th class="col-sm-1">Paramètre</th>
            <th class="col-sm-1">Type</th>
            <th class="col-sm-2">Valeur actuelle</th>
            <th class="col-sm-5">Configuration</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
</table>

<legend>Action</legend>
<table id="table_actions" class="table table-bordered table-condensed">
    <thead>
        <tr>
            <th class="col-sm-1">Action</th>
            <th class="col-sm-1">Type</th>
            <th class="col-sm-2">Correspondance actuelle</th>
            <th class="col-sm-5">Configuration</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
</table>

<script>
    $(function() {

        loadISSDevices();

        $('#eqIdISS').on('change', function() {
            loadDevice($(this).val());
        });

        $('#eqUpdateISS').on('click', function() {
            loadDevice($('#eqIdISS').val());
        });
    });

    function loadISSDevices(forceId){
        $('#eqIdISS')
            .find('optgroup')
            .remove()
        ;
        $('#eqIdISS')
            .find('option')
            .remove()
        ;

        jeedom.object.all({
            success : function(data){
                $('#eqIdISS').append( '<optgroup label="Aucun" id="null"></optgroup>');
                $.each(data, function( key, val ) {
                    $('#eqIdISS').append( '<optgroup label="' + val.name + '" id="' + val.id + '"></optgroup>');
                });

                $.ajax({
                    type: 'POST',
                    url: 'plugins/imperihome/core/ajax/imperihome.ajax.php',
                    data: {
                        action: 'devices'
                    },

                    dataType: 'json',
                    error: function (request, status, error) {
                        console.log("Erreur lors de la demande de la liste des Devices");
                    },

                    success: function( data ) {
                        $.each( data.result.devices, function( key, val ) {
                            $('#eqIdISS optgroup[id=' + val.room + ']').append( '<option value="' + val.id + '">' + val.name + '</option>');
                            console.log(val.room + ' - ' + val.name);
                        });

                        if(forceId != null){
                            $('#eqIdISS option[value="' + forceId + '"]').prop('selected', true);
                        }
                    }
                });
            }
        });
    }

    function loadDevice(id, forceType){
        if(typeof(forceType) == 'undefined' ){
            forceType = null;
        }

        $.ajax({
            type: 'POST',
            url: 'plugins/imperihome/core/ajax/imperihome.ajax.php',
            data: {
                action: 'getDevice',
                deviceId : id,
                forceType : forceType
            },

            dataType: 'json',
            error: function (request, status, error) {
                console.log("Erreur lors de la demande du type de configuration");
            },

            success: function( data ) {
                if(data.state == "ok"){
                    fillDevice(data.result);
                }else{
                    console.log(data.result);
                }
            }
        });
    }

    function saveDevice(device){
        confToSave = {};

        if(device.confMan){
            confToSave[device.id] = {};
            confToSave[device.id].type = device.type;

            confToSave[device.id].params = {};
            $.each(device.params, function( key, param ) {
                confToSave[device.id].params[param.key] = {};
                if((param.type == 'infoBinary') || (param.type == 'infoNumeric') || (param.type == 'infoText')){
                    confToSave[device.id].params[param.key].cmdId = $('#cmd' + param.key + "Id").val();
                }else{
                    if(param.type == 'optionBinary'){
                        confToSave[device.id].params[param.key].value = $("input[name='" + param.key + "']:checked" ).val();
                    }else{
                        confToSave[device.id].params[param.key].value = $('#' + param.key).val();
                    }
                }
            });

            confToSave[device.id].actions = {};
            $.each(device.actions, function( actionName, actionParam ) {
                confToSave[device.id].actions[actionName] = {};
                confToSave[device.id].actions[actionName].type = actionParam.type;
                confToSave[device.id].actions[actionName].options = {};

                if(actionParam.type == 'item'){
                    $.each(device.actions[actionName].options, function(itemValue, itemParam){
                        confToSave[device.id].actions[actionName].options[itemValue] = {};

                        confToSave[device.id].actions[actionName].options[itemValue].cmdId = $('#cmd' + actionName + itemValue + "Id").val();
                        confToSave[device.id].actions[actionName].options[itemValue].cmdParam = $('#cmdParam' + actionName + itemValue).val();
                    });
                }else{
                    confToSave[device.id].actions[actionName].options.cmdId = $('#cmd' + actionName + "Id").val();
                    confToSave[device.id].actions[actionName].options.cmdParam = $('#cmdParam' + actionName).val();
                }
            });
        }else{
            confToSave[device.id] = {};
            confToSave[device.id].type = 'auto';
        }

        $.ajax({
            type: 'POST',
            url: 'plugins/imperihome/core/ajax/imperihome.ajax.php',
            data: {
                action: 'saveLocalConf',
                conf : confToSave
            },

            dataType: 'json',
            error: function (request, status, error) {
                $('#div_ISSAlert').showAlert({message: 'Erreur lors de la sauvegarde...', level: 'danger'});
            },

            success: function(data, textStatus, jqXHR) {
                $('#div_ISSAlert').showAlert({message: 'La configuration de l\'équipement a bien été sauvegardée!', level: 'success'});
            }
        });
    }

    function fillDevice(device){
        $('#div_ISSAlert').hide();

        $('#eqSave').unbind('click');
        $('#eqSave').on('click', function(device) {
            return function() {
                bootbox.confirm('<b>Etes-vous sûr de vouloir modifier la configuration de cet équipement?</b><br><br>Il est fortement recommandé de <b>supprimer l\'équipement de l\'interface Imperihome</b> avant de modifier la configuration de celui-ci, notamment en Dashboard. Sans cela, un plantage d\'Imperihome est probable...', function(result) {
                    if (result) {
                        saveDevice(device);
                    }
                });
            };
        }(device));

        // INFO GENERALES //////////////////
        $("#deviceId").html('<span class="label label-primary">' + device.id + '</span>');
        $("#deviceRoom").html('<span class="label label-primary">' + device.roomName + '</span>');

        if(device.confMan){
            $("#deviceConfigType").html('<span class="label label-primary" id="eqMode">Manuelle</span> <a class="btn btn-xs btn-warning bt_showInterview" id="eqModeSwitch">Passer en Automatique</a>');
            loadDeviceTypeList(device);
        }else{
            if(device.type == 'DevScene'){
                $("#deviceConfigType").html('<span class="label label-primary" id="eqMode">Automatique</span>');
            }else{
                $("#deviceConfigType").html('<span class="label label-primary" id="eqMode">Automatique</span> <a class="btn btn-xs btn-warning bt_showInterview" id="eqModeSwitch">Passer en Manuelle</a>');
            }
            $("#deviceType").html('<span class="label label-primary">' + device.type + ' (' + device.typeDesc + ')</span>');
        }

        $('#eqModeSwitch').on('click', function(device) {
            return function() {
                bootbox.confirm('<b>Etes-vous sûr de vouloir modifier la configuration de cet équipement?</b><br><br>Il est fortement recommandé de <b>supprimer l\'équipement de l\'interface Imperihome</b> avant de modifier la configuration de celui-ci, notamment en Dashboard. Sans cela, un plantage d\'Imperihome est probable...', function(result) {
                    if (result) {
                        device.confMan = !device.confMan;
                        switchMode(device);
                    }
                });
            };
        }(device));

        // PARAMETRES ///////////////////////
        $('#table_params tbody')
            .find('tr')
            .remove()
        ;

        $.each(device.params, function(key, param){
            tr = '<tr>';
            tr += '    <td><b>' + param.key + '</b><br>' + param.Description + '</td>';
            tr += '    <td><span class="label label-primary">' + param.type + '</span></td>';

            switch(param.type){
                case "infoNumeric":
                case "infoBinary":
                case "infoText":
                    tr += '    <td><span class="label label-primary">' + param.value + '</span><br>';
                    if(param.cmdId != null){
                        tr += '    Commande correspondante: <span class="label label-primary">' + param.cmdHumanName + ' (' + param.cmdId + ')</span>';
                    }else{
                        tr += '    <span class="label label-danger">Aucune correspondance trouvée!</span>';
                    }
                    tr += '    </td>';
                break;

                case "optionBinary":
                case "text":
                case "numeric":
                    tr += '    <td><span class="label label-primary">' + param.value + '</span></td>';
                break;

                default:
                    tr += '    <td><span class="label label-primary">' + param.value + '</span></td>';
                break;
            }

            tr += '    <td id="devParamConf' + param.key + '"></td>';
            tr += '</tr>';

            $('#table_params tbody').append(tr);

            if(device.confMan){
                fillParamConf(param);
            }
        });

        $("#table_params").delegate(".listEquipementInfo", 'click', function() {
            var el = $(this);
            jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function(result) {
                $('#' + el.data('input') + "Id").val(result.cmd.id);
                $('#' + el.data('input')).val(result.human);
            });
        });


        // ACTIONS ///////////////////////

        $('#table_actions tbody')
            .find('tr')
            .remove()
        ;

        $.each(device.actions, function(key, action){

            if(action.type == 'item'){
                firstLine = true;

                for(optkey in action.options){
                    tr = '<tr>';
                    option = action.options[optkey];

                    if(firstLine){
                        nbrOptions = Object.keys(action.options).length;
                        tr += '    <td rowspan="' + nbrOptions + '"><b>' + key + '</b></td>';
                        tr += '    <td rowspan="' + nbrOptions + '"><span class="label label-primary">' + action.type + '</span></td>';
                        firstLine = false;
                    }

                    tr += '    <td>';
                    tr += '         <b>Pour la valeur</b> <span class="label label-primary">' + optkey + '</span><br>' ;
                    if(option.hasOwnProperty('cmdRecherche')){
                        tr += '         Commande recherchée: <span class="label label-warning">' + option.cmdRecherche + '</span><br>' ;
                    }
                    if(option.cmdId != null){
                        if(option.cmdParam == null){
                            option.cmdParam = '';
                        }

                        tr += '         Commande retenue: <span class="label label-primary">' + option.cmdHumanName + ' (' + option.cmdId + ')</span> <span class="label label-warning">(Param = "' + option.cmdParam + '")</span>';
                    }else{
                        tr += '         <span class="label label-danger">Aucune correspondance trouvée!</span>';
                    }

                    tr += '    </td>';
                    tr += '    <td id="devActionConf' + key + optkey +  '"></td>';
                    tr += '</tr>';

                    $('#table_actions tbody').append(tr);

                    if(device.confMan){
                        action.key = key;
                        fillActionConf(action, optkey);
                    }
                }

            }else{
                tr = '<tr>';
                tr += '    <td><b>' + key + '</b></td>';
                tr += '    <td><span class="label label-primary">' + action.type + '</span></td>';
                tr += '    <td>';
                if(action.options.hasOwnProperty('cmdRecherche')){
                    tr += '         Commande recherchée: <span class="label label-warning">' + action.options.cmdRecherche + '</span><br>' ;
                }
                if(action.options.cmdId != null){
                    if(action.options.cmdParam == null){
                        action.options.cmdParam = '';
                    }
                    tr += '         Commande retenue: <span class="label label-primary">' + action.options.cmdHumanName + ' (' + action.options.cmdId + ')</span>';
                }else{
                    tr += '         <span class="label label-danger">Aucune correspondance trouvée!</span>';
                }
                tr += '    </td>';
                tr += '    <td id="devActionConf' + key + '"></td>';
                tr += '</tr>';

                $('#table_actions tbody').append(tr);

                if(device.confMan){
                    action.key = key;
                    fillActionConf(action);
                }
            }
        });

        $("#table_actions").delegate(".listEquipementAction", 'click', function() {
            var el = $(this);
            jeedom.cmd.getSelectModal({cmd: {type: 'action'}}, function(result) {
                $('#' + el.data('input') + "Id").val(result.cmd.id);
                $('#' + el.data('input')).val(result.human);
            });
        });
    }

    function switchMode(device){
        fillDevice(device);
    }

    function fillParamConf(param){
        switch(param.type){
            case "infoNumeric":
            case "infoBinary":
            case "infoText":
                if(param.cmdId == null){
                    param.cmdId = '';
                }
                if(param.cmdHumanName == null){
                    param.cmdHumanName = '';
                }else{
                    param.cmdHumanName = '#' + param.cmdHumanName + '#';
                }

                td = '<input class="form-control" style="width: 10%; display : inline-block;" id="cmd' + param.key + 'Id" value="' + param.cmdId + '" disabled>';
                td += '<input class="form-control" id="cmd' + param.key + '" placeholder="Commande Info associée" style="width: 80%; display : inline-block;" value="' + param.cmdHumanName + '" disabled>';
                td += '<a class="btn btn-default cursor listEquipementInfo" data-input="cmd' + param.key + '"><i class="fas fa-list-alt "></i></a>';

                $('#devParamConf' + param.key).html(td);
            break;

            case "optionBinary":
                td = '<label><input type="radio" name="' + param.key + '" id="' + param.key + '0" value="0"> {{Non}}</label><br>';
                td += '<label><input type="radio" name="' + param.key + '" id="' + param.key + '1" value="1"> {{Oui}}</label>';
                $('#devParamConf' + param.key).html(td);
                $('#' + param.key + param.value).prop('checked', true);
            break;

            case "text":
                td = '<input type="text" class="form-control" id="' + param.key + '" placeholder="Valeur" style="width: 80%; display : inline-block;" value="' + param.value + '">';
                $('#devParamConf' + param.key).html(td);
            break;

            case "numeric":
                td = '<input type="number" class="form-control" id="' + param.key + '" placeholder="Valeur" style="width: 80%; display : inline-block;" value="' + param.value + '">';
                $('#devParamConf' + param.key).html(td);
            break;
        }
    }

    function fillActionConf(action, value){
        if( typeof(value) == 'undefined' ){
            value = '';
        }

        if(action.type == 'item'){
            if(action.options[value].cmdId == null){
                action.options[value].cmdId = '';
            }
            if(action.options[value].cmdParam == null){
                action.options[value].cmdParam = '';
            }
            if(action.options[value].cmdHumanName == null){
                action.options[value].cmdHumanName = '';
            }else{
                action.options[value].cmdHumanName = '#' + action.options[value].cmdHumanName + '#';
            }
            td  = '            Commande à appeler: <input class="form-control" style="width: 10%; display : inline-block;" id="cmd' + action.key + value + 'Id" value="' + action.options[value].cmdId + '" disabled>';
            td += '            <input class="form-control" id="cmd' + action.key + value + '" placeholder="Commande Action associée" style="width: 50%; display : inline-block;" value="' + action.options[value].cmdHumanName + '" disabled>';
            td += '            <a class="btn btn-default cursor listEquipementAction" data-input="cmd' + action.key + value + '"><i class="fas fa-list-alt "></i></a><br>';
            td += '            Paramètre optionnel: <input class="form-control" id="cmdParam' + action.key + value + '" placeholder="Paramètre optionnel" style="width: 60%; display : inline-block;" value="' + action.options[value].cmdParam + '">';
        }else{
            if(action.options.cmdId == null){
                action.options.cmdId = '';
            }
            if(action.options.cmdHumanName == null){
                action.options.cmdHumanName = '';
            }else{
                action.options.cmdHumanName = '#' + action.options.cmdHumanName + '#';
            }
            td  = '            Commande à appeler: <input class="form-control" style="width: 10%; display : inline-block;" id="cmd' + action.key + 'Id" value="' + action.options.cmdId + '" disabled>';
            td += '            <input class="form-control" id="cmd' + action.key + '" placeholder="Commande Action associée" style="width: 50%; display : inline-block;" value="' + action.options.cmdHumanName + '" disabled>';
            td += '            <a class="btn btn-default cursor listEquipementAction" data-input="cmd' + action.key + '"><i class="fas fa-list-alt "></i></a>';
        }

        $('#devActionConf' + action.key + value).html(td)
    }

    function loadDeviceTypeList(device){
        $.ajax({
            type: 'POST',
            url: 'plugins/imperihome/core/ajax/imperihome.ajax.php',
            data: {
                action: 'ISSStructure'
            },

            dataType: 'json',
            error: function (request, status, error) {
                console.log("Erreur lors de la demande de la structure ISS");
            },

            success: function(device) {
                return function( data ) {
                    if(data.state == "ok"){
                        var deviceList = '<select id="eqType" class="form-control">';
                        $.each(data.result, function(type, typeDesc){
                            if(type != "DevScene"){
                                deviceList += '<option value="' + type + '">' + typeDesc.Description + ' (' + type + ')</option>';
                            }
                        });
                        deviceList += '</select>';

                        $("#deviceType").html(deviceList);

                        // rangement dans l'ordre
                        var options = $('#eqType option');
                        var arr = options.map(function(_, o) {
                            return {
                                t: $(o).text(),
                                v: o.value
                            };
                        }).get();
                        arr.sort(function(o1, o2) {
                            return o1.t > o2.t ? 1 : o1.t < o2.t ? -1 : 0;
                        });
                        options.each(function(i, o) {
                            o.value = arr[i].v;
                            $(o).text(arr[i].t);
                        });

                        // selection du type en cours
                        $('#eqType option[value="' + device.type + '"]').prop('selected', true);

                        $('#eqType').on('change', function(device) {
                            return function() {
                                loadDevice(device.id, $(this).val());
                            };
                        }(device));

                    }else{
                        console.log(data.result);
                    }
                };
            }(device)

        });

    }

</script>
