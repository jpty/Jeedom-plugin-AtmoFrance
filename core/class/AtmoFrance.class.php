<?php
declare(strict_types=1);
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

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class AtmoFrance extends eqLogic {
  public static $_widgetPossibility = array('custom' => true, 'custom::layout' => false);

  public static function cronDaily() {
    foreach (eqLogic::byType(__CLASS__, true) as $eqLogic) {
      $typeEquipment = trim($eqLogic->getConfiguration('typeEquipment', ''));
      $eqLogic->updateDataFromJsonCmdValue($typeEquipment);
    }
  }

  public function updateDataFromJsonCmdValue($typeEquipment) : int {
    log::add(__CLASS__, 'debug', __FUNCTION__ ." {$typeEquipment} [" .$this->getName());
    $Cmd = $this->getCmd(null, "{$typeEquipment}Json");
    if(is_object($Cmd)) {
      $cmdValue = $Cmd->execCmd();
      $cmdValue = str_replace('&#34;', '"', $cmdValue);
      $dec = json_decode($cmdValue,true);
      if($dec != null) {
        $changed = false;
        $changed = $this->processDataFromJson($typeEquipment, $dec) || $changed;
        if($changed) $this->refreshWidget();
        return 0;
      }
      else {
        log::add(__CLASS__, 'debug', "Unable to json_decode cmdValue($cmdValue) from eqpt [" .$this->getName() ."]");
        return 1;
      }
    }
    else {
      log::add(__CLASS__, 'debug', "Cmd {$typeEquipment}Json not found");
      return 1;
    }
  }

  public static function cron() {
    $h = date('G');
    if($h < 12 || $h >= 15) { // Avant midi ou à partir de 15h
      $minute = date('i');
      $minuteRecup = (int)config::byKey('minuteRecup', __CLASS__, -1);
      if($minuteRecup == -1) {
        $minuteRecup = rand(1,59);
        log::add(__CLASS__, 'debug', "Creating minuteRecup: $minuteRecup");
        config::save('minuteRecup', $minuteRecup, __CLASS__);
      }
      if($minute == $minuteRecup) { // TODO
        foreach (eqLogic::byType(__CLASS__, true) as $eqLogic) {
          $changed = $eqLogic->fetchInformation(__FUNCTION__);
          if($changed) $eqLogic->refreshWidget();
        }
      }
    }
    // else log::add(__CLASS__, 'debug', "Cron between 12 and 15");
  }

  public function checkAndUpdateCmd($_logicalId, $_value, $_updateTime = null) {
    $loglevel = log::convertLogLevel(log::getLogLevel(__CLASS__));
    if($loglevel == 'debug') {
      $cmd = $this->getCmd('info', $_logicalId);
      if (!is_object($cmd)) {
        log::add(__CLASS__, 'debug', "Equipment: [" .$this->getName() ."] Unexistant command [{$_logicalId}]");
      }
    }
    parent::checkAndUpdateCmd($_logicalId, $_value, $_updateTime);
  }

  public static function initParams(&$params): int {
    // init user, passwd et nom ou Id du virtuel support des commandes.
    $username = trim(config::byKey('username', __CLASS__));
    $password = trim(config::byKey('password', __CLASS__));
    if($username == '' || $password == '') {
      log::add(__CLASS__, 'warning', "Empty 'username' or 'password'");
      return(1);
    }
    $params['username'] = $username;
    $params['password'] = $password;
    $params['token'] = config::byKey("token", __CLASS__, '');
    $params['tokenExpires'] = config::byKey("tokenExpires", __CLASS__, '');
    $ret = 0;
    if($params['token'] == '') { // renew si expiré. Durée de vie: 24h 
      log::add(__CLASS__, 'debug', "NEW token");
      $ret = self::getToken($params);
    } else {
      $expDate = ($params['tokenExpires'] == '')? 0 : strtotime($params['tokenExpires']);
      if(time() >= $expDate) {
        log::add(__CLASS__, 'debug', "ReNEW token");
        $ret = self::getToken($params);
      }
    }
    return($ret); // 0 si OK pour continuer
  }

  public static function getToken(&$params) : int { 
    $token_url = "https://admindata.atmo-france.org/api/login";
    $header = array( "accept: */*",
      "Authorization: Bearer http",
      "Content-Type: application/json");
    $curl_data = '{ "username": "'.$params['username'].'", "password": "'.$params['password'].'"}';
    $curloptions = array(
      CURLOPT_URL => $token_url, CURLOPT_HTTPHEADER => $header, CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $curl_data);
    $curl = curl_init();
    log::add(__CLASS__, 'debug', "----- CURL ".__FUNCTION__ ." $token_url");
    curl_setopt_array($curl, $curloptions);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if($http_code == 200) {
      log::add(__CLASS__,'debug', "  Curl request OK.");
      curl_close($curl); unset($curl);
 /*       // Pour DEBUG verif du retour
      $hdle = fopen(__DIR__ ."/../../data/token.json", "wb");
      if($hdle !== FALSE) { fwrite($hdle, $response); fclose($hdle);
    // log::add(__CLASS__, 'debug', 'Update ' .$JsonFile .' to :' .date ("F d Y H:i:s.", filemtime($JsonFile)));
      }
*/
      // log::add(__CLASS__, 'debug', 'token ' .json_decode($response)->token);
      $dec = json_decode($response); 
      if($dec !== null && isset($dec->token)) {
        $params['token'] = $dec->token;  // Lifetime token 24h encodée en base64 dans le token
        $params['tokenExpires'] = date('c',time() + 86370 /* 3570 */);
        config::save("token", $params['token'], __CLASS__);
        config::save("tokenExpires", $params['tokenExpires'], __CLASS__);
        return(0);
      }
      else {
        log::add(__CLASS__,'warning', "  Unable to get token: " .$response);
        return(1);
      }
    } else {
      log::add(__CLASS__,'warning', "  Curl error. Code: $http_code :" .$response);
      curl_close($curl); unset($curl);
      return(1);
    }
  }

  public static function extractValueFromJsonTxt($cmdValue, $request) {
    $txtJson = str_replace(array('&quot;','&#34;'),'"',$cmdValue);
    $json =json_decode($txtJson,true);
    if($json !== null) {
      $tags = explode('>', $request);
      foreach ($tags as $tag) {
        $tag = trim($tag);
        if (isset($json[$tag])) {
          $json = $json[$tag];
        } elseif (is_numeric(intval($tag)) && isset($json[intval($tag)])) {
          $json = $json[intval($tag)];
        } elseif (is_numeric(intval($tag)) && intval($tag) < 0 && isset($json[count($json) + intval($tag)])) {
          $json = $json[count($json) + intval($tag)];
        } else {
          $json = "Request error: tag[$tag] not found in " .json_encode($json);
          break;
        }
      }
      return (is_array($json)) ? json_encode($json) : $json;
    }
    return ("*** Unable to decode JSON: " .substr($txtJson,0,20));
  }

  public static function getJsonInfo($cmd_id, $request) {
    $id = cmd::humanReadableToCmd('#' .$cmd_id .'#');
    $cmd = cmd::byId(trim(str_replace('#', '', $id)));
    if(is_object($cmd)) {
      return self::extractValueFromJsonTxt($cmd->execCmd(), $request);
    }
    else log::add(__CLASS__, 'debug', "Command not found: $cmd");
    return(null);
  }

  public function preInsert() {
    $this->setIsVisible(1);
    $this->setIsEnable(1);
    $this->setConfiguration('displayNDays',3);
    $this->setConfiguration('typeEquipment','pollens');
  }

  public function postSave() {
    $zipCode = trim($this->getConfiguration('zipCode', ''));
    if($zipCode == '') {
      log::add(__CLASS__, 'error', "[".$this->getName() ."] Le code postal n'est pas renseigné.");
      return false;
    }
    $codeZone = trim($this->getConfiguration('codeZone', ''));
    if($codeZone == '') {
      log::add(__CLASS__, 'error', "Le champ 'Communes (INSEE,EPCI)' n'est pas renseigné.");
      return false;
    }
    $commune = ''; $insee = ''; $epci = ''; $inseeEpci  = '';
    if(self::parseDataFromCodeZone($codeZone,$commune,$insee,$epci,$inseeEpci)) return false;
log::add(__CLASS__, 'debug', __FUNCTION__ ." InseeEpci $inseeEpci");

    $insees = self::getCodeInseeFromZipCode($zipCode);
    $found = 0; // Verif si code INSEE correspond à zipCode renseigné
    $posComma = strpos($inseeEpci, ',');
    foreach($insees as $code) {
      if(($posComma !== false && ($code->code.','.$code->codeEpci) == $inseeEpci) ||
         ($posComma === false && $code->code == $insee)) {
        foreach($code->codesPostaux as $codePostal) {
          if($codePostal == $zipCode) { $found = 1; break; }
        }
        if($found) break;
      }
    }
    if(!$found) {
      log::add(__CLASS__, 'error', "Equipement[" .$this->getName() ."] Le code InseeEpci [$inseeEpci] ne correspond pas au code postal [$zipCode]. L'équipement doit être reconfiguré.");
      return false;
    }
// TODO stocker les coords GPS pour lien vers le site Atmo dans le titre de l'eqpt
// $arr = self::getCodeCoordFromCodeInsee($insee);
    
    if($this->getIsEnable() == 1) {
      $this->fetchInformation(__FUNCTION__);
    }
  }

  public function postUpdate() {
    $typeEquipment = trim($this->getConfiguration('typeEquipment', ''));
    /*
    $id = "eqptStatus";
    $Cmd = $this->getCmd(null, $id);
    if(!is_object($Cmd)) {
      $Cmd = new AtmoFranceCmd();
      $Cmd->setName(__("Etat équipement", __FILE__));
      $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
      $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
      $Cmd->setType('info'); $Cmd->setSubType('numeric');
      // $Cmd->setTemplate('dashboard', __CLASS__ .'::TODO');
      // $Cmd->setTemplate('mobile', __CLASS__ .'::TODO');
      $Cmd->setOrder(300);
      $Cmd->save();
    }
     */
    $order = 1;
    $id = "date_maj"; $Cmd = $this->getCmd(null, $id);
    if(!is_object($Cmd)) {
      $Cmd = new AtmoFranceCmd();
      $Cmd->setName(__("Date MAJ", __FILE__));
      $Cmd->setIsVisible(1); $Cmd->setIsHistorized(0);
      $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
      $Cmd->setType('info'); $Cmd->setSubType('string');
      $Cmd->setOrder($order++);
      $Cmd->setTemplate('dashboard', __CLASS__ .'::lineJpty');
      $Cmd->setTemplate('mobile', __CLASS__ .'::lineJpty');
        // Si modif param existant, les récupérer avant d'ajouter getDisplay('parameters' ...
      $Cmd->setDisplay('parameters', array('type' => 'datetime'));
      $Cmd->save();
    }
    $id = "aasqa"; $Cmd = $this->getCmd(null, $id); // Associations Agréées de Surveillance de la Qualité de l’Air
    if(!is_object($Cmd)) {
      $Cmd = new AtmoFranceCmd();
      $Cmd->setName(__("AASQA", __FILE__));
      $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
      $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
      $Cmd->setType('info'); $Cmd->setSubType('string');
      $Cmd->setOrder($order++);
      $Cmd->save();
    }
    $id = "code_zone"; $Cmd = $this->getCmd(null, $id);
    if(!is_object($Cmd)) {
      $Cmd = new AtmoFranceCmd();
      $Cmd->setName(__("Code Zone INSEE EPCI", __FILE__));
      $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
      $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
      $Cmd->setType('info'); $Cmd->setSubType('string');
      $Cmd->setOrder($order++);
      $Cmd->save();
    }
    $id = "lib_zone"; $Cmd = $this->getCmd(null, $id);
    if(!is_object($Cmd)) {
      $Cmd = new AtmoFranceCmd();
      $Cmd->setName(__("Libellé Zone", __FILE__));
      $Cmd->setIsVisible(1); $Cmd->setIsHistorized(0);
      $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
      $Cmd->setType('info'); $Cmd->setSubType('string');
      $Cmd->setOrder($order++);
      $Cmd->save();
    }
    $id = "source"; $Cmd = $this->getCmd(null, $id);
    if(!is_object($Cmd)) {
      $Cmd = new AtmoFranceCmd();
      $Cmd->setName(__("Source de données", __FILE__));
      $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
      $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
      $Cmd->setType('info'); $Cmd->setSubType('string');
      $Cmd->setOrder($order++);
      $Cmd->save();
    }

    if($typeEquipment == 'pollens') {
      $id = "pollensJson";
      $Cmd = $this->getCmd(null, $id);
      if(!is_object($Cmd)) {
        $Cmd = new AtmoFranceCmd();
        $Cmd->setName(__("Pollens Json", __FILE__));
        $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
        $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
        $Cmd->setType('info'); $Cmd->setSubType('string');
        // $Cmd->setTemplate('dashboard', __CLASS__ .'::TODO');
        // $Cmd->setTemplate('mobile', __CLASS__ .'::TODO');
        $Cmd->setOrder(300);
        $Cmd->save();
      }
      for($J=0;$J<3;$J++) {
        $id = "pollensJ{$J}Json"; $Cmd = $this->getCmd(null, $id);
        if(!is_object($Cmd)) {
          $Cmd = new AtmoFranceCmd();
          $Cmd->setName(__("Pollens J$J Json", __FILE__));
          $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
          $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
          $Cmd->setType('info'); $Cmd->setSubType('string');
          // $Cmd->setTemplate('dashboard', __CLASS__ .'::TODO');
          // $Cmd->setTemplate('mobile', __CLASS__ .'::TODO');
          $Cmd->setOrder($order++);
          $Cmd->save();
        }
        $id = "date_echJ{$J}"; $Cmd = $this->getCmd(null, $id);
        if(!is_object($Cmd)) {
          $Cmd = new AtmoFranceCmd();
          $Cmd->setName(__("Date J$J", __FILE__));
          $Cmd->setIsVisible(1); $Cmd->setIsHistorized(0);
          $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
          $Cmd->setType('info'); $Cmd->setSubType('string');
          $Cmd->setTemplate('dashboard', __CLASS__ .'::lineJpty');
          $Cmd->setTemplate('mobile', __CLASS__ .'::lineJpty');
          $Cmd->setDisplay('parameters', array('type' => 'datetime'));
          $Cmd->setOrder($order++);
          $Cmd->save();
        }
        $id = "code_qualJ{$J}"; $Cmd = $this->getCmd(null, $id);
        if(!is_object($Cmd)) {
          $Cmd = new AtmoFranceCmd();
          $Cmd->setName(__("Code général J$J", __FILE__));
          $Cmd->setIsVisible(1); $Cmd->setIsHistorized(0);
          $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
          $Cmd->setType('info'); $Cmd->setSubType('numeric');
          $Cmd->setTemplate('dashboard', __CLASS__ .'::codePollens');
          $Cmd->setTemplate('mobile', __CLASS__ .'::codePollens');
          $Cmd->setOrder($order++);
          $Cmd->save();
        }
        $cmds = ["code_ambr"=>"Code Ambroisies","code_arm"=>"Code Armoise","code_aul"=>"Code Aulne","code_boul"=>"Code Bouleau","code_gram"=>"Code Graminées","code_oliv"=>"Code Olivier"];
        foreach($cmds as $cmdId => $desc) {
          $id = "{$cmdId}J{$J}"; $Cmd = $this->getCmd(null, $id);
          if(!is_object($Cmd)) {
            $Cmd = new AtmoFranceCmd();
            $Cmd->setName(__("{$desc} J$J", __FILE__));
            $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
            $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
            $Cmd->setType('info'); $Cmd->setSubType('numeric');
            $Cmd->setTemplate('dashboard', __CLASS__ .'::codePollens');
            $Cmd->setTemplate('mobile', __CLASS__ .'::codePollens');
            $Cmd->setOrder($order++);
            $Cmd->save();
          }
        }
      }
    }
    elseif($typeEquipment == 'aqi') {
      $id = "aqiJson";
      $Cmd = $this->getCmd(null, $id);
      if(!is_object($Cmd)) {
        $Cmd = new AtmoFranceCmd();
        $Cmd->setIsVisible(0);
        $Cmd->setIsHistorized(0);
        $Cmd->setName(__("Air Quality Json", __FILE__));
        $Cmd->setLogicalId($id);
        $Cmd->setEqLogic_id($this->getId());
        $Cmd->setType('info');
        $Cmd->setSubType('string');
        $Cmd->setOrder(300);
        $Cmd->save();
      }
      for($J=0;$J<3;$J++) {
        $id = "aqiJ{$J}Json"; $Cmd = $this->getCmd(null, $id);
        if(!is_object($Cmd)) {
          $Cmd = new AtmoFranceCmd();
          $Cmd->setName(__("Air Quality J$J Json", __FILE__));
          $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
          $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
          $Cmd->setType('info'); $Cmd->setSubType('string');
          // $Cmd->setTemplate('dashboard', __CLASS__ .'::TODO');
          // $Cmd->setTemplate('mobile', __CLASS__ .'::TODO');
          $Cmd->setOrder($order++);
          $Cmd->save();
        }
        $id = "date_echJ{$J}"; $Cmd = $this->getCmd(null, $id);
        if(!is_object($Cmd)) {
          $Cmd = new AtmoFranceCmd();
          $Cmd->setName(__("Date J$J", __FILE__));
          $Cmd->setIsVisible(1); $Cmd->setIsHistorized(0);
          $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
          $Cmd->setType('info'); $Cmd->setSubType('string');
          $Cmd->setTemplate('dashboard', __CLASS__ .'::lineJpty');
          $Cmd->setTemplate('mobile', __CLASS__ .'::lineJpty');
          $Cmd->setDisplay('parameters', array('type' => 'datetime'));
          $Cmd->setOrder($order++);
          $Cmd->save();
        }
        $id = "code_qualJ{$J}"; $Cmd = $this->getCmd(null, $id);
        if(!is_object($Cmd)) {
          $Cmd = new AtmoFranceCmd();
          $Cmd->setName("Indice Atmo J{$J}");
          $Cmd->setIsVisible(1); $Cmd->setIsHistorized(0);
          $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
          $Cmd->setType('info'); $Cmd->setSubType('numeric');
          $Cmd->setTemplate('dashboard', __CLASS__ .'::codeAQI');
          $Cmd->setTemplate('mobile', __CLASS__ .'::codeAQI');
          $Cmd->setOrder($order++);
          $Cmd->save();
        }
        $cmds = ["code_no2"=>"NO2","code_o3"=>"Ozone","code_so2"=>"SO2","code_pm25"=>"PM2.5","code_pm10"=>"PM10"];
        foreach($cmds as $cmdId => $desc) {
          $id = "{$cmdId}J{$J}"; $Cmd = $this->getCmd(null, $id);
          if(!is_object($Cmd)) {
            $Cmd = new AtmoFranceCmd();
            $Cmd->setName("{$desc} J{$J}");
            $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
            $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
            $Cmd->setType('info'); $Cmd->setSubType('numeric');
            $Cmd->setTemplate('dashboard', __CLASS__ .'::codeAQI');
            $Cmd->setTemplate('mobile', __CLASS__ .'::codeAQI');
            $Cmd->setOrder($order++);
            $Cmd->save();
          }
        }
      }
    }

    $refresh = $this->getCmd(null, 'refresh');
    if(!is_object($refresh)) {
      $refresh = new AtmoFranceCmd();
      $refresh->setIsVisible(1);
      $refresh->setName(__('Rafraichir', __FILE__));
    }
    $refresh->setEqLogic_id($this->getId());
    $refresh->setLogicalId('refresh');
    $refresh->setType('action');
    $refresh->setSubType('other');
    $refresh->setOrder(0);
    $refresh->save();
log::add(__CLASS__, 'debug', "End of " .__FUNCTION__ ." " .$this->getName());
  }
  
  /** @return array|null */
  public function getResource($params, $api) {
    $header = array("accept: */*", "Authorization: Bearer {$params['token']}");
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $api, CURLOPT_HTTPHEADER => $header,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_RETURNTRANSFER => true));
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if($http_code == 200) {
      log::add(__CLASS__, 'debug', "  ----- CURL OK ".__FUNCTION__ ." $api");
      curl_close($curl); unset($curl);
      $dec = json_decode($response,true);
      if($dec === null) {
        log::add(__CLASS__, 'warning', "  Json_decode error : " .json_last_error_msg() ." " .substr($resource,0,50));
        return null;
      }
      if(isset($dec['features'])) {
        $nbF = count($dec['features']);
        if($nbF) log::add(__CLASS__, 'debug', "    " .$nbF ." features found");
        else log::add(__CLASS__, 'error', "    Pas de données pour cette commune.");
        return $dec;
      }
      else  {
        log::add(__CLASS__, 'warning', "    -> No features in response " .$response);
        return null;
      }
    } else {
      log::add(__CLASS__,'warning', "  Curl error. Code: $http_code :" .$response);
      curl_close($curl); unset($curl);
      return null;
    }
  }

  /* API pour recup code Insee et Epci pour la config de l'équipement
  https://geo.api.gouv.fr/communes?codePostal=54200&fields=code,nom,codeEpci&format=json&geometry=centre
  https://geo.api.gouv.fr/communes?codePostal=54200&fields=nom,code,codesPostaux,siren,codeEpci,codeDepartement,codeRegion,population&format=json&geometry=centre
  SWAGGER : https://www.data.gouv.fr/fr/dataservices/api-decoupage-administratif-api-geo/
   */
  public static function getCodeInseeFromZipCode($zipCode = '') : array {
    if(trim($zipCode) == '') return([]);
    $api = "https://geo.api.gouv.fr/communes?codePostal=$zipCode&fields=nom,centre,code,codesPostaux,siren,codeEpci,codeDepartement,codeRegion,population&format=json&geometry=centre";
    $response = @file_get_contents($api);
    if($response === false) {
      log::add(__CLASS__, 'warning', "File_get_contents failed for zipCode:$zipCode");
      return [];
    }
    else {
      $dec = json_decode($response);
      if($dec === null) {
        log::add(__CLASS__, 'warning', "Failed to json_decode API call [$response]");
        return [];
      }
      else return($dec);
    }
  }
  public static function getCodeCoordFromCodeInsee($codeInsee = '') {
    if(trim($codeInsee) == '') return([]);
    $api = "https://geo.api.gouv.fr/communes/$codeInsee?fields=nom,centre&format=json&geometry=centre";
    $response = @file_get_contents($api);
    if($response === false) {
      log::add(__CLASS__, 'warning', "File_get_contents failed for codeInsee:$codeInsee");
      return[];
    }
    else {
      $dec = json_decode($response);
      if($dec === null) {
        log::add(__CLASS__, 'warning', "Failed to json_decode API call [$response]");
        return[];
      }
      else {
        $lat = $dec->centre->coordinates[1];
        $lon = $dec->centre->coordinates[0];
        $nom = $dec->nom;
        // log::add(__CLASS__, 'debug', "$nom $lat $lon");
        return ["latitude"=> $lat,"longitude" => $lon, "nom" => $nom];
      }
    }
  }

  public function processDataFromJson($typeEquipment, $dec) {
    $changed = false;
    if($dec !== null && isset($dec['features'])) {
      for($J=0;$J<3;$J++) {
    log::add(__CLASS__, 'debug', __FUNCTION__ ." J$J {$typeEquipment} [" .$this->getName());
        $propFound = 0;
        $dateJ = date('Y-m-d',strtotime("+$J days"));
        foreach($dec['features'] as $feature) {
          $properties = $feature['properties'];
          $date_ech = $properties['date_ech']; // mesures de ce jour.
          if($date_ech == $dateJ) {
            $propFound = 1;
            log::add(__CLASS__, 'debug', "    Processing $date_ech Day: $J");
            if($J == 0) {
              $changed = $this->checkAndUpdateCmd("date_maj",$properties['date_maj']) || $changed;
              $changed = $this->checkAndUpdateCmd("aasqa",$properties['aasqa']) || $changed;
              $changed = $this->checkAndUpdateCmd("code_zone",$properties['code_zone']) || $changed;
              $changed = $this->checkAndUpdateCmd("lib_zone",$properties['lib_zone']) || $changed;
              $changed = $this->checkAndUpdateCmd("source",$properties['source']) || $changed;
            }
            $changed = $this->checkAndUpdateCmd("date_echJ$J",$properties['date_ech']) || $changed;
            $changed = $this->checkAndUpdateCmd("code_qualJ$J",$properties['code_qual']) || $changed;
            if($typeEquipment == 'pollens') {
              $changed = $this->checkAndUpdateCmd("pollensJ{$J}Json", str_replace('"','&#34;',json_encode($properties))) || $changed;
              // $changed = $this->checkAndUpdateCmd("lib_qualJ$J",$properties['lib_qual']) || $changed;
              $changed = $this->checkAndUpdateCmd("code_ambrJ$J",$properties['code_ambr']) || $changed;
              $changed = $this->checkAndUpdateCmd("code_armJ$J",$properties['code_arm']) || $changed;
              $changed = $this->checkAndUpdateCmd("code_aulJ$J",$properties['code_aul']) || $changed;
              $changed = $this->checkAndUpdateCmd("code_boulJ$J",$properties['code_boul']) || $changed;
              $changed = $this->checkAndUpdateCmd("code_gramJ$J",$properties['code_gram']) || $changed;
              $changed = $this->checkAndUpdateCmd("code_olivJ$J",$properties['code_oliv']) || $changed;
            }
            else if($typeEquipment == 'aqi') {
              $changed = $this->checkAndUpdateCmd("aqiJ{$J}Json", str_replace('"','&#34;',json_encode($properties))) || $changed;
              $changed = $this->checkAndUpdateCmd("code_no2J$J",$properties['code_no2']) || $changed;
              $changed = $this->checkAndUpdateCmd("code_o3J$J",$properties['code_o3']) || $changed;
              $changed = $this->checkAndUpdateCmd("code_so2J$J",$properties['code_so2']) || $changed;
              $changed = $this->checkAndUpdateCmd("code_pm25J$J",$properties['code_pm25']) || $changed;
              $changed = $this->checkAndUpdateCmd("code_pm10J$J",$properties['code_pm10']) || $changed;
            }
            break;
          }
        }
        if(!$propFound) {
          if($J == 0) {
            $changed = $this->checkAndUpdateCmd("date_maj",'') || $changed;
            $changed = $this->checkAndUpdateCmd("aasqa",'') || $changed;
            $changed = $this->checkAndUpdateCmd("code_zone",'') || $changed;
            $changed = $this->checkAndUpdateCmd("lib_zone",'') || $changed;
            $changed = $this->checkAndUpdateCmd("source",'') || $changed;
          }
log::add(__CLASS__, 'debug', "    Purging data Day: $J");
          $changed = $this->checkAndUpdateCmd("date_echJ$J",$dateJ) || $changed;
          $changed = $this->checkAndUpdateCmd("code_qualJ$J", 0) || $changed;
          if($typeEquipment == 'pollens') {
            $changed = $this->checkAndUpdateCmd("pollensJ{$J}Json", "[]") || $changed;
            $changed = $this->checkAndUpdateCmd("code_ambrJ$J", 0) || $changed;
            $changed = $this->checkAndUpdateCmd("code_armJ$J", 0) || $changed;
            $changed = $this->checkAndUpdateCmd("code_aulJ$J", 0) || $changed;
            $changed = $this->checkAndUpdateCmd("code_boulJ$J", 0) || $changed;
            $changed = $this->checkAndUpdateCmd("code_gramJ$J", 0) || $changed;
            $changed = $this->checkAndUpdateCmd("code_olivJ$J", 0) || $changed;
          }
          else if($typeEquipment == 'aqi') {
            $changed = $this->checkAndUpdateCmd("aqiJ{$J}Json", "[]") || $changed;
            $changed = $this->checkAndUpdateCmd("code_no2J$J", 0) || $changed;
            $changed = $this->checkAndUpdateCmd("code_o3J$J", 0) || $changed;
            $changed = $this->checkAndUpdateCmd("code_so2J$J", 0) || $changed;
            $changed = $this->checkAndUpdateCmd("code_pm25J$J", 0) || $changed;
            $changed = $this->checkAndUpdateCmd("code_pm10J$J", 0) || $changed;
          }
        }
      }
      return($changed);
    }
  }

  public static function isValidEpciFormat($epci) {
    // Doit être exactement 9 chiffres ET commencer par 1, 2 ou 24
    return preg_match('/^((1|2)\d{8}|24\d{7})$/', $epci);
  }
  public static function isValidInseeFormat($insee) {
    // Format : 2 chiffres ou 2A/2B + 3 chiffres
    return preg_match('/^(2A|2B|\d{2})\d{3}$/', $insee);
  }

  public static function parseDataFromCodeZone($codeZone,&$commune,&$insee,&$epci,&$inseeEpci) {
    $openPar = strrpos($codeZone,'('); $closePar = strrpos($codeZone,')');
    if($openPar !== false && $closePar !== false) {
      $inseeEpci = substr($codeZone, $openPar+1,$closePar - $openPar - 1);
      $commune = trim(substr($codeZone,0,$openPar-1));
    }
    else $inseeEpci = $codeZone;
    $arrCode = explode(',',$inseeEpci); $nb= count($arrCode);
    if($nb == 1) $insee = trim($arrCode[0]);
    else if($nb == 2) {
      $insee = trim($arrCode[0]);
      $epci = trim($arrCode[1]); if($epci == '----') $epci = '';
    }
    else {
      log::add(__CLASS__, 'warning', "Format du champ 'Commune (INSEE,EPCI)' incorrect.");
      return 1;
    }
      // Code Insee : 2 chiffres OU "2A"/"2B" + 3 chiffres
    if(!self::isValidInseeFormat($insee)) {
      log::add(__CLASS__, 'error', "Format du code INSEE [$insee] incorrect: 2 chiffres OU \"2A\"/\"2B\" + 3 chiffres.");
      return 1;
    }
    if($epci != '') {
      if(!self::isValidEpciFormat($epci)) {
        log::add(__CLASS__, 'warning', "Format du code EPCI [$epci] incorrect: 9 chiffres.");
        return 1;
      }
    }

    log::add(__CLASS__, 'debug', "  Commune: $commune Insee: $insee Epci: $epci InseeEpci: $inseeEpci");
    return 0;
  }

  public function requestData2API($typeEquipment,$params, $insee): ?array {
log::add(__CLASS__, 'debug', __FUNCTION__ ." [" .$this->getName() ."]");
    $dateDeb = date('Y-m-d',strtotime("today"));
    $dateFin = date('Y-m-d',strtotime("+3 days"));
    if($typeEquipment == 'pollens')
      $url = "https://admindata.atmo-france.org/api/v2/data/indices/{$typeEquipment}?format=geojson&date=$dateFin&date_historique={$dateDeb}&code_zone={$insee}&with_geom=false";
    elseif($typeEquipment == 'aqi')
      $url = "https://admindata.atmo-france.org/api/v2/data/indices/atmo?format=geojson&date=$dateFin&date_historique={$dateDeb}&code_zone={$insee}";
    log::add(__CLASS__, 'debug', "  URL: $url");
    $jsonDec = $this->getResource($params, $url);
    $loglevel = log::convertLogLevel(log::getLogLevel(__CLASS__));
    if($loglevel == 'debug') {
      $file = __DIR__ ."/../../data/$typeEquipment-$insee.json";
      $hdle = fopen($file, "wb");
      if($hdle !== FALSE) {
        if($jsonDec) fwrite($hdle, json_encode($jsonDec));
        else fwrite($hdle, "Error in request result");
        fclose($hdle);
      }
      else log::add(__CLASS__, 'debug', "Unable to write file [$file]");
    }
    return $jsonDec;
  }

  public function fetchInformation($fctCaller) {
log::add(__CLASS__, 'debug', "---------------------");
log::add(__CLASS__, 'debug', __FUNCTION__ ." [" .$this->getName() ."] Called by $fctCaller");
    $changed = false;
    $codeZone = trim($this->getConfiguration('codeZone', ''));
    if($codeZone == '') {
      log::add(__CLASS__, 'warning', 'Code INSEE non renseigné');
      return false;
    }

    $commune = ''; $insee = ''; $epci = ''; $inseeEpci = '';
    if(self::parseDataFromCodeZone($codeZone,$commune,$insee,$epci,$inseeEpci)) return;

    $typeEquipment = trim($this->getConfiguration('typeEquipment', ''));
      // Verif source // TODO
    $lastCallData = $this->getConfiguration('lastCallData', '');
    if(($fctCaller == 'cron' || $fctCaller == 'postSave')) { // appel cron/postSave et sources identiques
log::add(__CLASS__, 'debug', "  Caller $fctCaller LastCallerData $lastCallData Now $inseeEpci");
      if($lastCallData == $inseeEpci) { // sources identiques. Verif age de la commande
        $now = time();
        if($typeEquipment == 'pollens' || $typeEquipment == 'aqi') {
          $Cmd = $this->getCmd(null, 'date_maj');
          if(is_object($Cmd)) $dateMAJ = strtotime($Cmd->execCmd());
          else $dateMAJ = 0;
          if(($dateMAJ+86400) >= $now) {
            $this->updateDataFromJsonCmdValue($typeEquipment);
    log::add(__CLASS__, 'debug', "  Aborting update. Same request and data age <24h: ". gmdate('H:i:s',$now-$dateMAJ));
            return false;
          }
          else {
    log::add(__CLASS__, 'debug', "  Updating data. Date older than 24 hours. ". gmdate('H:i:s',$now-$dateMAJ));
          }
        }
      }
    }
    log::add(__CLASS__, 'debug', " Fetching $typeEquipment data from ATMO FRANCE");
    $params = array();
    $cr = $this->initParams($params);
    if($cr != 0) throw new Exception("Pb initParams: $cr");
    if($typeEquipment == 'pollens' || $typeEquipment == 'aqi') {
      $jsonDec = $this->requestData2API($typeEquipment, $params, $insee);
      if($jsonDec !== null) {
        $this->setConfiguration('lastCallData', $inseeEpci); // memo
        $this->save(true);
        $jsonTxt = str_replace('"','&#34;',json_encode($jsonDec));
        $changed = $this->checkAndUpdateCmd("{$typeEquipment}Json", $jsonTxt) || $changed;
        $changed = $this->processDataFromJson($typeEquipment, $jsonDec) || $changed;
      }
      // else // TODO nettoyage des commandes ?????????? 
    }
    else {
      log::add(__CLASS__, 'debug', "  Unknown equipment type: [$typeEquipment] Eqpt[" .$this->getName() ."]");
    }
    return $changed;
  }
}

class AtmoFranceCmd extends cmd {
  /* Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
    public function dontRemoveCmd() {
    return true;
    }
   */

  public function execute($_options = array()) {
    $eqLogic = $this->getEqLogic();
    if($this->getLogicalId() == 'refresh') {
      $eqLogic->fetchInformation('RefreshCmd');
    }
  }
}
