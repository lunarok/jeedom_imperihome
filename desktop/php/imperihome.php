<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
sendVarToJS('eqType', 'imperihome');

$eqLogics = eqLogic::byType('imperihome');

?>


<div class="row row-overflow">
    <div class="col-lg-2 col-md-3 col-sm-4">
        <div class="bs-sidebar">
            <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
                <center style="margin-bottom: 5px;">
                    <!--<a class="btn btn-default btn-sm tooltips expertModeVisible" id="bt_adminISS" title="{{Configuration avancée ISS}}" style="display: inline-block;"><i class="fa fa-cogs"></i></a>-->
                    <a class="btn btn-default btn-sm tooltips expertModeVisible" href="plugins/imperihome/core/config/ISS-LocalConfig-<?php echo config::byKey('LocalConfigIsSet', 'imperihome'); ?>.json" download="ISS-LocalConfig-<?php echo config::byKey('LocalConfigIsSet', 'imperihome'); ?>.json" style="display: inline-block;" title="{{Télécharger le fichier de configuration manuelle}}"><i class="fa fa-download"></i></a>
                </center>

                <a class="btn btn-default eqLogicAction disabled" style="width : 100%;margin-top : 5px;margin-bottom: 5px;" data-action="add"><i class="fa fa-plus-circle"></i> {{Ajouter un équipement}}</a>
                <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
                <?php
                foreach ($eqLogics as $eqLogic) {
                    echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
                }
                ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation" class="active"><a href="#configISS" role="tab" data-toggle="tab">{{Configuration ISS}}</a></li>
            <li role="presentation"><a href="#interfaces" role="tab" data-toggle="tab">{{Mes Interfaces Impérihome}}</a></li>
        </ul>
        <div class="tab-content">
            <div role="tabpanel" class="tab-pane" id="interfaces">
                <?php
                if (count($eqLogics) == 0) {
                    echo "<br/><br/><br/><center><span style='color:#767676;font-size:1.2em;font-weight: bold;'>{{Vous n'avez pas encore d'interface de configurée, cliquez sur Ajouter un équipement pour commencer}}</span></center>";
                } else {
                    ?>
                    <div class="eqLogicThumbnailContainer">
                        <?php
                        foreach ($eqLogics as $eqLogic) {
                            echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >';
                            echo "<center>";
                            echo '<img src="plugins/imperihome/doc/images/imperihome_icon.png" height="105" width="95" />';
                            echo "</center>";
                            echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $eqLogic->getHumanName(true, true) . '</center></span>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                <?php } ?>
            </div>
            <div role="tabpanel" class="tab-pane active" id="configISS">
                <br>
                <a class="btn btn-default btn-xs" id="bt_selectAllISS"><i class="fa fa-check-square-o"></i> Sélectionner tout</a><a class="btn btn-default btn-xs" id="bt_unselectAllISS"><i class="fa"></i> Désélectionner tout</a><a class="btn btn-success pull-right" id="bt_saveISSConfig"><i class="fa fa-floppy-o"></i> Sauvegarder</a><br>
                <br>
                <table class="table table-bordered table-condensed tablesorter" id="cmdList">
                    <thead>
                        <tr>
                            <td>{{Transmettre}}</td>
                            <td>{{Pièce}}</td>
                            <td>{{Nom}}</td>
                            <td>{{Configuration}}</td>
                            <td>{{Type Imperihome}}</td>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="col-lg-10 col-md-9 col-sm-8 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
        <form class="form-horizontal">
            <fieldset>
                <legend>
                    <i class="fa fa-arrow-circle-left eqLogicAction cursor" data-action="returnToThumbnailDisplay"></i> {{Général}}</legend>
                <div class="form-group">
                    <label class="col-sm-2 control-label">{{Nom de l'interface Imperihome}}</label>
                    <div class="col-sm-3">
                        <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                        <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'interface}}"/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" >{{Objet parent}}</label>
                    <div class="col-sm-3">
                        <select class="form-control eqLogicAttr" data-l1key="object_id">
                            <option value="">{{Aucun}}</option>
                            <?php
                            foreach (object::all() as $object) {
                                echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">{{Catégorie}}</label>
                    <div class="col-sm-8">
                        <?php
                        foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                            echo '<label class="checkbox-inline">';
                            echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                            echo '</label>';
                        }
                        ?>

                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">{{Activer}}</label>
                    <div class="col-sm-1">
                        <input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>
                    </div>
                    <label class="col-sm-2 control-label" >{{Visible}}</label>
                    <div class="col-sm-1">
                        <input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">{{Commentaire}}</label>
                    <div class="col-sm-3">
                        <textarea class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="commentaire" ></textarea>
                    </div>
                </div>
            </fieldset> 
        </form>

        <legend>{{Interace Imperihome}}</legend>
        <a class="btn btn-default btn-sm" id="bt_addPreCmd"><i class="fa fa-plus-circle"></i> {{Ajouter une commande}}</a><br/>
        <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th style="width: 230px;">{{Nom}}</th>
                    <th style="width: 110px;">{{Sous-Type}}</th>
                    <th>{{Valeur}}</th>
                    <th style="width: 100px;">{{Unité}}</th>
                    <th style="width: 200px;">{{Paramètres}}</th>
                    <th style="width: 100px;"></th>
                </tr>
            </thead>
            <tbody>

            </tbody>
        </table>

        <form class="form-horizontal">
            <fieldset>
                <div class="form-actions">
                    <a class="btn btn-danger eqLogicAction" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
                    <a class="btn btn-success eqLogicAction" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
                </div>
            </fieldset>
        </form>

    </div>
</div>

<?php include_file('desktop', 'imperihome', 'js', 'imperihome'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>