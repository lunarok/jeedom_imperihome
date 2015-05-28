
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


/*$('#bt_adminISS').on('click', function () {
    $('#md_modal').dialog({title: "{{Configuration avancée ISS}}"});
    $('#md_modal').load('index.php?v=d&plugin=imperihome&modal=config.ISS').dialog('open');
});*/

$('#cmdList tbody').delegate(".bt_confEqISS", 'click', function() {
    var el = $(this);
    var id = el.data('input');
    
    $('#md_modal').dialog({title: "{{Configuration avancée ISS}}"});
    $('#md_modal').load('index.php?v=d&plugin=imperihome&modal=config.eqISS&ISSeqId=' + id).dialog('open');
});

$('#bt_selectAllISS').on('click', function () {
    $('.ISSCheckbox').each(function() { //loop through each checkbox
        this.checked = true;  //select all checkboxes with class "checkbox1"               
    });
});

$('#bt_unselectAllISS').on('click', function () {
    $('.ISSCheckbox').each(function() { //loop through each checkbox
        this.checked = false;  //select all checkboxes with class "checkbox1"               
    });
});

$('#bt_saveISSConfig').on('click', function () {
    $.ajax({
        type: 'POST',
        url: 'plugins/imperihome/core/ajax/imperihome.ajax.php',
        data: {
            action: 'transmitList',
            transmitList: JSON.stringify($('.ISSeq').getValues('.ISSeqAttr'), null, 2)
        },

        dataType: 'json',
        error: function (request, status, error) {
            $('#div_alert').showAlert({message: error.message, level: 'danger'});
        },

        success: function( data ) {
            $('#div_alert').showAlert({message: '{{Sauvegarde réussie}}', level: 'success'});
        }
    });
});

$(function() {
    loadISSDevices();
});

function loadISSDevices(){
    $('#cmdList tbody')
            .find('tr')
            .remove()
        ;

    $.ajax({
        type: 'POST',
        url: 'plugins/imperihome/core/ajax/imperihome.ajax.php',
        data: {
            action: 'listDevices'
        },

        dataType: 'json',
        error: function (request, status, error) {
            $('#div_alert').showAlert({message: error.message, level: 'danger'});
        },

        success: function( data ) {
            $.each( data.result.devices, function( key, val ) {
                $('#cmdList tbody').append( '<tr><td><span class="ISSeq"><input class="ISSeqAttr" data-l1key="id" style="display:none;" value="' + val.id + '"/><input type="checkbox" class="ISSeqAttr ISSCheckbox" data-l1key="transmit" ' + val.isTransmit + '/></span></td><td>' + val.roomName + '</td><td>' + val.name + ' (' + val.id + ')</td><td><a data-input="' + val.id + '" class="btn btn-default btn-sm tooltips bt_confEqISS" title="{{Configuration avancée}}" style="display: inline-block;"><i class="fa fa-cogs"></i></a> ' + val.confType + '</td><td>' + val.type + '</td></tr>');
            });

            $('#cmdList').trigger("update");
        }
    });
}

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }

    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
	// Nom
    tr += '<td>';
    tr += '<div class="row">';
    tr += '<div class="col-lg-6">';
    tr += '<a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fa fa-flag"></i> Icone</a>';
    tr += '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>';
    tr += '</div>';
    tr += '<div class="col-lg-6">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name">';
    tr += '</div>';
    tr += '</div>';
    tr += '<select class="cmdAttr form-control tooltips input-sm" data-l1key="value" style="display : none;margin-top : 5px;" title="La valeur de la commande vaut par defaut la commande">';
    tr += '<option value="">Aucune</option>';
    tr += '</select>';
    tr += '</td>';
	
	// Type
	tr += '<td class="expertModeVisible">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">';
    tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    tr += '</td>';
	
	// Logical ID / Commande
    tr += '<td class="expertModeVisible"><input class="cmdAttr form-control input-sm" data-l1key="logicalId" value="0">';
    tr += '</td>';
	
	// Paramètres
    tr += '<td>';
    tr += '<span><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" /> Historiser<br/></span>';
    tr += '<span><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/> Afficher<br/></span>';
    tr += '<span><input type="checkbox" class="cmdAttr expertModeVisible" data-l1key="eventOnly" /> Evénement<br/></span>';
    tr += '<span><input type="checkbox" class="cmdAttr expertModeVisible" data-l1key="display" data-l2key="invertBinary" /> Inverser<br/></span>';
    tr += '<input style="width : 150px;" class="tooltips cmdAttr form-control expertModeVisible input-sm" data-l1key="cache" data-l2key="lifetime" placeholder="Lifetime cache">';
    tr += '</td>';
	
	// Test et Suppression
    tr += '<td>';
    /* if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> Tester</a>';
    } */
    tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    var tr = $('#table_cmd tbody tr:last');
    jeedom.eqLogic.builSelectCmd({
        id: $(".li_eqLogic.active").attr('data-eqLogic_id'),
        filter: {type: 'info'},
        error: function (error) {
            $('#div_alert').showAlert({message: error.message, level: 'danger'});
        },
        success: function (result) {
            tr.find('.cmdAttr[data-l1key=value]').append(result);
            tr.find('.cmdAttr[data-l1key=configuration][data-l2key=updateCmdId]').append(result);
            tr.setValues(_cmd, '.cmdAttr');
            jeedom.cmd.changeType(tr, init(_cmd.subType));
        }
    });
}