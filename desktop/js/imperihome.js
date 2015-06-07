
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
 $('.bt_saveISSConfig').on('click',function(){
    var imperihome = {};
    $('tr.imperihome').each(function(){
        imperihome[$(this).attr('data-cmd_id')] = $(this).getValues('.imperihomeAttr')[0]
    });
    $('tr.imperihomeScenario').each(function(){
        imperihome['scenario'+$(this).attr('data-scenario_id')] = $(this).getValues('.imperihomeAttr')[0]
    });
     $.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // méthode de transmission des données au fichier php
            url: "plugins/imperihome/core/ajax/imperihome.ajax.php", // url du fichier php
            data: {
                action: "saveISSConfig",
                config: json_encode(imperihome),
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

$('#bt_selectAllISS').on('click', function () {
    $('.imperihomeAttr').each(function() { //loop through each checkbox
        this.checked = true;  //select all checkboxes with class "checkbox1"               
    });
});

$('#bt_unselectAllISS').on('click', function () {
    $('.imperihomeAttr').each(function() { //loop through each checkbox
        this.checked = false;  //select all checkboxes with class "checkbox1"               
    });
});

$('.bt_newAdvancedDevice').on('click',function(){
    $('#md_modal').dialog({title: "{{Mode avancé ISS}}"});
    $('#md_modal').load('index.php?v=d&plugin=imperihome&modal=config.eqISS&ISSeqId=new').dialog('open');
});


loadConf();

function loadConf(){
    $.ajax({// fonction permettant de faire de l'ajax
        type: "POST", // méthode de transmission des données au fichier php
        url: "plugins/imperihome/core/ajax/imperihome.ajax.php", // url du fichier php
        data: {
            action: "loadISSConfig",
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

