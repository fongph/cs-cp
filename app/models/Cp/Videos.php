<?php

namespace Models\Cp;

class Videos extends BaseModel {

    private static $_authLifeTime = 3600;

    public function getAuthorizedUrl($uri) {
        $s3 = $this->di->get('S3');
        return $s3->getAuthenticatedURL($this->di['config']['s3']['bucket'], $uri, self::$_authLifeTime);
    }

    public function getCDNAuthorizedUrl($uri) {
        $s3 = $this->di->get('S3');
        return $s3->getSignedCannedURL($this->di['config']['cloudFront']['domain'] . $uri, self::$_authLifeTime);
    }

    public function getRecentVideos($devId, $count = 8) {
        $escapedDevId = $this->getDb()->quote($devId);
        $count = intval($count);

        $list = $this->getDb()->query("SELECT `timestamp`, `parent` album, `filepath`, `filename`, `is_full`, `requested`, `deleted` FROM `video` WHERE `dev_id` = {$escapedDevId} ORDER BY `timestamp` DESC LIMIT {$count}")->fetchAll();

        foreach ($list as $key => $value) {
            $list[$key]['thumbUrl'] = $this->getCDNAuthorizedUrl(urlencode($devId) . '/video/' . urlencode($value['album']) . '/' . urlencode($value['filename']) . '.jpg');
        }

        return $list;
    }

    public function getCameraVideos($devId) {
        $escapedDevId = $this->getDb()->quote($devId);

        $list = $this->getDb()->query("SELECT `timestamp`, `parent` album, `filepath`, `filename`, `is_full`, `requested`, `deleted` FROM `video` WHERE `dev_id` = {$escapedDevId} AND `filepath` LIKE '%dcim/%' ORDER BY `timestamp` DESC")->fetchAll();

        foreach ($list as $key => $value) {
            $list[$key]['thumbUrl'] = $this->getCDNAuthorizedUrl(urlencode($devId) . '/video/' . urlencode($value['album']) . '/' . urlencode($value['filename']) . '.jpg');
        }

        return $list;
    }

    public function getNoCameraVideos($devId) {
        $escapedDevId = $this->getDb()->quote($devId);

        $list = $this->getDb()->query("SELECT `timestamp`, `parent` album, `filepath`, `filename`, `is_full`, `requested`, `deleted` FROM `video` WHERE `dev_id` = {$escapedDevId} AND `filepath` NOT LIKE '%dcim/%' ORDER BY `timestamp` DESC")->fetchAll();

        foreach ($list as $key => $value) {
            $list[$key]['thumbUrl'] = $this->getCDNAuthorizedUrl(urlencode($devId) . '/video/' . urlencode($value['album']) . '/' . urlencode($value['filename']) . '.jpg');
        }

        return $list;
    }

    public function getFirstCameraVideo($devId) {
        $escapedDevId = $this->getDb()->quote($devId);

        if (($value = $this->getDb()->query("SELECT `timestamp`, `parent` album, `filepath`, `filename`, `is_full`, `requested`, `deleted` FROM `video` WHERE `dev_id` = {$escapedDevId} AND `filepath` LIKE '%dcim/%' ORDER BY `timestamp` DESC LIMIT 1")->fetch()) === false) {
            return false;
        }

        $value['thumbUrl'] = $this->getCDNAuthorizedUrl(urlencode($devId) . '/video/' . urlencode($value['album']) . '/' . urlencode($value['filename']) . '.jpg');
        return $value;
    }

    public function getFirstNoCameraVideo($devId) {
        $escapedDevId = $this->getDb()->quote($devId);

        if (($value = $this->getDb()->query("SELECT `timestamp`, `parent` album, `filepath`, `filename`, `is_full`, `requested`, `deleted` FROM `video` WHERE `dev_id` = {$escapedDevId} AND `filepath` NOT LIKE '%dcim/%' ORDER BY `timestamp` DESC LIMIT 1")->fetch()) === false) {
            return false;
        }

        $value['thumbUrl'] = $this->getCDNAuthorizedUrl(urlencode($devId) . '/video/' . urlencode($value['album']) . '/' . urlencode($value['filename']) . '.jpg');
        return $value;
    }

    public function getVideoParams($devId, $filepath) {
        $devId = $this->getDb()->quote($devId);
        $filepath = $this->getDb()->quote($filepath);

        return $this->getDb()->query("SELECT `is_full`, `requested`, `deleted` FROM `video` WHERE `dev_id` = {$devId} AND `filepath` = {$filepath} LIMIT 1")->fetch();
    }

    public function setVideoRequested($devId, $filepath) {
        if (($params = $this->getVideoParams($devId, $filepath)) === false) {
            throw new VideosRecordNotFoundException();
        }

        if ($params['requested']) {
            throw new VideosAlreadyRequestedException();
        }

        if ($params['is_full']) {
            throw new VideosAlreadyUploadedException();
        }

        if ($params['deleted']) {
            throw new VideosDeletedException();
        }

        $devId = $this->getDb()->quote($devId);
        $filepath = $this->getDb()->quote($filepath);
        return $this->getDb()->exec("UPDATE `video` SET `requested` = 1 WHERE `dev_id` = {$devId} AND `filepath` = {$filepath} LIMIT 1");
    }

    public function cancelVideoRequest($devId, $filepath) {
        if (($params = $this->getVideoParams($devId, $filepath)) === false) {
            throw new VideosRecordNotFoundException();
        }

        if ($params['is_full']) {
            throw new VideosAlreadyUploadedException();
        }

        if ($params['deleted']) {
            throw new VideosDeletedException();
        }

        if (!$params['requested']) {
            throw new VideosNoRequestToCancelException();
        }

        $devId = $this->getDb()->quote($devId);
        $filepath = $this->getDb()->quote($filepath);
        return $this->getDb()->exec("UPDATE `video` SET `requested` = 0 WHERE `dev_id` = {$devId} AND `filepath` = {$filepath} LIMIT 1");
    }

    public function hasRecords($devId) {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT `dev_id` FROM `video` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn() !== false;
    }

}

class VideosRecordNotFoundException extends \Exception {
    
}

class VideosAlreadyRequestedException extends \Exception {
    
}

class VideosAlreadyUploadedException extends \Exception {
    
}

class VideosNoRequestToCancelException extends \Exception {
    
}

class VideosDeletedException extends \Exception {
    
}
