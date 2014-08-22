<?php

namespace Models\Cp;

class Calendar extends \System\Model {

    public function getEventsList($devId, $from, $to, $timeOffset = 0) {
        if (!$devId)
            return null;

        $devId = $this->getDb()->quote($devId);
        $from = (int) $from;
        $to = (int) $to;

        $res = $this->getDb()->query(
                        "SELECT * FROM `calendar_events` WHERE `dev_id` = {$devId} AND (`start` >= {$from} AND `start` <= {$to})"
                        //"SELECT * FROM `calendar_events` `cal` WHERE (`cal`.`end` >= {$from} OR `cal`.`start` >= {$to}) LIMIT 100"
                )->fetchAll();

        $json = array('success' => 1, 'result' => array());
        foreach ($res as $r) {
            if ($r['end'] < $r['start']) {
                $r['end'] = $r['start'];
            }
            
            $json['result'][] = array(
                'id' => $r['event_id'],
                'title' => $r['title'] . (!empty($r['description']) ? "\n{$r['description']}" : null) . ($r['location'] ? "\n{$r['location']}" : null),
                //'class' => $r['rrule'] ? "rrule-{$r['rrule']}" : '',
                //'url' => 'http://www.example.com/',
                'start' => ($r['start'] + $timeOffset) * 1000,
                'end' => ($r['end'] + $timeOffset) * 1000,
            );
        }
        return $json;
    }
    
    public function hasRecords($devId) {
        $devId = $this->getDb()->quote($devId);
        
        return $this->getDb()->query("SELECT `dev_id` FROM `calendar_events` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn() !== false;
    }

}