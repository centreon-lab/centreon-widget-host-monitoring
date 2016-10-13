<?php
/**
 * Copyright 2005-2011 MERETHIS
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

require_once "../../require.php";
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'www/class/centreonService.class.php';
require_once $centreon_path . 'www/class/centreonExternalCommand.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonGMT.class.php';

//             TODO Add checkbox to use host timezone
require_once $centreon_path . 'www/widgets/host-monitoring/class/centreonWidgetHostMonitoringExternalCommand.class.php';
//

session_start();

try {
    var_dump($_POST['cmdType']);
    if (!isset($_SESSION['centreon']) || !isset($_POST['cmdType']) || !isset($_POST['hosts']) ||
        !isset($_POST['author'])) {
        throw new Exception('Missing data');
    }
    $db = new CentreonDB();
    if (CentreonSession::checkSession(session_id(), $db) == 0) {
        throw new Exception('Invalid session');
    }
    $type = $_POST['cmdType'];
    $centreon = $_SESSION['centreon'];
    $hosts = explode(',', $_POST['hosts']);
    $oreon = $centreon;
    $externalCmd = new CentreonExternalCommand($centreon);
    $hostObj = new CentreonHost($db);
    $svcObj = new CentreonService($db);
    $command = "";
    $author = $_POST['author'];
    $comment = "";
    $widgetExternalCommand = new centreonWidgetHostMonitoringExternalCommand($db);
    $commands = array();
    $centreonGMT = new CentreonGMT($db);
    $centreonGMT->getMyGMTFromSession(session_id(), $db);
    $locationId = $centreonGMT->getMyGMT();

    $location = $centreonGMT->getMyTimezone();
    if (!is_null($location)) {
        $dateTime = new DateTime('now', new DateTimeZone($location));
    } else {
        $dateTime = new DateTime('now');
    }

    if (isset($_POST['comment'])) {
        $comment = $_POST['comment'];
    }

    if ($type == 'ack') {
        $persistent = 0;
        $sticky = 0;
        $notify = 0;
        if (isset($_POST['persistent'])) {
            $persistent = 1;
        }
        if (isset($_POST['sticky'])) {
            $sticky = 1;
        }
        if (isset($_POST['notify'])) {
            $notify = 1;
        }

        foreach ($hosts as $hostId) {
            $hostname = $hostObj->getHostName($hostId);
            $pollerId = $hostObj->getHostPollerId($hostId);

            $commands[$pollerId][] = "ACKNOWLEDGE_HOST_PROBLEM;$hostname;$sticky;$notify;$persistent;$author;$comment";
            $commands[$pollerId][] = "ACKNOWLEDGE_SVC_PROBLEM;$hostname;$sticky;$notify;$persistent;$author;$comment";

            if (isset($_POST['forcecheck'])) {
                $commands[$pollerId][] = "SCHEDULE_FORCED_HOST_CHECK;$hostname;".time();
                $commands[$pollerId][] = "SCHEDULE_FORCED_SVC_CHECK;$hostname;".time();
            }
        }

    } elseif ($type == 'downtime') {
        $fixed = 0;
        if (isset($_POST['fixed'])) {
            $fixed = 1;
        }

        $duration = 0;
        if (isset($_POST['dayduration'])) {
            $duration += ($_POST['dayduration'] * 86400);
        }

        if (isset($_POST['hourduration'])) {
            $duration += ($_POST['hourduration'] * 3600);
        }

        if (isset($_POST['minuteduration'])) {
            $duration += ($_POST['minuteduration'] * 60);
        }

        if (!isset($_POST['start']) || !isset($_POST['end'])) {
            throw new Exception ('Missing downtime start/end');
        }

        $dateStart = $_POST['start'];
        $dateEnd = $_POST['end'];

        if (isset($_POST['start_time']) && $_POST['start_time']) {
            $timeStart = str_replace(' ', '', $_POST['start_time']);

        } else {
            $timeStart = '00:00';
        }

        list($hour, $minute) = explode(':', $timeStart);
        $dateTime->setTime($hour, $minute);
        list($year, $month, $day) = explode('/', $dateStart);
        $dateTime->setDate($year, $month, $day);
        $timestampStart = $dateTime->getTimestamp();
        var_dump($timestampStart);

        if (isset($_POST['end_time']) && $_POST['end_time']) {
            $timeEnd = str_replace(' ', '', $_POST['end_time']);
        } else {
            $timeEnd = '00:00';
        }

        list($hour, $minute) = explode(':', $timeEnd);
        $dateTime->setTime($hour, $minute);
        list($year, $month, $day) = explode('/', $dateEnd);
        $dateTime->setDate($year, $month, $day);
        $timestampEnd = $dateTime->getTimestamp();

        foreach ($hosts as $hostId) {
            $hostname = $hostObj->getHostName($hostId);
            $pollerId = $hostObj->getHostPollerId($hostId);

            /*
             TODO Add checkbox to use host timezone

              $timestampStart = $widgetExternalCommand->getTimestamp($hostId, $dateStart, $timeStart);

              $timestampEnd = $widgetExternalCommand->getTimestamp($hostId, $dateEnd, $timeEnd);
            */

            $commands[$pollerId][] = "SCHEDULE_HOST_DOWNTIME;$hostname;$timestampStart;$timestampEnd;$fixed;0;$duration;$author;$comment";

            if (isset($_POST['processServices'])) {
                $commands[$pollerId][] = "SCHEDULE_HOST_SVC_DOWNTIME;$hostname;$timestampStart;$timestampEnd;$fixed;0;$duration;$author;$comment";
            }
        }

    } else {
        throw new Exception('Unknown command');
    }

    foreach ($commands as $pollerId => $commandLines) {
        foreach ($commandLines as $commandLine) {
            $externalCmd->setProcessCommand($commandLine, $pollerId);
        }
    }
    $externalCmd->write();

} catch (Exception $e) {
    echo $e->getMessage();
}
