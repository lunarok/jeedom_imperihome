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

sendVarToJS('urlToUse', config::byKey('internalProtocol') . config::byKey('internalAddr') . ":" . config::byKey('internalPort') . config::byKey('urlToUse', 'imperihome'));

sendVarToJS('LocalConfigIsSet', config::byKey('LocalConfigIsSet', 'imperihome'));

?>

<div class="row">
    <div class="col-lg-4">
        <label class="control-label">Selectionnez un équipement:</label>
    </div>
    <div class="col-lg-6">
        <select class="form-control" id="eqIdISS" name="eqIdISS">
            <option value="">Aucun</option>
        </select>
    </div>
    <div class="col-lg-2">
        <a id="eqUpdateISS" name="eqUpdateISS" class="btn btn-primary btn"><i class="fa fa-forward"></i>Actualiser</a>
</div>
<div class="row">
    <br>
</div>
<div class="row">
    <legend>
        <center>Détail de la configuration</center>
    </legend>
</div>

<div id="eqConfISSBtn"></div>
<div id="eqConfISS">
    <div class="col-lg-6" id="eqConfISSGeneral"></div>
    <div class="col-lg-6" id="eqConfISSParamAction"></div>
</div>

<script>
    var ISSStructure;
    var ISSLocalConfig;

    $('#eqIdISS').on('change', function() {
        loadISSConf($(this).val());
    });

    $('#eqUpdateISS').on('click', function() {
        loadISSConf($('#eqIdISS').val());
    });

    $(function() {
        //$.ajaxSetup({ cache: false });

        loadISSStructure();
        loadISSDevices();
    });

    function loadISSStructure(){
        $.getJSON( "plugins/imperihome/ressources/ISS-Structure.json?cache=" + new Date().getTime(), function( data ) {
            if(data != undefined){
                ISSStructure = data;
            }else{
                ISSStructure = array();
            }

            loadISSLocalConfig();
        });
    }

    function loadISSLocalConfig(){
        $.getJSON( "plugins/imperihome/core/config/ISS-LocalConfig-" + LocalConfigIsSet + ".json?cache=" + new Date().getTime(), function( data ) {
            if(data != undefined){
                ISSLocalConfig = data;
            }else{
                ISSLocalConfig = array();
            }
        });
    }

    function loadISSDevices(forceId){
        $('#eqIdISS')
            .find('optgroup')
            .remove()
        ;
        $('#eqIdISS')
            .find('option')
            .remove()
        ;

        $.getJSON( urlToUse + "rooms", function( data ) {
            $.each( data.rooms, function( key, val ) {
                $('#eqIdISS').append( '<optgroup label="' + val.name + '" id="' + val.id + '"></optgroup>');
            });

            $.getJSON( urlToUse + "devices", function( data ) {
                $.each( data.devices, function( key, val ) {
                    $('#eqIdISS optgroup[id=' + val.room + ']').append( '<option value="' + val.id + '">' + val.name + '</option>');

                });

                $('#eqConfISSBtn').html('');
                $('#eqConfISSGeneral').html('');
                $('#eqConfISSParamAction').html('');

                if(forceId != null){
                    $('#eqIdISS option[value="' + forceId + '"]').prop('selected', true);
                }

                loadISSConf($('#eqIdISS').val());

            });
        });

    }

    function loadISSConf(id){
        var eqConf;

        if(id != '') {
            $.getJSON( urlToUse + "devices", function( data ) {
                $.each( data.devices, function( key, val ) {
                    if(val.id == id){
                        loadConfOnDiv(val);
                    }
                });
            });
        }else{
            // Vider le DIV
            $('#eqConfISS').html('');
        }
    }

    function loadConfOnDiv(eqConf, forceMode){
        var confModeMan = (eqConf.id in ISSLocalConfig);
        var localConfig = {}; 

        // On garde la config locale en mémoire
        if(confModeMan){
            localConfig = ISSLocalConfig[eqConf.id];
        }

        if(typeof(forceMode) != 'undefined'){
            if(forceMode == "auto"){
                confModeMan = false; // 1: manuel, 0: auto
            }

            if(forceMode == "manuel"){
                confModeMan = true; // 1: manuel, 0: auto
            }
        }

        loadGeneralConfOnDiv(eqConf, confModeMan, localConfig);

        loadParamActionConfOnDiv(eqConf, confModeMan, localConfig);

        
        div = '    <div class="col-lg-12">';
        div += '        <a class="btn btn-success" id="eqSave"><i class="fa fa-check-circle"></i> Sauvegarder</a>';
        div += '    </div>';
        $('#eqConfISSBtn').html(div);

        $('#eqSave').on('click', function(eqConf, confModeMan) {
            return function() {
                if(confModeMan){ // En mode manuel: on récupère la configuration

                    confToSave = {};

                    confToSave[eqConf.id] = {};
                    confToSave[eqConf.id].params = {};
                    confToSave[eqConf.id].actions = {};

                    confToSave[eqConf.id].type = $('#eqType').val();

                    $.each(ISSStructure[confToSave[eqConf.id].type].params ,function( key, val ) {
                        confToSave[eqConf.id].params[val.ParamKey] = {};
                        confToSave[eqConf.id].params[val.ParamKey].cmdId = $('#cmd' + val.ParamKey).val();
                    });

                    $.each(ISSStructure[confToSave[eqConf.id].type].actions ,function( key, val ) {
                            confToSave[eqConf.id].actions[key] = {};

                            confToSave[eqConf.id].actions[key].type = val.type;
                            confToSave[eqConf.id].actions[key].options = {};
                            
                            if(val.type == 'item'){
                                $.each(val.item ,function( valeur, correspondance ) {
                                    confToSave[eqConf.id].actions[key].options[valeur] = {};

                                    confToSave[eqConf.id].actions[key].options[valeur].cmdId = $('#cmd' + key + valeur).val();
                                    confToSave[eqConf.id].actions[key].options[valeur].cmdParam = ""; // CHAMPS A AJOUTER
                                });
                            }

                            if(val.type == 'direct'){
                                confToSave[eqConf.id].actions[key].options.cmdId = $('#cmd' + key).val();
                                confToSave[eqConf.id].actions[key].options.cmdParam = ""; // CHAMPS A AJOUTER
                            }
                    });
                }else{
                    // En mode automatique: on a rien à récuperer
                    confToSave = {};
                    confToSave[eqConf.id] = {};
                    confToSave[eqConf.id].type = "auto";
                }
                
                var reloadCallBack = function(forceId) {
                    return function(data, textStatus, jqXHR) {
                        // Rechargement des devices et de la config locale
                        loadISSDevices(forceId);
                        loadISSLocalConfig();
                    };
                };

                $.ajax({
                    type: 'POST',
                    url: 'plugins/imperihome/core/ajax/imperihome.ajax.php',
                    data: {
                        action: 'saveLocalConf',
                        conf : confToSave
                    },

                    dataType: 'json',
                    error: function (request, status, error) {
                        //handleAjaxError(request, status, error, $('#div_smsShowDebug'));
                        console.log("Erreur lors de la requête de sauvegarde");
                    },

                    success: reloadCallBack(eqConf.id) 
                });
            };
        }(eqConf, confModeMan));

        
        /*
        jeedom.cmd.byHumanName({
                humanName: "#[Couloir][Lumiere Couloir][Etat]#",
                error: function (error) {
                    console.log(error.log);
                },
                success: function (data) {
                    console.log(data);
                }
        });
        */

    }

    function loadGeneralConfOnDiv(eqConf, confModeMan, localConfig){
        // COLONNE PARAMETRES GENERAUX
        var div = '        <form class="form-horizontal">';
            div += '        <div class="form-group">';
            div += '            <label class="col-lg-6 control-label">Type de configuration</label>';
            div += '            <div class="col-lg-6">' + eqConf.name + '</div>';
            div += '        </div>';
            div += '        <div class="form-group">';
            div += '            <label class="col-lg-6 control-label">Type de configuration</label>';

        if(confModeMan){
            div += '            <div class="col-lg-6"><span class="label label-primary" id="eqMode">Manuelle</span> <span class="label label-success"><a href="#" style="color : white;" id="eqModeSwitch">Passer en Automatique</a></span></div>';
            div += '        </div>';
        }else{
            if(eqConf.type == 'DevScene'){
                div += '            <div class="col-lg-6"><span class="label label-primary" id="eqMode">Automatique</span></div>';
            }else{
                div += '            <div class="col-lg-6"><span class="label label-primary" id="eqMode">Automatique</span> <span class="label label-success"><a href="#" style="color : white;" id="eqModeSwitch">Passer en Manuelle</a></span></div>';
            }
            div += '        </div>';
        }

            div += '        <div class="form-group">';
            div += '            <label class="col-lg-6 control-label">ID</label>';
            div += '            <div class="col-lg-6">' + eqConf.id + '</div>';
            div += '        </div>';
            div += '        <div class="form-group">';
            div += '            <label class="col-lg-6 control-label">Pièce</label>';
            div += '            <div class="col-lg-6">' + eqConf.room + '</div>';
            div += '        </div>';
            div += '        </form>';

        
        $('#eqConfISSGeneral').html(div);

        $('#eqModeSwitch').on('click', function(eqConf, forceMode) {
            return function() {
                if(forceMode){
                    forceMode = "manuel";
                }else{
                    forceMode = "auto";
                }

                loadConfOnDiv(eqConf, forceMode);
            };
        }(eqConf, !confModeMan));

    }

    function loadParamActionConfOnDiv(eqConf, confModeMan, localConfig){
        // COLONNE PARAMTRE/ACTION

        div = '        <form class="form-horizontal" id="formParamAction">';
        div += '        <div class="form-group">';
        div += '            <label class="col-lg-4 control-label">Type de l\'équipement</label>';

        if(confModeMan){
            var selectTypeISS = '<select id="eqType" class="form-control">';

            $.each( ISSStructure, function( key, val ) {
                if(key != "DevScene"){
                    selectTypeISS += '<option value="' + key + '">' + val.Description + ' (' + key + ')</option>';
                }               
            });
            selectTypeISS += '</select>';

            div += '            <div class="col-lg-8">' + selectTypeISS + '</div>';

        }else{
            div += '            <div class="col-lg-8">' + ISSStructure[eqConf.type].Description + ' (' + eqConf.type + ')</div>';
        
        }
            div += '        </div>';


        if(confModeMan){

            $.each(ISSStructure[eqConf.type].params ,function( key, val ) {

                    div += '        <div class="form-group">';
                    div += '            <label class="col-lg-4 control-label">Paramètre \'' + val.ParamKey + '\'</label>';
                    div += '            <div class="col-lg-8">' + val.Description + '<br>';
                    for(var i= 0; i < eqConf.params.length; i++){
                        if(eqConf.params[i].key == val.ParamKey){
                            div += '            Valeur actuelle: ' + eqConf.params[i].value + '<br>';
                        }
                    }
                    div += '            <input type="hidden" id="cmd' + val.ParamKey + 'Id" value="">';
                    div += '            <input class="form-control" id="cmd' + val.ParamKey + '" placeholder="Commande Info associée" style="width: 80%; display : inline-block;" value="' + ('params' in localConfig ? (val.ParamKey in localConfig.params ? localConfig.params[val.ParamKey].cmdId : "") : "") + '" disabled>';
                    div += '            <a class="btn btn-default cursor listEquipementInfo" data-input="cmd' + val.ParamKey + '"><i class="fa fa-list-alt "></i></a>';
                    div += '            </div>';
                    div += '        </div>';
            });

            $.each(ISSStructure[eqConf.type].actions ,function( key, val ) {
                    div += '        <div class="form-group">';
                    div += '            <label class="col-lg-4 control-label">Action \'' + key + '\'</label>';
                    div += '            <div class="col-lg-8">';
                    div += '            Type: ' + val.type + '<br>';
                    if(val.type == 'item'){
                        $.each(val.item ,function( valeur, correspondance ) {
                            div += '            - Valeur "' + valeur + '":<br>';
                            div += '            <input type="hidden" id="cmd' + key + valeur + 'Id" value="">';
                            div += '            <input class="form-control" id="cmd' + key + valeur + '" placeholder="Commande Action associée" style="width: 80%; display : inline-block;" value="' + ('actions' in localConfig ? (key in localConfig.actions ? ('options' in localConfig.actions[key] ? (valeur in localConfig.actions[key].options ? localConfig.actions[key].options[valeur].cmdId : "") : "") : "") : "") + '" disabled>';
                            div += '            <a class="btn btn-default cursor listEquipementAction" data-input="cmd' + key + valeur + '"><i class="fa fa-list-alt "></i></a>'; 
                        });
                    }

                    if(val.type == 'direct'){
                        div += '            <input type="hidden" id="cmd' + key + 'Id" value="">';
                        div += '            <input class="form-control" id="cmd' + key + '" placeholder="Commande Action associée" style="width: 80%; display : inline-block;" value="' + ('actions' in localConfig ? (key in localConfig.actions ? ('options' in localConfig.actions[key] ?  localConfig.actions[key].options.cmdId : "") : "") : "") + '" disabled>';
                        div += '            <a class="btn btn-default cursor listEquipementAction" data-input="cmd' + key + '"><i class="fa fa-list-alt "></i></a>'; 
                    }

                    div += '            </div>';
                    div += '        </div>';
            });

        }else{
            $.each(ISSStructure[eqConf.type].params ,function( key, val ) {
                    div += '        <div class="form-group">';
                    div += '            <label class="col-lg-4 control-label">Paramètre \'' + val.ParamKey + '\'</label>';
                    div += '            <div class="col-lg-8">' + val.Description + '<br>';

                    if(val.unit){
                        div += '            Unité: ' + val.unit + '<br>';
                    }

                    if(val.graphable){
                        div += '            Graphable: ' + val.graphable + '<br>';
                    }

                    for(var i= 0; i < eqConf.params.length; i++){
                        if(eqConf.params[i].key == val.ParamKey){
                            div += '            Valeur actuelle: ' + eqConf.params[i].value;
                        }
                    }

                    div += '            </div>';
                    div += '        </div>';
            });

            $.each(ISSStructure[eqConf.type].actions ,function( key, val ) {
                    div += '        <div class="form-group">';
                    div += '            <label class="col-lg-4 control-label">Action \'' + key + '\'</label>';
                    div += '            <div class="col-lg-8">';
                    div += '            Type: ' + val.type + '<br>';
                    if(val.type == 'item'){
                        $.each(val.item ,function( valeur, correspondance ) {
                            div += '            - Valeur "' + valeur + '": ' + correspondance + '<br>';
                        });
                    }

                    if(val.type == 'direct'){
                        div += '            Equivalent recherché: ' + val.equivalent + '<br>';
                    }

                    div += '            </div>';
                    div += '        </div>';
            });

            
        }
            div += '        </form>';

            $('#eqConfISSParamAction').html(div);

        if(confModeMan){
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

            // Selection du type paramétré
            $('#eqType option[value="' + eqConf.type + '"]').prop('selected', true);

            $("#formParamAction").delegate(".listEquipementInfo", 'click', function() {
                var el = $(this);
                jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function(result) {
                    //$('#' + el.data('input') + "Id").val(result.cmd.id);
                    //$('#' + el.data('input')).val(result.human);
                    $('#' + el.data('input')).val(result.cmd.id);
                });
            });

            $("#formParamAction").delegate(".listEquipementAction", 'click', function() {
                var el = $(this);
                jeedom.cmd.getSelectModal({cmd: {type: 'action'}}, function(result) {
                    //$('#' + el.data('input') + "Id").val(result.cmd.id);
                    //$('#' + el.data('input')).val(result.human);
                    $('#' + el.data('input')).val(result.cmd.id);
                });
            });

            $('#eqType').on('change', function(eqConf, confModeMan, localConfig) {
                return function() {
                    eqConf.type =  $(this).val();

                    loadParamActionConfOnDiv(eqConf, confModeMan, localConfig)
                };
            }(eqConf, confModeMan, localConfig));



            /*if(typeof(localConfig.param) == "object"){
                for(var i= 0; i < localConfig.params.length; i++){
                    if(eqConf.params[i].key == val.ParamKey){
                        div += '            Valeur actuelle: ' + eqConf.params[i].value;
                    }
                }
            }*/

        }
    }

</script>