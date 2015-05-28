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

function test_url($url){
    $test_URL = @file_get_contents($url . 'system');

    if($test_URL === FALSE) {
        log::add('imperihome', 'debug', 'Configuration: test_url -> Impossible d\'accéder à l\'URL! Retour de la commande file_get_contents à False [' . $url . ']');
        return false;
    }
    
    if($test_URL){
        $test_URL = json_decode($test_URL, true);
    }else{
        log::add('imperihome', 'debug', 'Configuration: test_url -> Impossible d\'accéder à l\'URL! $test_URL = false [' . $url . ']');
        return false;
    }

    if(is_array($test_URL)){
        if($test_URL['id'] == config::byKey('api')){
            log::add('imperihome', 'debug', 'Configuration: test_url -> Test OK de l\'URL [' . $url . ']');
            return true;
        }else{
            log::add('imperihome', 'debug', 'Configuration: test_url -> Incohérence de clé API retournée! [' . $url . ']');
            return false;
        }
    }else{
        log::add('imperihome', 'debug', 'Configuration: test_url -> Impossible de lire la chaine retournée: non json! [' . $url . ']');
        return false;
    }
}

?>


<form class="form-horizontal">
    <fieldset>
        <div class="form-group">
            <label class="col-lg-4 control-label">Utiliser le cache: </label>
            <div class="col-lg-8">
                L'utilisation du cache permet de soulager les petites configurations, mais réduit la réactivité de l'interface.
                <select class="configKey form-control" id="select_cache" data-l1key="useCache">
                    <option value="1">Oui</option>
                    <option value="0">Non</option>
                </select></div>
        </div>

        <div class="form-group">
            <label class="col-lg-4 control-label">Votre type de serveur: </label>
            <div class="col-lg-8"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></div>
        </div>

        <div class="form-group">
            <label class="col-lg-4 control-label">Lien de l'API ISS à indiquer dans ImperiHome: </label>
            <div class="col-lg-8"><?php 
                //$url_base = config::byKey('internalProtocol') . config::byKey('internalAddr') . ":" . config::byKey('internalPort');
                $url_base = network::getNetworkAccess();
                //$url_rewrite_nginx = "/iss/" . config::byKey('api') . "/";
                $url_full = "/plugins/imperihome/core/php/imperihome.php?_url=/" . config::byKey('api') . "/";
                


                $urlToUse = "";

                if(test_url($url_base . $url_full)){
                    echo "Test URL de base: OK<br>";
                    $urlToUse = $url_full;

                    /*if(test_url($url_base . $url_rewrite_nginx)){
                        //echo "Test ISS Nginx: OK<br>";
                        $urlToUse = $url_rewrite_nginx;
                    }else{
                        //echo "Test ISS Nginx: Non disponible<br>";
                    }*/

                    if($urlToUse != ""){
                        echo "<br>Sous Imperihome, vous pouvez utiliser cette URL pour configurer votre système:<br><b>" . $url_base . $urlToUse . "</b>";
                        config::save('urlToUse', $urlToUse, 'imperihome');
                    }else{
                        echo "<br><b>La configuration automatique semble ne pas avoir fonctionnée. Veuillez suivre les instructions ci-dessous ou aller sur le forum Jeedom pour demander une assistance.</b><br>Le plugin ne peut pas fonctionner en l'état.";
                    }

                }else{
                    echo "Test URL de base: Non OK <br>Le plugin ne fonctionne pas ou la configuration réseau n'est pas correcte, vérifiez-la: page Général -> Administration -> Configuration.<br>";
                }
                

            ?></div>
        </div>
        
        <?php 
            if($urlToUse == ""){
        ?>
        <div class="form-group">
            <label class="col-lg-4 control-label">Configuration manuelle du serveur web: </label>
            <div class="col-lg-8">
                Tout d'abord, <b>vérifiez la configuration réseau de votre Jeedom</b> dans la page: <br>
                 Général -> Administration -> Configuration<br>
                 (penser à ce mettre en mode Expert)<br>
                <br>
                Actuellement, le plugin détecte cette URL pour Jeedom: <br>
                <a href="<?php echo $url_base;?>"><b><?php echo $url_base;?></b></a><br>
                Est-elle correcte? Si non, corrigez la configuration.<br>
                Tant que celle-ci n'est pas correcte, ne pas passer à la suite...<br>
            <?php 
                if(strpos($_SERVER['SERVER_SOFTWARE'], 'apache') !== false){
            ?>
                
            <?php 
                }elseif(strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false){
            ?>
                <!--<br>
                <b>Ce plugin nécessite de modifier la configuration du serveur Web.</b><br>
                Si vous n'êtes pas dans une configuration "standard" (box, ou installation de base sous Nginx), et que vous n'avez pas donné les droits nécessaires à Jeedom, il est possible que la configuration n'ai pas été modifiée automatiquement à l'installation.<br>
                <br>
                Tentez d'éxécuter cette commande en SSH:<br>
                    <pre>sudo /usr/share/nginx/www/jeedom/install/install.sh update_nginx</pre>
                <br>
                <b>Désactivez et réactivez le plugin.</b><br>
                <br>
                <b>Si le probléme persiste, voici la configuration à appliquer à NGinx manuellement:</b><br>
                Ouvrir le fichier de configuration de nginx via la commande:
                    <pre>sudo nano /etc/nginx/sites-enabled/default</pre>
                  
                Insérer les lignes suivantes juste avant la première ligne "location /... {":<br>
                (attention, vérifiez bien que le chemin est correcte sur votre config)
                    <pre>
location /iss/ {
        try_files $uri $uri/ @rewrite;
}

location @rewrite {
        rewrite ^/iss/(.*)$ <?php // echo config::byKey('internalComplement'); ?>/plugins/imperihome/core/php/imperihome.php?_url=/$1;
}
                    </pre>
                    
                Enregistrez le fichier (Ctrl + O, Entrer) et quittez (Ctrl + X).<br>
                <br>
                Puis redémarrez Nginx via la commande:
                    <pre>sudo /etc/init.d/nginx restart</pre>
                <br>
                -->
            <?php 
                }else{
            ?>
                <br>
                Votre serveur n'est pas reconnu par le plugin.<br>
                <br>
            <?php 
            }
            ?>
            Si besoin, allez demander une assistance sur le forum de Jeedom sur le post dédié au plugin.<br>
            </div>
        </div>
        <?php
            }
        ?>		
    </fieldset>
</form>