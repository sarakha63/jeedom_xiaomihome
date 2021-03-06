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
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class xiaomihome extends eqLogic {

    public function yeeAction($ip, $request, $option) {
        //$cmd = 'python ' . realpath(dirname(__FILE__)) . '/../../resources/yeecli.py ' . $ip . ' ' . $request . ' ' . $option;
        $cmd = 'yeecli --ip=' . $ip . ' ' . $request . ' ' . $option;
        log::add('xiaomihome', 'debug', $cmd);
        exec($cmd);
    }

    public function aquaraAction($request) {
        //{"cmd":"write","model":"ctrl_neutral1","sid":"158d0000123456","short_id":4343,"data":"{\"channel_0\":\"on\",\"key\":\"3EB43E37C20AFF4C5872CC0D04D81314\"}" }
        $cmd = '{"cmd":"write","model":"' . $this->getConfiguration('model') . '","sid":"' . $this->getConfiguration('sid') . '","short_id":"' . $this->getConfiguration('short_id') . '","token":"' . config::byKey('token','xiaomihome') . '","data":"{' . $request . '}';
        $gateway = $this->getConfiguration('gateway');
        $sock = socket_create(AF_INET, SOCK_DGRAM, 0);
        // Actually write the data and send it off
        if( ! socket_sendto($sock, $cmd , strlen($cmd) , 0 , $gateway , '9898')) {
          $errorcode = socket_last_error();
          $errormsg = socket_strerror($errorcode);
          die("Could not send data: [$errorcode] $errormsg \n");
          log::add('xiaomihome', 'error', 'Envoi impossible :  ' . $errorcode . ', avec message ' . $errormsg);
        } else {
          log::add('xiaomihome', 'debug', 'Envoi ok ' . $cmd);
        }
        socket_close($sock);
    }

    public function yeeStatus($ip) {
        $cmd = 'yee --ip=' . $ip . ' status';
        exec($cmd, $output, $return_var);

        $power = explode(': ',$output[5]);
        $color_mode = explode(': ',$output[3]);
        $bright = explode(': ',$output[9]);
        $rgb = explode(': ',$output[8]);
        $hue = explode(': ',$output[2]);
        $saturation = explode(': ',$output[13]);
        $color_temp = explode(': ',$output[12]);

        $power = ($power[1] == 'off')? 0:1;
        $this->checkAndUpdateCmd('status', $power);
        $this->checkAndUpdateCmd('brightness', $bright[1]);
        if ($this->getConfiguration('model') != 'mono') {
            $this->checkAndUpdateCmd('color_mode', $color_mode[1]);
            $this->checkAndUpdateCmd('rgb', '#' . str_pad(dechex($rgb[1]), 6, "0", STR_PAD_LEFT));
            $this->checkAndUpdateCmd('hsv', $hue[1]);
            $this->checkAndUpdateCmd('saturation', $saturation[1]);
            $this->checkAndUpdateCmd('temperature', $color_temp[1]);
        }
        //log::add('xiaomihome', 'debug', $power . ' ' . $color_mode[1] . ' ' . $bright[1] . ' ' . '#' . str_pad(dechex($rgb[1]), 6, "0", STR_PAD_LEFT) . ' ' . $hue[1] . ' ' . $saturation[1] . ' ' . $color_temp[1]);
    }

    public function receiveYeelight($ip, $id, $model, $fw_ver, $power, $color_mode, $rgb, $bright, $hue, $saturation, $color_temp) {
        $xiaomihome = self::byLogicalId($id, 'xiaomihome');
        if (!is_object($xiaomihome)) {
            $xiaomihome = new xiaomihome();
            $xiaomihome->setEqType_name('xiaomihome');
            $xiaomihome->setLogicalId($id);
            $xiaomihome->setName($model . ' ' . $id);
            $xiaomihome->setConfiguration('sid', $id);
            $xiaomihome->setIsEnable(1);
            $xiaomihome->setIsVisible(1);
            event::add('xiaomihome::includeDevice',
            array(
                'state' => 1
            )
        );
    }
    $xiaomihome->setConfiguration('model',$model);
    $xiaomihome->setConfiguration('short_id',$fw_ver);
    $xiaomihome->setConfiguration('gateway',$ip);
    $xiaomihome->setConfiguration('type','yeelight');
    $xiaomihome->setConfiguration('lastCommunication',date('Y-m-d H:i:s'));
    $xiaomihome->save();

    $xiaomihome->checkCmdOk('status', 'Statut', 'info', 'binary', '0', '0', '0', 'light', '0');
    $power = ($power == 'off')? 0:1;
    $xiaomihome->checkAndUpdateCmd('status', $power);
    $xiaomihome->checkCmdOk('toggle', 'Toggle', 'action', 'other', 'toggle', '0', '0', '0', '<i class="fa fa-toggle-on"></i>');
    $xiaomihome->checkCmdOk('refresh', 'Raffraichir', 'action', 'other', 'refresh', '0', '0', '0', '<i class="fa fa-refresh"></i>');
    $xiaomihome->checkCmdOk('on', 'Allumer', 'action', 'other', 'turn on', 'status', '0', 'light', '<i class="fa fa-sun-o"></i>');
    $xiaomihome->checkCmdOk('off', 'Eteindre', 'action', 'other', 'turn off', 'status', '0', 'light', '<i class="fa fa-power-off"></i>');
    /*$xiaomihome->checkCmdOk('cron', 'Extinction programmée', 'action', 'slider', 'cron', '0', '0', '0', '<i class="fa fa-power-off"></i>');
    $xiaomihome->checkCmdOk('flow', 'Enchainement', 'action', 'message', 'flow', '0', '0', '0', '0');*/

    //brightness 0-100
    $xiaomihome->checkCmdOk('brightness', 'Luminosité', 'info', 'numeric', '0', '0', '0', 'light', '0');
    $xiaomihome->checkAndUpdateCmd('brightness', $bright);
    $xiaomihome->checkCmdOk('brightnessAct', 'Définir Luminosité', 'action', 'slider', 'brightness', 'brightness', '1', 'light', '0');

    if ($model != 'mono') {
        $xiaomihome->checkCmdOk('colormode', 'Mode', 'info', 'numeric', '0', '0', '0', 'line', '0');
        $xiaomihome->checkAndUpdateCmd('color_mode', $color_mode);

        //RGB
        $xiaomihome->checkCmdOk('rgb', 'Couleur RGB', 'info', 'string', '0', '0', '0', 'line', '0');
        $xiaomihome->checkAndUpdateCmd('rgb', '#' . str_pad(dechex($rgb), 6, "0", STR_PAD_LEFT));
        $xiaomihome->checkCmdOk('rgbAct', 'Définir Couleur RGB', 'action', 'color', 'rgb', 'rgb', '1', '0', '0');

        //HSV 0-253 + Saturation 0-100
        $xiaomihome->checkCmdOk('hsv', 'Couleur HSV', 'info', 'numeric', '0', '0', '0', 'line', '0');
        $xiaomihome->checkAndUpdateCmd('hsv', $hue);
        $xiaomihome->checkCmdOk('hsvAct', 'Définir Couleur HSV', 'action', 'slider', 'hsv', 'hsv', '0', '0', '0');
        $xiaomihome->checkCmdOk('saturation', 'Intensité HSV', 'info', 'numeric', '0', '0', '0', 'line', '0');
        $xiaomihome->checkAndUpdateCmd('saturation', $saturation);
        $xiaomihome->checkCmdOk('saturationAct', 'Définir Intensité HSV', 'action', 'slider', 'saturation', 'saturation', '0', '0', '0');


        //Température en Kelvin 1700-6500
        $xiaomihome->checkCmdOk('temperature', 'Température Blanc', 'info', 'numeric', '0', '0', '0', 'line', '0');
        $xiaomihome->checkAndUpdateCmd('temperature', $color_temp);
        $xiaomihome->checkCmdOk('temperatureAct', 'Définir Température Blanc', 'action', 'slider', 'temperature', 'temperature', '1', '0', '0');
    }
}

public function checkCmdOk($_id, $_name, $_type, $_subtype, $_request, $_setvalue,$_visible, $_template, $_icon) {
    //log::add('xiaomihome', 'debug', $_id . ' ' . $_name . ' ' . $_type . ' ' . $_subtype . ' ' . $_request . ' ' . $_setvalue . ' ' . $_visible . ' ' . $_template . ' ' . $_icon);
    $xiaomihomeCmd = xiaomihomeCmd::byEqLogicIdAndLogicalId($this->getId(),$_id);
    if (!is_object($xiaomihomeCmd)) {
        log::add('xiaomihome', 'debug', 'Création de la commande ' . $_id);
        $xiaomihomeCmd = new xiaomihomeCmd();
        $xiaomihomeCmd->setName(__($_name, __FILE__));
        $xiaomihomeCmd->setEqLogic_id($this->id);
        $xiaomihomeCmd->setEqType('xiaomihome');
        $xiaomihomeCmd->setLogicalId($_id);
        $xiaomihomeCmd->setType($_type);
        $xiaomihomeCmd->setSubType($_subtype);
        if ($_subtype == 'slider') {
            switch ($_id) {
                case 'brightnessAct':
                $xiaomihomeCmd->setConfiguration('minValue', 0);
                $xiaomihomeCmd->setConfiguration('maxValue', 100);
                break;
                case 'hsvAct':
                $xiaomihomeCmd->setConfiguration('minValue', 0);
                $xiaomihomeCmd->setConfiguration('maxValue', 253);
                break;
                case 'saturationAct':
                $xiaomihomeCmd->setConfiguration('minValue', 0);
                $xiaomihomeCmd->setConfiguration('maxValue', 100);
                break;
                case 'temperatureAct':
                $xiaomihomeCmd->setConfiguration('minValue', 1700);
                $xiaomihomeCmd->setConfiguration('maxValue', 6500);
                break;
                case 'cron':
                $xiaomihomeCmd->setConfiguration('minValue', 1);
                $xiaomihomeCmd->setConfiguration('maxValue', 300);
                break;
            }
        }
        $xiaomihomeCmd->setIsVisible($_visible);
        if ($_request != '0') {
            $xiaomihomeCmd->setConfiguration('request', $_request);
        }
        if ($_setvalue != '0') {
            $cmdlogic = xiaomihomeCmd::byEqLogicIdAndLogicalId($this->getId(),$_setvalue);
            $xiaomihomeCmd->setValue($cmdlogic->getId());
        }
        if ($_template != '0') {
            $xiaomihomeCmd->setTemplate("mobile",$_template );
            $xiaomihomeCmd->setTemplate("dashboard",$_template );
        }
        if ($_icon != '0') {
            $xiaomihomeCmd->setDisplay('icon', $_icon);
        }
        $xiaomihomeCmd->save();
    }
}

public static function receiveId($sid, $model, $gateway, $short_id) {
    $xiaomihome = self::byLogicalId($sid, 'xiaomihome');
    if (!is_object($xiaomihome)) {
        $xiaomihome = new xiaomihome();
        $xiaomihome->setEqType_name('xiaomihome');
        $xiaomihome->setLogicalId($sid);
        $xiaomihome->setName($model . ' ' . $sid);
        $xiaomihome->setConfiguration('sid', $sid);
        $xiaomihome->setIsEnable(1);
        $xiaomihome->setIsVisible(1);
        event::add('xiaomihome::includeDevice',
        array(
            'state' => 1
        )
    );
}
$xiaomihome->setConfiguration('model',$model);
$xiaomihome->setConfiguration('short_id',$short_id);
$xiaomihome->setConfiguration('gateway',$gateway);
$xiaomihome->setConfiguration('type','aquara');
$xiaomihome->setConfiguration('lastCommunication',date('Y-m-d H:i:s'));
$xiaomihome->save();
}

public static function receiveHeartbeat($sid, $model, $ip, $gateway, $short_id) {
    $xiaomihome = self::byLogicalId($sid, 'xiaomihome');
    if (!is_object($xiaomihome)) {
        $xiaomihome = new xiaomihome();
        $xiaomihome->setEqType_name('xiaomihome');
        $xiaomihome->setLogicalId($sid);
        $xiaomihome->setName($model . ' ' . $ip);
        $xiaomihome->setConfiguration('sid', $sid);
        $xiaomihome->setConfiguration('model',$model);
        $xiaomihome->setConfiguration('ip',$ip);
        $xiaomihome->setIsEnable(1);
        $xiaomihome->setIsVisible(0);
        event::add('xiaomihome::includeDevice',
        array(
            'state' => 1
        )
    );
}
$xiaomihome->setConfiguration('gateway',$gateway);
$xiaomihome->setConfiguration('short_id',$short_id);
$xiaomihome->setConfiguration('type','aquara');
$xiaomihome->setConfiguration('lastCommunication',date('Y-m-d H:i:s'));
$xiaomihome->save();
}

public static function receiveData($sid, $model, $key, $value) {
    $xiaomihome = self::byLogicalId($sid, 'xiaomihome');
    if (is_object($xiaomihome)) {
        //default
        $unite = '';
        $type = 'string';
        $icone = '';
        $widget = 'line';
        switch ($model) {
            case 'motion':
                if ($value == 'motion') {
                    $value = 1;
                } else {
                    $value = 0;
                }
                $type = 'binary';
                $widget = 'presence';
                break;
            case 'plug':
                if ($key == 'status') {
                    if ($value == 'on') {
                        $value = 1;
                    } else {
                        $value = 0;
                    }
                    $type = 'binary';
                    $widget = 'light';
                }
                break;
            case 'ctrl_neutral1' || 'ctrl_neutral2' :
                if ($value == 'on') {
                    $value = 1;
                } else {
                    $value = 0;
                }
                $type = 'binary';
                $widget = 'light';
                break;
            case 'magnet':
                if ($value == 'close') {
                    $value = 0;
                } else {
                    $value = 1;
                }
                $type = 'binary';
                $widget = 'door';
                break;
            case 'sensor_ht':
                $type = 'numeric';
                $value = $value / 100;
                if ($key == 'humidity') {
                    $unite = '%';
                    $icone = '<i class="fa fa-tint"></i>';
                } else { #temperature
                    $unite = '°C';
                    $icone = '<i class="fa fa-thermometer-empty"></i>';
                }
                break;
        }
        $xiaomihomeCmd = xiaomihomeCmd::byEqLogicIdAndLogicalId($xiaomihome->getId(),$key);
        if (!is_object($xiaomihomeCmd)) {
            log::add('xiaomihome', 'debug', 'Création de la commande ' . $key);
            $xiaomihomeCmd = new xiaomihomeCmd();
            $xiaomihomeCmd->setName(__($key, __FILE__));
            $xiaomihomeCmd->setEqLogic_id($xiaomihome->id);
            $xiaomihomeCmd->setEqType('xiaomihome');
            $xiaomihomeCmd->setLogicalId($key);
            $xiaomihomeCmd->setType('info');
            $xiaomihomeCmd->setSubType($type);
            if ($icone != '') {
                $xiaomihomeCmd->setDisplay('icon', $icone);
            }
            $xiaomihomeCmd->setTemplate("mobile",$widget );
            $xiaomihomeCmd->setTemplate("dashboard",$widget );
            $xiaomihomeCmd->save();
        }
        $xiaomihome->checkAndUpdateCmd($key, $value);
        if (($model == 'plug' && $key == 'status') || $model == 'ctrl_neutral1' || $model == 'ctrl_neutral2') {
            $xiaomiactCmd = xiaomihomeCmd::byEqLogicIdAndLogicalId($xiaomihome->getId(),$key . '-on');
            if (!is_object($xiaomiactCmd)) {
                log::add('xiaomihome', 'debug', 'Création de la commande ' . $key . '-on');
                $xiaomiactCmd = new xiaomihomeCmd();
                $xiaomiactCmd->setName(__($key . '-on', __FILE__));
                $xiaomiactCmd->setEqLogic_id($xiaomihome->id);
                $xiaomiactCmd->setEqType('xiaomihome');
                $xiaomiactCmd->setLogicalId($key . '-on');
                $xiaomiactCmd->setType('action');
                $xiaomiactCmd->setSubType('other');
                if ($icone != '') {
                    $xiaomiactCmd->setDisplay('icon', $icone);
                }
                $xiaomiactCmd->setTemplate("mobile",$widget );
                $xiaomiactCmd->setTemplate("dashboard",$widget );
                $xiaomiactCmd->setValue($xiaomihomeCmd->getId());
                $xiaomihomeCmd->setConfiguration('request', '{\" ' . $key . '\":\"on\"}');
                $xiaomiactCmd->save();
            }
            $xiaomiactCmd = xiaomihomeCmd::byEqLogicIdAndLogicalId($xiaomihome->getId(),$key . '-off');
            if (!is_object($xiaomiactCmd)) {
                log::add('xiaomihome', 'debug', 'Création de la commande ' . $key . '-off');
                $xiaomiactCmd = new xiaomihomeCmd();
                $xiaomiactCmd->setName(__($key . '-off', __FILE__));
                $xiaomiactCmd->setEqLogic_id($xiaomihome->id);
                $xiaomiactCmd->setEqType('xiaomihome');
                $xiaomiactCmd->setLogicalId($key . '-off');
                $xiaomiactCmd->setType('action');
                $xiaomiactCmd->setSubType('other');
                if ($icone != '') {
                    $xiaomiactCmd->setDisplay('icon', $icone);
                }
                $xiaomiactCmd->setTemplate("mobile",$widget );
                $xiaomiactCmd->setTemplate("dashboard",$widget );
                $xiaomiactCmd->setValue($xiaomihomeCmd->getId());
                $xiaomihomeCmd->setConfiguration('request', '{\" ' . $key . '\":\"off\"}');
                $xiaomiactCmd->save();
            }
        }
    }
}

public static function deamon_info() {
    $return = array();
    $return['log'] = 'xiaomihome_node';
    $return['state'] = 'nok';
    $pid = trim( shell_exec ('ps ax | grep "xiaomihome.py" | grep -v "grep" | wc -l') );
    if ($pid != '' && $pid != '0') {
        $return['state'] = 'ok';
    }
    $return['launchable'] = 'ok';
    return $return;
}

public static function deamon_start() {
    self::deamon_stop();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
        throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }
    log::add('xiaomihome', 'info', 'Lancement du démon xiaomihome');

    $url = network::getNetworkAccess('internal') . '/plugins/xiaomihome/core/api/xiaomihome.php?apikey=' . jeedom::getApiKey('xiaomihome');
    $log = log::convertLogLevel(log::getLogLevel('xiaomihome'));
    $sensor_path = realpath(dirname(__FILE__) . '/../../resources');
    $cmd = 'nice -n 19 python ' . $sensor_path . '/xiaomihome.py ' . $url;

    log::add('xiaomihome', 'debug', 'Lancement démon xiaomihome : ' . $cmd);

    $result = exec('nohup ' . $cmd . ' >> ' . log::getPathToLog('xiaomihome_node') . ' 2>&1 &');
    if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
        log::add('xiaomihome', 'error', $result);
        return false;
    }

    $i = 0;
    while ($i < 30) {
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] == 'ok') {
            break;
        }
        sleep(1);
        $i++;
    }
    if ($i >= 30) {
        log::add('xiaomihome', 'error', 'Impossible de lancer le démon xiaomihome, vérifiez le port', 'unableStartDeamon');
        return false;
    }
    message::removeAll('xiaomihome', 'unableStartDeamon');
    log::add('xiaomihome', 'info', 'Démon xiaomihome lancé');
    sleep(5);
    return true;
}

public static function deamon_stop() {
    exec('kill $(ps aux | grep "xiaomihome.py" | awk \'{print $2}\')');
    log::add('xiaomihome', 'info', 'Arrêt du service xiaomihome');
    $deamon_info = self::deamon_info();
    if ($deamon_info['state'] == 'ok') {
        sleep(1);
        exec('kill -9 $(ps aux | grep "xiaomihome.py" | awk \'{print $2}\')');
    }
    $deamon_info = self::deamon_info();
    if ($deamon_info['state'] == 'ok') {
        sleep(1);
        exec('sudo kill -9 $(ps aux | grep "xiaomihome.py" | awk \'{print $2}\')');
    }
}

public static function dependancy_info() {
    $return = array();
    $return['log'] = 'xiaomihome_dep';
    $cmd = "pip list | grep yeecli";
    exec($cmd, $output, $return_var);
    $cmd2 = "pip list | grep mihome";
    exec($cmd2, $output2, $return_var2);
    $return['state'] = 'nok';
    if (array_key_exists(0,$output)) {
        if ($output[0] != "" && $output2[0] != "") {
            $return['state'] = 'ok';
        }
    }
    return $return;
}

public static function dependancy_install() {
    exec('sudo apt-get -y install python-pip && sudo pip install yeecli && sudo pip install mihome > ' . log::getPathToLog('xiaomihome_dep') . ' 2>&1 &');
}

}

class xiaomihomeCmd extends cmd {
    public function preSave() {
        if ($this->getSubtype() == 'message') {
            $this->setDisplay('message_disable', 1);
        }
    }

    public function execute($_options = null) {
        if ($this->getType() == 'info') {
            return $this->getConfiguration('value');
        } else {
            $eqLogic = $this->getEqLogic();
            log::add('xiaomihome', 'debug', 'execute : ' . $this->getType() . ' ' . $eqLogic->getConfiguration('type') . ' ' . $this->getLogicalId());
            if ($eqLogic->getConfiguration('type') == 'yeelight') {
                switch ($this->getSubType()) {
                    case 'slider':
                    $option = $_options['slider'];
                    if ($this->getLogicalId() == 'hsvAct') {
                        $cplmtcmd = xiaomihomeCmd::byEqLogicIdAndLogicalId($eqLogic->getId(),'saturation');
                        $option = $option . ' ' . $cplmtcmd->execCmd();
                    }
                    if ($this->getLogicalId() == 'saturationAct') {
                        $cplmtcmd = xiaomihomeCmd::byEqLogicIdAndLogicalId($eqLogic->getId(),'hsv');
                        $option = $cplmtcmd->execCmd() . ' ' . $option;
                    }
                    log::add('xiaomihome', 'debug', 'Slider : ' . $option);
                    break;
                    case 'color':
                    $option = str_replace('#','',$_options['color']);
                    break;
                    case 'message':
                    $option = $_options['title'];
                    break;
                    default :
                    $option = '';
                }
                //log::add('xiaomihome', 'debug', $eqLogic->getConfiguration('gateway') . ' ' . $this->getConfiguration('request') . ' ' . $option);
                if ($this->getLogicalId() != 'refresh') {
                    if ($option == '000000') {
                        $eqLogic->yeeAction($eqLogic->getConfiguration('gateway'),'turn','off');
                    } else {
                        $eqLogic->yeeAction($eqLogic->getConfiguration('gateway'),$this->getConfiguration('request'),$option);
                    }
                }
                $eqLogic->yeeStatus($eqLogic->getConfiguration('gateway'));
            } else {
                $eqLogic->aquaraAction($request);
            }
        }
    }
}
