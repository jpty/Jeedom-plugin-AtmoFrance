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

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class AtmoFrance extends eqLogic {
  public static $_widgetPossibility = array('custom' => true, 'custom::layout' => false);

  public static function cronHourly() {
    foreach (eqLogic::byType(__CLASS__, true) as $eqLogic) {
      $typeEquipment = trim($eqLogic->getConfiguration('typeEquipment', ''));
      if($typeEquipment == 'pollens') {
        $changed = false;
        $Cmd = $eqLogic->getCmd(null, 'pollensJson');
        if(is_object($Cmd)) {
          $cmdValue = $Cmd->execCmd();
          $cmdValue = str_replace('&#34;', '"', $cmdValue);
          $dec = json_decode($cmdValue,true);
          if($dec != null) {
            $changed = $eqLogic->updatePollensDataFromJson($dec) || $changed;
            if($changed) $eqLogic->refreshWidget();
          }
        }
      }
    }
  }
  public static function cron() {
    if(date('H') > 15) { // Apres 15h
      $minute = date('i');
      $minuteRecup = config::byKey('minuteRecup', __CLASS__, -1);
      if($minuteRecup == -1) {
        $minuteRecup = rand(1,59);
        log::add(__CLASS__, 'debug', "Minute recup: $minuteRecup");
        config::save('minuteRecup', $minuteRecup, __CLASS__);
      }
      if($minute == $minuteRecup) {
        $now = time();
        foreach (eqLogic::byType(__CLASS__, true) as $eqLogic) {
          $typeEquipment = trim($eqLogic->getConfiguration('typeEquipment', ''));
          if($typeEquipment == 'pollens') {
            $Cmd = $eqLogic->getCmd(null, 'date_maj');
            if(is_object($Cmd)) $dateMAJ = strtotime($Cmd->execCmd());
            else $dateMAJ = 0;
            if(($dateMAJ+86400) < $now) {
        log::add(__CLASS__, 'debug', "Start recup data ATMO FRANCE");
              $changed = $eqLogic->getInformation();
              if($changed) $eqLogic->refreshWidget();
            }
            else log::add(__CLASS__, 'debug', "Age des Data: ". gmdate('H:i:s',$now-$dateMAJ));
          }
        }
      }
    }
  }

  public static function initParams(&$params) {
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
    if($params['token'] == '') { // TODO renew si expiré. Durée de vie une heure, 24h permanent ??? 
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

  public static function getToken(&$params) { 
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
        $params['token'] = $dec->token;  // TODO verif lifetime
        $params['tokenExpires'] = date('c',time() + 86370 /* 3570 */); // 24h, 1h ou permanent
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

      /*
  public function preUpdate() {
    $zipCode = trim($this->getConfiguration('zipCode'));
    if($zipCode == '') {
      throw new Exception(__("Le code postal doit être renseigné.", __FILE__));
    }
    $insees = self::getCodeInseeFromZipCode($zipCode);
    $codeZone = trim($this->getConfiguration('codeZone'));
    if($codeZone == '') {
      throw new Exception(__("Le code INSEE de la commune doit être renseigné.", __FILE__));
    }
    else {
      $found = 0; // Verif si code INSEE correspond à zipCode renseigné
      foreach($insees as $insee) {
        if(($insee->code.','.$insee->codeEpci ) == $codeZone) {
          foreach($insee->codesPostaux as $codePostal) {
            if($codePostal == $zipCode) { $found = 1; break; }
          }
          if($found) break;
        }
      }
      if(!$found) {
        throw new Exception(__("Le code INSEE ne correspond pas au code postal.", __FILE__));
      }
    }
  }
*/

  public function preInsert() {
    $this->setIsVisible(1);
    $this->setIsEnable(1);
    $this->setConfiguration('displayNDays',3);
    $this->setConfiguration('typeEquipment','pollens');
  }

  public function postUpdate() {
    $typeEquipment = trim($this->getConfiguration('typeEquipment', ''));
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
      $order = 1;
      $id = "date_maj"; $Cmd = $this->getCmd(null, $id);
      if(!is_object($Cmd)) {
        $Cmd = new AtmoFranceCmd();
        $Cmd->setName(__("Date MAJ", __FILE__));
        $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
        $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
        $Cmd->setType('info'); $Cmd->setSubType('string');
        $Cmd->setOrder($order++);
        $Cmd->setTemplate('dashboard', __CLASS__ .'::lineJpty');
        $Cmd->setTemplate('mobile', __CLASS__ .'::lineJpty');
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
        $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
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
        $id = "code_ambrJ{$J}"; $Cmd = $this->getCmd(null, $id);
        if(!is_object($Cmd)) {
          $Cmd = new AtmoFranceCmd();
          $Cmd->setName(__("Code Ambroisie J$J", __FILE__));
          $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
          $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
          $Cmd->setType('info'); $Cmd->setSubType('numeric');
          $Cmd->setTemplate('dashboard', __CLASS__ .'::codePollens');
          $Cmd->setTemplate('mobile', __CLASS__ .'::codePollens');
          $Cmd->setOrder($order++);
          $Cmd->save();
        }
        $id = "code_armJ{$J}"; $Cmd = $this->getCmd(null, $id);
        if(!is_object($Cmd)) {
          $Cmd = new AtmoFranceCmd();
          $Cmd->setName(__("Code Armoise J$J", __FILE__));
          $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
          $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
          $Cmd->setType('info'); $Cmd->setSubType('numeric');
          // showNameOndashboard":"0","showNameOnmobile
          $Cmd->setTemplate('dashboard', __CLASS__ .'::codePollens');
          $Cmd->setTemplate('mobile', __CLASS__ .'::codePollens');
          $Cmd->setOrder($order++);
          $Cmd->save();
        }
        $id = "code_aulJ{$J}"; $Cmd = $this->getCmd(null, $id);
        if(!is_object($Cmd)) {
          $Cmd = new AtmoFranceCmd();
          $Cmd->setName(__("Code Aulne J$J", __FILE__));
          $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
          $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
          $Cmd->setType('info'); $Cmd->setSubType('numeric');
          $Cmd->setTemplate('dashboard', __CLASS__ .'::codePollens');
          $Cmd->setTemplate('mobile', __CLASS__ .'::codePollens');
          $Cmd->setOrder($order++);
          $Cmd->save();
        }
        $id = "code_boulJ{$J}"; $Cmd = $this->getCmd(null, $id);
        if(!is_object($Cmd)) {
          $Cmd = new AtmoFranceCmd();
          $Cmd->setName(__("Code Bouleau J$J", __FILE__));
          $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
          $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
          $Cmd->setType('info'); $Cmd->setSubType('numeric');
          $Cmd->setTemplate('dashboard', __CLASS__ .'::codePollens');
          $Cmd->setTemplate('mobile', __CLASS__ .'::codePollens');
          $Cmd->setOrder($order++);
          $Cmd->save();
        }
        $id = "code_gramJ{$J}"; $Cmd = $this->getCmd(null, $id);
        if(!is_object($Cmd)) {
          $Cmd = new AtmoFranceCmd();
          $Cmd->setName(__("Code Graminées J$J", __FILE__));
          $Cmd->setIsVisible(0); $Cmd->setIsHistorized(0);
          $Cmd->setLogicalId($id); $Cmd->setEqLogic_id($this->getId());
          $Cmd->setType('info'); $Cmd->setSubType('numeric');
          $Cmd->setTemplate('dashboard', __CLASS__ .'::codePollens');
          $Cmd->setTemplate('mobile', __CLASS__ .'::codePollens');
          $Cmd->setOrder($order++);
          $Cmd->save();
        }
        $id = "code_olivJ{$J}"; $Cmd = $this->getCmd(null, $id);
        if(!is_object($Cmd)) {
          $Cmd = new AtmoFranceCmd();
          $Cmd->setName(__("Code Olivier J$J", __FILE__));
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

    $this->getInformation();
  }
  
  public function getResource($params, $api) {
    $header = array("accept: */*", "Authorization: Bearer {$params['token']}");
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $api, CURLOPT_HTTPHEADER => $header,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_RETURNTRANSFER => true));
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if($http_code == 200) {
      log::add(__CLASS__, 'debug', "----- CURL OK ".__FUNCTION__ ." $api");
      curl_close($curl); unset($curl);
      log::add(__CLASS__, 'debug', "[" .$response ."]");
      return ($response);
    } else {
      log::add(__CLASS__,'warning', "  Curl error. Code: $http_code :" .$response);
      curl_close($curl); unset($curl);
      return("[]");
    }
  }

  public static function getCodeInseeFromZipCode($zipCode = '') {
    if(trim($zipCode) == '') return([]);
    $api = "https://geo.api.gouv.fr/communes?codePostal=$zipCode&fields=nom,code,codesPostaux,siren,codeEpci,codeDepartement,codeRegion,population&format=json&geometry=centre";
    $header = array("accept: application/json");
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $api, CURLOPT_HTTPHEADER => $header,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_RETURNTRANSFER => true));
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if($http_code == 200) {
      log::add(__CLASS__, 'debug', "----- CURL OK ".__FUNCTION__ ." $api");
      curl_close($curl); unset($curl);
      log::add(__CLASS__, 'debug', "[" .$response ."]");
      return (json_decode($response));
    } else {
      log::add(__CLASS__,'warning', "  Curl error. Code: $http_code :" .$response);
      curl_close($curl); unset($curl);
      return([]);
    }
  }
  /* code Insee et Epci pour la config de l'équipement
  https://geo.api.gouv.fr/communes?codePostal=54200&fields=code,nom,codeEpci&format=json&geometry=centre
  https://geo.api.gouv.fr/communes?codePostal=54200&fields=nom,code,codesPostaux,siren,codeEpci,codeDepartement,codeRegion,population&format=json&geometry=centre
  SWAGGER : https://www.data.gouv.fr/fr/dataservices/api-decoupage-administratif-api-geo/
   */
  public function updatePollensDataFromJson($dec) {
    $changed = false;
    $dateJ = [];
    for($J=0;$J<3;$J++) $dateJ[$J] = date('Y-m-d',strtotime("+$J days"));
    foreach($dec['features'] as $feature) {
      $properties = $feature['properties'];
      $date_ech = $properties['date_ech']; // mesures de ce jour.
      if($date_ech == $dateJ[0]) $J = 0;
      elseif($date_ech == $dateJ[1]) $J = 1;
      elseif($date_ech == $dateJ[2]) $J = 2;
      else continue;
      if($J == 0) {
        $changed = $this->checkAndUpdateCmd("date_maj",$properties['date_maj']) || $changed;
        $changed = $this->checkAndUpdateCmd("aasqa",$properties['aasqa']) || $changed;
        $changed = $this->checkAndUpdateCmd("code_zone",$properties['code_zone']) || $changed;
        $changed = $this->checkAndUpdateCmd("lib_zone",$properties['lib_zone']) || $changed;
        $changed = $this->checkAndUpdateCmd("source",$properties['source']) || $changed;
      }
      $changed = $this->checkAndUpdateCmd("pollensJ{$J}Json", str_replace('"','&#34;',json_encode($properties))) || $changed;
      $changed = $this->checkAndUpdateCmd("date_echJ$J",$properties['date_ech']) || $changed;
      $changed = $this->checkAndUpdateCmd("code_qualJ$J",$properties['code_qual']) || $changed;
      // $changed = $this->checkAndUpdateCmd("lib_qualJ$J",$properties['lib_qual']) || $changed;
      $changed = $this->checkAndUpdateCmd("code_ambrJ$J",$properties['code_ambr']) || $changed;
      $changed = $this->checkAndUpdateCmd("code_armJ$J",$properties['code_arm']) || $changed;
      $changed = $this->checkAndUpdateCmd("code_aulJ$J",$properties['code_aul']) || $changed;
      $changed = $this->checkAndUpdateCmd("code_boulJ$J",$properties['code_boul']) || $changed;
      $changed = $this->checkAndUpdateCmd("code_gramJ$J",$properties['code_gram']) || $changed;
      $changed = $this->checkAndUpdateCmd("code_olivJ$J",$properties['code_oliv']) || $changed;
    }
    for($J=$J+1;$J<3;$J++) {
      $changed = $this->checkAndUpdateCmd("pollensJ{$J}Json", "[]") || $changed;
      $changed = $this->checkAndUpdateCmd("date_echJ$J",$dateJ[$J]) || $changed;
      $changed = $this->checkAndUpdateCmd("code_qualJ$J",0) || $changed;
      $changed = $this->checkAndUpdateCmd("code_ambrJ$J",0) || $changed;
      $changed = $this->checkAndUpdateCmd("code_armJ$J",0) || $changed;
      $changed = $this->checkAndUpdateCmd("code_aulJ$J",0) || $changed;
      $changed = $this->checkAndUpdateCmd("code_boulJ$J",0) || $changed;
      $changed = $this->checkAndUpdateCmd("code_gramJ$J",0) || $changed;
      $changed = $this->checkAndUpdateCmd("code_olivJ$J",0) || $changed;
    }
    return($changed);
  }

  public function getInformation($params = null) {
    $changed = false;
    $zipCode = trim($this->getConfiguration('zipCode', ''));
    if($zipCode == '') {
      log::add(__CLASS__, 'warning', 'Code postal non renseigné');
      return false;
    }
    $insees = self::getCodeInseeFromZipCode($zipCode);
    $codeZone = trim($this->getConfiguration('codeZone', ''));
    if($codeZone == '') {
      log::add(__CLASS__, 'warning', 'Code INSEE non renseigné');
      return false;
    }
    else {
      $found = 0; // Verif si code INSEE correspond à zipCode renseigné
      foreach($insees as $insee) {
        if(($insee->code.','.$insee->codeEpci ) == $codeZone) {
          foreach($insee->codesPostaux as $codePostal) {
            if($codePostal == $zipCode) { $found = 1; break; }
          }
          if($found) break;
        }
      }
      if(!$found) {
        log::add(__CLASS__, 'error', "Equipement[{$this->getName()}] Le code INSEE [$codeZone] ne correspond pas au code postal [$zipCode]. L'équipement doit être reconfiguré.");
        return false;
      }
    }

    if($params === null) {
      $params = array();
      $cr = $this->initParams($params);
      if($cr != 0) throw new Exception("Pb initParams: $cr");
    }
    $typeEquipment = trim($this->getConfiguration('typeEquipment', ''));
    if($typeEquipment == 'pollens') {
      $dateDeb = date('Y-m-d',strtotime("today"));
      $dateFin = date('Y-m-d',strtotime("+3 days"));
      $url = "https://admindata.atmo-france.org/api/v2/data/indices/pollens?format=geojson&date=$dateFin&date_historique={$dateDeb}&code_zone={$codeZone}&with_geom=false";
      $resource = $this->getResource($params, $url);
      /*
          // TODO commenter dev only
        $hdle = fopen(__DIR__ ."/../../data/pollens-$codeZone.json", "wb");
        if($hdle !== FALSE) { fwrite($hdle, $resource); fclose($hdle); }
       */
      if($resource != "") {
        $dec = json_decode($resource,true);
        if($dec === null) {
          log::add(__CLASS__, 'debug', __FILE__ ." " .__LINE__ ." Json_decode error : " .json_last_error_msg() ." " .substr($resource,0,50));
          return false;
        }
        else {
          $changed = $this->checkAndUpdateCmd('pollensJson', str_replace('"','&#34;',$resource)) || $changed;
          $changed = $this->updatePollensDataFromJson($dec) || $changed;
        }
      }
    }
    elseif($typeEquipment == 'aqi') {
      $url = "https://admindata.atmo-france.org/api/v2/data/indices/atmo?format=geojson&date=$dateFin&date_historique={$dateDeb}&code_zone={$codeZone}&with_geom=false";
      $resource = $this->getResource($params, $url);
          // TODO commenter dev only
        $hdle = fopen(__DIR__ ."/../../data/aqi-$codeZone.json", "wb");
        if($hdle !== FALSE) { fwrite($hdle, $resource); fclose($hdle); }
      if($resource != "") {
        $dec = json_decode($resource,true);
        if($dec === null) {
          log::add(__CLASS__, 'debug', __FILE__ ." " .__LINE__ ." Json_decode error : " .json_last_error_msg() ." " .substr($resource,0,50));
          return $changed;
        }
        else {
          $changed = $this->checkAndUpdateCmd('aqiJson', str_replace('"','&#34;',$resource)) || $changed;
        }
      }
    }
    else {
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
      $eqLogic->getInformation();
    }
  }
}
