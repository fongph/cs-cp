<?php

namespace Models\Cp;

use Aws\AwsClient;

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
        $config = \CS\Settings\GlobalSettings::getS3Config();

        $client = new \Aws\S3\S3Client($config);

        $cmd = $client->getCommand('GetObject', [
            'Bucket' => $config['bucket'],
            'Key'    => $uri,
            'ResponseContentDisposition' => 'attachment; filename="' . $filename . '"'
        ]);

        $request = $client->createPresignedRequest($cmd, '+10 minutes');

        $presignedUrl = (string) $request->getUri();


        return $presignedUrl;
    }

}
