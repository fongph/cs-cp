<?php

namespace Models\Cp;

class BaseModel extends \System\Model
{

    private static $_authLifeTime = 3600;

    public function getDb()
    {
        return $this->di->get('dataDb');
    }

    public function getAuthorizedUrl($uri)
    {
        $s3 = $this->di->get('S3');
        return $s3->getAuthenticatedURL($this->di['config']['s3']['bucket'], $uri, self::$_authLifeTime);
    }

    public function getCDNAuthorizedUrl($uri)
    {
        $s3 = $this->di->get('S3');
        return $s3->getSignedCannedURL($this->di['config']['cloudFront']['domain'] . $uri, self::$_authLifeTime);
    }

    public function getDownloadUrl($uri, $filename)
    {
        $extendedUri = $uri . '?response-content-disposition=' . rawurlencode('attachment; filename=' . $filename);

        $config = \CS\Settings\GlobalSettings::getCloudFrontConfig();

        $client = \Aws\CloudFront\CloudFrontClient::factory(array(
                    'key_pair_id' => $config['keyPairId'],
                    'private_key' => $config['privatKeyFilename'],
        ));

        return $client->getSignedUrl(array(
                    'url' => $config['domain'] . $extendedUri,
                    'expires' => time() + $filename,
        ));
    }

}
