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

sendVarToJS('ISSeqId', init('ISSeqId'));
?>
<div id="div_ISSAlert"></div>

<legend>Commande support <a id="eqSave" class="btn btn-success btn-xs pull-right "><i class="fa fa-check-circle"> </i> Sauvegarder</a></legend>
<div>
    <input class="form-control" style="width: 10%; display : inline-block;" id="cmdSupportId" disabled>
    <input class="form-control" id="cmdSupportHumanName" placeholder="Commande Info associée" style="width: 80%; display : inline-block;" disabled>
    <a class="btn btn-default cursor listEquipement" data-input="cmdSupport"><i class="fa fa-list-alt "></i></a>
</div>
<br>
<legend>Type ISS</legend>
<div id="deviceType"></div>
<br>
<legend>Paramètres</legend>
<table id="table_params" class="table table-bordered table-condensed">
    <thead>
        <tr>
            <th class="col-sm-1">Paramètre</th>
            <th class="col-sm-5">Configuration</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
</table>

<legend>Actions</legend>
<table id="table_actions" class="table table-bordered table-condensed">
    <thead>
        <tr>
            <th class="col-sm-1">Action</th>
            <th class="col-sm-5">Configuration</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
</table>

<script>
var ISSStructure;

$('#eqSave').on('click', function () {
    var device = {};
    device.id = 'manual' + $("#cmdSupportId").val();
    device.type = $("#eqType").val();

    device.params = [];
    $('tr.imperihomeAdvancedDeviceParameter').each(function(){
        var param = {};
        param.key = $(this).attr('data-paramKey');
        param.type = $(this).attr('data-paramType');

        if((param.type == 'infoBinary') || (param.type == 'infoNumeric') || (param.type == 'infoText') || (param.type == 'infoColor')){
            param.value = $(this).find('#cmd' + param.key + "Id").val();
        }else{
            if(param.type == 'optionBinary'){
                param.value = $(this).find("input[name='" + param.key + "']:checked" ).val();
            }else{
                param.value = $(this).find('#' + param.key).val();
            }
        }

        device.params.push(param);
    });

    device.actions = {};
    $('tr.imperihomeAdvancedDeviceAction').each(function(){
        var action = {};
        actionName = $(this).attr('data-actionName');
        action.type = $(this).attr('data-actionType');

        if(action.type == 'item'){
            actionKey = $(this).attr('data-actionKey');

            if(actionName in device.actions){
                device.actions[actionName]['item'][actionKey] = $('#cmd' + actionName + actionKey + "Id").val();
            }else{
                action.item = [];
                action.item[actionKey] = $('#cmd' + actionName + actionKey + "Id").val();
                device.actions[actionName] = action;
            }
        }else{
            action.cmdId = $('#cmd' + actionName + "Id").val();
            device.actions[actionName] = action;
        }        
    });

    $.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // méthode de transmission des données au fichier php
            url: "plugins/imperihome/core/ajax/imperihome.ajax.php", // url du fichier php
            data: {
                action: "saveAdvancedDevice",
                config: json_encode(device),
            },
            dataType: 'json',
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function (data) { // si l'appel a bien fonctionné
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            $('#div_alert').showAlert({message: '{{Sauvegarde réalisée avec succès}}', level: 'success'});
        }
    });
});

$('.listEquipement').on('click', function () {
    var el = $(this);
    jeedom.cmd.getSelectModal({}, function(result) {
        $('#' + el.data('input') + "Id").val(result.cmd.id);
        $('#' + el.data('input') + "HumanName").val(result.human);
    });
});

$.ajax({
    type: 'POST',
    url: 'plugins/imperihome/core/ajax/imperihome.ajax.php',
    data: {
        action: 'getISSStructure'
    },

    dataType: 'json',
    error: function (request, status, error) {
        console.log("Erreur lors de la demande de la structure ISS");
    },

    success: function(ISSeqId) {
        return function( data ) {
            if(data.state == "ok"){
                ISSStructure = data.result;

                var deviceList = '<select id="eqType" class="form-control">';
                deviceList += '<option value="noType"> Selectionner un type ISS</option>';
                $.each(ISSStructure, function(type, typeDesc){
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

                $('#eqType').on('change', function(ISSeqId) {
                    return function() {
                        //loadDevice(device.id, $(this).val());
                        console.log("Changement de type: " + $(this).val());
                        loadParameters($(this).val());
                        loadActions($(this).val());
                    };
                }(ISSeqId));
                
            }else{
                console.log(data.result);
            }
        };
    }(ISSeqId)
});

function loadParameters(eqType){
    $('#table_params tbody')
        .find('tr')
        .remove()
    ;

    params = ISSStructure[eqType]['params'];

    $.each(params, function(key, param){
        tr = '<tr class="imperihomeAdvancedDeviceParameter" data-paramKey="' + param.key + '" data-paramType="' + param.type + '">';
        tr += '    <td><b>' + param.key + '</b><br>' + param.Description + '</td>';        
        tr += '    <td>';
        
        switch(param.type){
            case "infoNumeric":
            case "infoBinary":
            case "infoText":
                td = '<input class="form-control" style="width: 10%; display : inline-block;" id="cmd' + param.key + 'Id" disabled> ' ;
                td += '<input class="form-control" id="cmd' + param.key + '" placeholder="Commande Info associée" style="width: 80%; display : inline-block;" disabled> ';
                td += '<a class="btn btn-default cursor listEquipementInfo" data-input="cmd' + param.key + '"><i class="fa fa-list-alt "></i></a> ';
            break;

            case "optionBinary":
                td = '<label><input class="form-control" type="radio" name="' + param.key + '" id="' + param.key + '0" value="0" checked> {{Non}}</label> ';
                td += '<label><input class="form-control" type="radio" name="' + param.key + '" id="' + param.key + '1" value="1"> {{Oui}}</label>';
            break;

            case "text":
                td = '<input type="text" class="form-control" id="' + param.key + '" placeholder="Valeur" style="width: 80%; display : inline-block;" value="' + param.value + '">';
            break;

            case "numeric":
                td = '<input type="number" class="form-control" id="' + param.key + '" placeholder="Valeur" style="width: 80%; display : inline-block;" value="' + param.value + '">';
            break;
        }
        tr += td;

        tr += '     </td>';
        tr += '</tr>';

        $('#table_params tbody').append(tr);
    });
    
    $("#table_params").delegate(".listEquipementInfo", 'click', function() {
        var el = $(this);
        jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function(result) {
            $('#' + el.data('input') + "Id").val("#" + result.cmd.id + "#");
            $('#' + el.data('input')).val(result.human);
        });
    });
}

function loadActions(eqType){
    $('#table_actions tbody')
        .find('tr')
        .remove()
    ;

    actions = ISSStructure[eqType]['actions'];

    $.each(actions, function(key, action){        
        if(action.type == 'item'){
            firstLine = true;

            for(optkey in action.item){
                tr = '<tr class="imperihomeAdvancedDeviceAction" data-actionName="' + key + '" data-actionType="' + action.type + '" data-actionKey="' + optkey + '">';
                tr += '    <td><b>' + key + '('  + optkey + ')</b></td>';
                tr += '    <td>';
                tr +=  '           <input class="form-control" style="width: 10%; display : inline-block;" id="cmd' + key + optkey + 'Id" disabled> ';
                tr += '            <input class="form-control" id="cmd' + key + optkey + '" placeholder="Commande Action associée" style="width: 80%; display : inline-block;" disabled> ';
                tr += '            <a class="btn btn-default cursor listEquipementAction" data-input="cmd' + key + optkey + '"><i class="fa fa-list-alt "></i></a><br>';
                tr += '     </td>';
                tr += '</tr>';

                $('#table_actions tbody').append(tr);
            }
            
        }else{
            tr = '<tr class="imperihomeAdvancedDeviceAction" data-actionName="' + key + '" data-actionType="' + action.type + '">';
            tr += '    <td><b>' + key + '</b></td>';
            tr += '    <td>';
            tr += '            <input class="form-control" style="width: 10%; display : inline-block;" id="cmd' + key + 'Id" disabled> ';
            tr += '            <input class="form-control" id="cmd' + key + '" placeholder="Commande Action associée" style="width: 80%; display : inline-block;" disabled> ';
            tr += '            <a class="btn btn-default cursor listEquipementAction" data-input="cmd' + key + '"><i class="fa fa-list-alt "></i></a>'; 
            tr += '     </td>';
            tr += '</tr>';       

            $('#table_actions tbody').append(tr);            
        } 
    });

    $("#table_actions").delegate(".listEquipementAction", 'click', function() {
        var el = $(this);
        jeedom.cmd.getSelectModal({cmd: {type: 'action'}}, function(result) {
            $('#' + el.data('input') + "Id").val("#" + result.cmd.id + "#");
            $('#' + el.data('input')).val(result.human);
        });
    });
}

function initLoadEqISS(eqId){
    if(eqId != 'new'){

    }else{

    }
}

initLoadEqISS(ISSeqId);

</script>