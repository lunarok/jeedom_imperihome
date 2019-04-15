
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

 $('#bt_selectAllISS').on('click', function () {
     $('.issAttr').each(function() { //loop through each checkbox
         this.setValues(true);  //select all checkboxes with class "checkbox1"
     });
 });

 $('#bt_unselectAllISS').on('click', function () {
     $('.issAttr').each(function() { //loop through each checkbox
         this.setValues(false);  //select all checkboxes with class "checkbox1"
     });
 });

 $('.bt_newAdvancedDevice').on('click',function(){
     $('#md_modal').dialog({title: "{{Mode avancé ISS}}"});
     $('#md_modal').load('index.php?v=d&plugin=imperihome&modal=config.eqISS&ISSeqId=new').dialog('open');
 });

jwerty.key('ctrl+s', function (e) {
    e.preventDefault();
    $(".bt_saveISSConfig").click();
});

 $('.bt_saveISSConfig').on('click',function(){
    var imperihome = {};
    $('tr.imperihome').each(function(){
        imperihome[$(this).attr('data-cmd_id')] = $(this).getValues('.imperihomeAttr')[0]
    });
    $('tr.imperihomeScenario').each(function(){
        imperihome['scenario'+$(this).attr('data-scenario_id')] = $(this).getValues('.imperihomeAttr')[0]
    });
    $.ajax({
        type: "POST",
        url: "plugins/imperihome/core/ajax/imperihome.ajax.php",
        data: {
            action: "saveISSConfig",
            config: json_encode(imperihome),
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            $('#div_alert').showAlert({message: '{{Sauvegarde réalisée avec succès}}', level: 'success'});
        }
    });
});

 function loadConf(){
    $.ajax({
        type: "POST",
        url: "plugins/imperihome/core/ajax/imperihome.ajax.php",
        data: {
            action: "loadISSConfig",
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            var imperihome = data.result;
            for(var i in data.result){
                if(i.indexOf('scenario') != -1){
                    $('tr.imperihomeScenario[data-scenario_id='+i.replace("scenario", "")+']').setValues(data.result[i],'.imperihomeAttr');
                }else{
                    $('tr.imperihome[data-cmd_id='+i+']').setValues(data.result[i],'.imperihomeAttr');
                }
            }
        }
    });
}

function loadAdvancedConf(){
    $('#cmdListAdvanced tbody')
        .find('tr')
        .remove()
    ;

    $.ajax({// fonction permettant de faire de l'ajax
        type: "POST", // méthode de transmission des données au fichier php
        url: "plugins/imperihome/core/ajax/imperihome.ajax.php", // url du fichier php
        data: {
            action: "loadAdvancedISSConfig",
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

            for(var i in data.result){
                var tr = '<tr>';
                tr += '<td>' + data.result[i].humanName + ' (manual' + data.result[i].id + ')</td>';
                tr += '<td><span class="label label-info" style="font-size : 1em;">' + data.result[i].type + '</span></td>';
                tr += '<td><a class="btn btn-danger btn-xs pull-right bt_deleteAdvancedConfig" data-id="' + data.result[i].id + '"><i class="fas fa-minus"></i> Supprimer</a><a class="btn btn-warning btn-xs pull-right bt_editAdvancedConfig" data-id="' + data.result[i].id + '"><i class="fa"></i> Modifier</a></td>';
                tr += '</tr>';
                $('#cmdListAdvanced tbody').append(tr);
            }

            $('.bt_editAdvancedConfig').on('click',function(){
                $('#md_modal').dialog({title: "{{Mode avancé ISS}}"});
                $('#md_modal').load('index.php?v=d&plugin=imperihome&modal=config.eqISS&ISSeqId=' + $( this ).data('id')).dialog('open');
            });

            $('.bt_deleteAdvancedConfig').on('click', function() {

                    bootbox.confirm('<b>Etes-vous sûr de vouloir supprimer cet équipement?</b><br>', function(deviceId){
                        return function(result) {
                        if (result) {
                            $.ajax({// fonction permettant de faire de l'ajax
                                type: "POST", // méthode de transmission des données au fichier php
                                url: "plugins/imperihome/core/ajax/imperihome.ajax.php", // url du fichier php
                                data: {
                                    action: "deleteAdvancedDevice",
                                    deviceId: deviceId
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

                                    $('#div_alert').showAlert({message: '{{Suppression réalisée avec succès}}', level: 'success'});
                                    loadAdvancedConf();
                                }
                            });
                        }
                    };

                    }($( this ).data('id')));
            });
        }
    });
}

function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = {configuration: {}};
  }

  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
  tr += '<td>';
  tr += '<span class="cmdAttr" data-l1key="id"></span>';
  tr += '</td><td>';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom de la commande}}"></td>';
  tr += '</td><td>';
  tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
  if (_cmd.type == 'info') {
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
  }
  tr += '</td><td>';
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
  }
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
      tr.setValues(_cmd, '.cmdAttr');
      jeedom.cmd.changeType(tr, init(_cmd.subType));
    }
  });

}
