<?php

require_once $centreon_path . 'www/class/centreonGMT.class.php';


class centreonWidgetHostMonitoringExternalCommand
{
    private $db;
    private $hostLocations;

    public function __construct($db)
    {
        $this->db = $db;
        $gmtObj = new CentreonGMT($this->db);
        $this->hostLocations = $gmtObj->getHostLocations();
    }

    public function getTimestamp($hostId, $date, $time)
    {
        list($year, $month, $day) = explode('/', $date);
        list($hour, $minute) = explode (':', $time);

        $dateTime = new DateTime('now', new DateTimeZone($this->hostLocations[$hostId]));
        $dateTime->setDate($year, $month, $day);
        $dateTime->setTime($hour, $minute);

        $timestamp = $dateTime->getTimestamp();

        return $timestamp;
    }
}
