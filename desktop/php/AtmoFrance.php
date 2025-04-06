<?php
if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}
// Déclaration des variables obligatoires
$pluginName = 'AtmoFrance';
$plugin = plugin::byId($pluginName);
sendVarToJS('eqType', $plugin->getId());
sendVarToJs('pluginName', $pluginName);
$eqLogics = eqLogic::byType($plugin->getId());
$id = $_GET['id'] ?? -1;
// message::add("AtmoFrance", "ID eqLogic $id");
?>

<div class="row row-overflow">
  <!-- Page d'accueil du plugin -->
  <div class="col-xs-12 eqLogicThumbnailDisplay">
    <legend><i class="fas fa-cog"></i>  {{Gestion}}</legend>
    <!-- Boutons de gestion du plugin -->
    <div class="eqLogicThumbnailContainer">
      <div class="cursor eqLogicAction logoPrimary" data-action="add">
        <i class="fas fa-plus-circle"></i>
        <br>
        <span>{{Ajouter}}</span>
      </div>
      <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
        <i class="fas fa-wrench"></i>
        <br>
        <span>{{Configuration}}</span>
      </div>
    </div>
		<legend><i class="fas fa-table"></i> {{Mes équipements}}</legend>
			<?php
    if (count($eqLogics) == 0) {
      echo '<br><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucun équipement trouvé, cliquer sur "Ajouter" pour commencer}}</div>';
    } else {
      // Champ de recherche
      echo '<div class="input-group" style="margin:5px;">';
      echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">';
      echo '<div class="input-group-btn">';
      echo '<a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>';
      echo '<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>';
      echo '</div>';
      echo '</div>';
      // Liste des équipements du plugin
      echo '<div class="eqLogicThumbnailContainer">';
      foreach ($eqLogics as $eqLogic) {
        $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
        echo '<div class="eqLogicDisplayCard cursor ' .$opacity .'" data-eqLogic_id="' . $eqLogic->getId() . '">';
        echo '<img src="' . $plugin->getPathImgIcon() . '">';
        echo '<br>';
        echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
        echo '<span class="hiddenAsCard displayTableRight hidden">';
        echo ($eqLogic->getIsVisible() == 1) ? '<i class="fas fa-eye" title="{{Equipement visible}}"></i>' : '<i class="fas fa-eye-slash" title="{{Equipement non visible}}"></i>';
        echo '</span>';
        echo '</div>';
      }
        echo '</div>';
      }
    ?>
	</div> <!-- /.eqLogicThumbnailDisplay -->
	
  <!-- Page de présentation de l'équipement -->
  <div class="col-xs-12 eqLogic" style="display: none;">
    <!-- barre de gestion de l'équipement -->
    <div class="input-group pull-right" style="display:inline-flex;">
      <span class="input-group-btn">
        <!-- Les balises <a></a> sont volontairement fermées à la ligne suivante pour éviter les espaces entre les boutons. Ne pas modifier -->
        <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
        </a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
        </a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
        </a>
      </span>
    </div>
    <!-- Onglets -->
    <ul class="nav nav-tabs" role="tablist">
      <li role="presentation"><a class="eqLogicAction cursor" aria-controls="home" role="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
      <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
      <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Commandes}}</a></li>
    </ul>
    <div class="tab-content">
      <!-- Onglet de configuration de l'équipement -->
      <div role="tabpanel" class="tab-pane active" id="eqlogictab">
        <!-- Partie gauche de l'onglet "Equipements" -->
        <!-- Paramètres généraux et spécifiques de l'équipement -->
        <form class="form-horizontal">
          <fieldset>
          <div class="col-lg-6">
            <legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
            <div class="form-group">
              <label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
              <div class="col-sm-4">
                <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}"/>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-3 control-label" >{{Objet parent}}</label>
              <div class="col-sm-4">
                <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                  <option value="">{{Aucun}}</option>
                  <?php
                    $options = '';
                    foreach ((jeeObject::buildTree(null, false)) as $object) {
                      $options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                    }
                    echo $options;
                  ?>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-3 control-label">{{Catégorie}}</label>
              <div class="col-sm-6">
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
              <label class="col-sm-3 control-label"></label>
              <div class="col-sm-9">
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
              </div>
            </div>

              <legend><i class="fas fa-cogs"></i> {{Paramètres spécifiques}}</legend>
            <div class="form-group">
              <label class="col-sm-3 control-label">{{Type}}
              </label>
              <div class="col-sm-4">
                <select id="sel_object" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="typeEquipment">
                  <option value="pollens">Pollens</option>
                  <option value="aqi">AQI</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-3 control-label">{{Code postal}}
              </label>
              <div class="col-sm-4 input-group">
                <input id="zipCode" type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="zipCode"/>
                <span class="input-group-btn" style="vertical-align:top">
                  <button title="Rechercher INSEE" type="button" class="btn btn-default" id="btnGetInsee"><i class="icon fas fa-search"></i></button>
                </span>
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-3 control-label">{{Commune}}</label>
              <div class="col-sm-4 input-group">
                <select id="codeZone" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="codeZone">
                  <?php
                    $found =0; $nb=0;
                    if($id != -1) {
                      $eqLogic = eqLogic::byId($id);
                      $zipCode = $eqLogic->getConfiguration("zipCode", -1);
                      $codeZone = $eqLogic->getConfiguration("codeZone", -1);
                      if($zipCode != -1) {
                        $insees = AtmoFrance::getCodeInseeFromZipCode($zipCode);
                        if(count($insees) == 1) {
                          $insee = $insees[0];
                          $value = $insee->code .',' .$insee->codeEpci; $name = $insee->nom;
                          echo "<option value=\"$value\" selected>$name</option>";
                          $found =1; $nb=1;
                        }
                        else {
                          foreach($insees as $insee) {
                            $value = $insee->code .',' .$insee->codeEpci; $name = $insee->nom;
                            if($value == $codeZone) {
                              echo "<option value=\"$value\" selected>$name</option>";
                              $found++;
                            }
                            else echo "<option value=\"$value\">$name</option>";
                            $nb++;
                          }
                        }
                      }
                    }
                    if(!$found && $nb) {
                      echo "<option value=\"\" selected>Sélectionner une commune</option>";
                    }
                    elseif(!$nb) echo "<option value=\"\" selected>Rechercher une commune</option>";
                  ?>
                </select>
              </div>
            </div>
<!--
            <div class="form-group">
              <label class="col-sm-3 control-label">{{Affichage jours de prévision}}</label>
              <div class="col-sm-4">
                <select id="sel_object" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="displayNDays">
                  <option value="1">1</option>
                  <option value="2">2</option>
                  <option value="3">3</option>
                </select>
              </div>
            </div>
-->
            <div class="form-group">
              <label class="col-sm-3 control-label">{{Token Expires}}</label>
              <div class="col-sm-4">
                <?php
                    echo config::byKey("tokenExpires", $pluginName, '');
                  ?>
              </div>
            </div>
          </div>
          </fieldset>
        </form>
      </div><!-- /.tabpanel #eqlogictab-->

      <!-- Onglet des commandes de l'équipement -->
      <div role="tabpanel" class="tab-pane" id="commandtab">
        <br>
        <div class="table-responsive">
        <table id="table_cmd" class="table table-bordered table-condensed">
          <thead>
            <tr>
              <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
              <th style="min-width:100px;width:200px;">{{LogicalId}}</th>
              <th style="min-width:120px;width:250px;">{{Nom}}</th>
              <th>{{Paramètres}}</th>
              <th>{{Etat}}</th>
              <th style="min-width:80px;">{{Actions}}</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
        </table>
        </div>
    </div><!-- /.tabpanel #commandtab-->
		
    </div><!-- /.tab-content -->
  </div><!-- /.eqLogic -->
</div><!-- /.row row-overflow -->

<!-- Inclusion du fichier javascript du plugin (dossier, nom_du_fichier, extension_du_fichier, id_du_plugin) -->
<?php include_file('desktop', $pluginName, 'js', $pluginName);?>
<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js'); ?>
