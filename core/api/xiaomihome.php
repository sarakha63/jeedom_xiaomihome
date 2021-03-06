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
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'xiaomihome')) {
    echo __('Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (xiaomihome)', __FILE__);
    die();
}

$body = json_decode(file_get_contents('php://input'), true);
log::add('xiaomihome', 'debug', 'Recu ' . init('type') . ' de ' . init('gateway') . ' : ' . print_r($body, true));
//log::add('xiaomihome', 'debug', 'Body non decode ' . file_get_contents('php://input'));

if (init('type') == 'aquara') {
    if ($body['sid'] !== null && $body['model'] !== null) {
        if ($body['model'] == 'gateway') {
            if ($body['cmd'] == 'heartbeat') {
                xiaomihome::receiveHeartbeat($body['sid'], $body['model'], init('gateway'), init('gateway'), $body['short_id']);
            } else {
                xiaomihome::receiveId($body['sid'], $body['model'], init('gateway'), $body['short_id']);
            }
        } else {
            xiaomihome::receiveId($body['sid'], $body['model'], init('gateway'), $body['short_id']);
            //log::add('xiaomihome', 'debug', 'Recu ' . $body['sid'] . ' ' . $body['model'] . ' ' . print_r($body, true));
            if (is_array($body['data'])) {
                foreach ($body['data'] as $key => $value) {
                    xiaomihome::receiveData($body['sid'], $body['model'], $key, $value);
                }
            }
        }
    }

    if (isset($body['token']) && ($body['token'] != config::byKey('token','xiaomihome'))) {
        config::save('token', $body['token'],  'xiaomihome');
    }

} else {
    //log::add('xiaomihome', 'debug', 'Yeelight recu ' . init('gateway') . ' ' . $body['id'] . ' ' . $body['model'] . ' ' . $body['fw_ver'] . ' ' . $body['power'] . ' ' . $body['color_mode'] . ' ' . $body['rgb'] . ' ' . $body['bright'] . ' ' . $body['hue'] . ' ' . $body['sat'] . ' ' . $body['ct']);
    xiaomihome::receiveYeelight(init('gateway'), $body['id'], $body['model'], $body['fw_ver'], $body['power'], $body['color_mode'], $body['rgb'], $body['bright'], $body['hue'], $body['sat'], $body['ct']);
}

return true;

?>
