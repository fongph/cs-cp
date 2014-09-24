<?php

namespace Models\Cp;

class Photos extends \System\Model {

    private static $_authLifeTime = 3600;

    public function getAuthorizedUrl($uri) {
        $s3 = $this->di->get('S3');
        return $s3->getAuthenticatedURL($this->di['config']['s3']['bucket'], $uri, self::$_authLifeTime);
    }
    
    public function getCDNAuthorizedUrl($uri) {
        $s3 = $this->di->get('S3');
        return $s3->getSignedCannedURL($this->di['config']['cloudFront']['domain'] . $uri, self::$_authLifeTime);
    }

    public function getRecentPhotos($devId, $count = 10) {
        $escapedDevId = $this->getDb()->quote($devId);
        $count = intval($count);

        $list = $this->getDb()->query("SELECT `timestamp`, `parent` album, `filepath`, `tmp_name` filename, `deleted` FROM `photos` WHERE `dev_id` = {$escapedDevId} AND `saved` > 0 ORDER BY `timestamp` DESC LIMIT {$count}")->fetchAll();
        
        foreach ($list as $key => $value) {
            $list[$key]['thumbUrl'] = $this->getCDNAuthorizedUrl($devId . '/photos/' . $value['album'] . '/thumb_' . $value['filename']);
            $list[$key]['fullUrl'] = $this->getCDNAuthorizedUrl($devId . '/photos/' . $value['album'] . '/' . $value['filename']);
        }
        
        return $list;
    }

    public function getAlbums($devId) {
        $escapedDevId = $this->getDb()->quote($devId);

        $list = $this->getDb()->query("SELECT 
                                            p.`parent` album,
                                            `tmp_name` filename
                                        FROM `photos` p
                                        INNER JOIN (
                                            SELECT
                                                MAX(`timestamp`) maxTimestamp,
                                                `parent`
                                            FROM `photos`
                                            WHERE 
                                                `dev_id` = {$escapedDevId} AND 
                                                `saved` > 0
                                            GROUP BY `parent`
                                        ) p2 ON p2.`parent`=p.`parent` AND p2.`maxTimestamp`=p.`timestamp`
                                        WHERE 
                                            p.`dev_id` = {$escapedDevId} AND
                                            p.`saved` > 0
                                        GROUP BY 
                                            p.`parent`
                                        ORDER BY `album`")->fetchAll();

        foreach ($list as $key => $value) {
            $list[$key]['thumbUrl'] = $this->getCDNAuthorizedUrl(urlencode($devId) . '/photos/' . urlencode($value['album']) . '/thumb_' . urlencode($value['filename']));
            $list[$key]['fullUrl'] = $this->getCDNAuthorizedUrl(urlencode($devId) . '/photos/' . urlencode($value['album']) . '/' . urlencode($value['filename']));
        }

        return $list;
    }

    public function getAlbumPhotos($devId, $album) {
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedAlbum = $this->getDb()->quote($album);

        $list = $this->getDb()->query("SELECT `timestamp`, `parent` album, `filepath`, `tmp_name` filename, `deleted` FROM `photos` WHERE `dev_id` = {$escapedDevId} AND `saved` > 0 AND `parent` = {$escapedAlbum} ORDER BY `timestamp` DESC")->fetchAll();

        foreach ($list as $key => $value) {
            $list[$key]['thumbUrl'] = $this->getCDNAuthorizedUrl(urlencode($devId) . '/photos/' . urlencode($value['album']) . '/thumb_' . urlencode($value['filename']));
            $list[$key]['fullUrl'] = $this->getCDNAuthorizedUrl(urlencode($devId) . '/photos/' . urlencode($value['album']) . '/' . urlencode($value['filename']));
        }

        return $list;
    }

}
