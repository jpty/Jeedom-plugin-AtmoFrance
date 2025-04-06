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
?>
<form class="form-horizontal">
    <fieldset>
        <div class="form-group">
            <label class="col-lg-2 control-label">{{Instructions de configuration}}</label>
            <div class="col-lg-6">
<ol>
  <li>Faire une demande de création de compte sur le site: <a href="https://admindata.atmo-france.org/inscription-api" target="_blank">Atmo France</a></li>
  <li>Initialiser le mot de passe via le lien fourni dans l'e-mail reçu d'Atmo France - Agrégateur.</li>
  <li>L'identifiant et le mot de passe créés sur le site Atmo France sont à renseigner ci-dessous.</li>
</ol>
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-2 control-label">{{Identifiant}}</label>
            <div class="col-lg-2">
                <input class="configKey form-control" data-l1key="username" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-2 control-label">{{Mot de passe}}</label>
            <div class="col-lg-2">
                <input type="password" class="configKey form-control" data-l1key="password" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-2 control-label">{{Pour plus d'informations:}}</label>
            <div class="col-lg-6">
              <a target="_blank" href="https://www.atmo-france.org/">Site Atmo France</a> &nbsp; &nbsp;
              <a target="_blank" href="http://admindata.atmo-france.org/auth/fr/login">Accés à l'API</a>
            </div>
        </div>
  </fieldset>
</form>
<!--
        <div class="form-group">
            <label class="col-lg-4 control-label">Sauvegarder la configuration avant de </label>
            <div class="col-lg-2">
                <span class="col-lg-4"><a class="btn btn-sm btn-info" id="btn-test_connection"><i class="fas fa-magic"></i> {{Test connexion}}</a></span>
            </div>
        </div>

<script>
$('#btn-test_connection').on('click',function(){
    $('#md_modal2').dialog({title: "{{Test de connexion AtmoFrance}}"});
    $('#md_modal2').load('index.php?v=d&plugin=AtmoFrance&modal=authenticate').dialog('open');
 })
</script>
-->
