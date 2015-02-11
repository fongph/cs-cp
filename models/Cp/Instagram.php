<?php

namespace Models\Cp;

class Instagram extends BaseModel
{

    private static $_authLifeTime = 3600;

    public function getAuthorizedUrl($uri)
    {
        $s3 = $this->di->get('S3');
        return $s3->getAuthenticatedURL($this->di['config']['s3']['bucket'] . $uri, self::$_authLifeTime);
    }

    public function getCDNAuthorizedUrl($uri)
    {
        $s3 = $this->di->get('S3');
        return $s3->getSignedCannedURL($this->di['config']['cloudFront']['domain'] . $uri, self::$_authLifeTime);
    }

    public function getAccounts($devId)
    {
        return $this->getDb()->query("SELECT
                        `account_id`,
                        `account_nickname`
                    FROM `instagram_posts` WHERE `dev_id` = {$devId}")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function getPostedData($devId, $accountId, $dateFrom, $dateTo, $recordsPerPage = 10, $page = 1)
    {
        if ($page < 1) {
            $page = 1;
        }

        $records = $this->getDb()->query("SELECT COUNT(*) FROM `instagram_posts` p WHERE 
                        `dev_id` = {$devId} AND
                        `account_id` = {$accountId} AND
                        `timestamp` >= {$dateFrom} AND
                        `timestamp` <= {$dateTo}")->fetchColumn();

        $pages = max(ceil($records / $recordsPerPage), 1);

        if ($page > $pages) {
            $page = $page;
        }

        $start = ($page - 1) * $recordsPerPage;

        $posts = $this->getDb()->query("SELECT
                        p.`account_id`,
                        p.`post_id`,
                        p.`timestamp`,
                        p.`type`,
                        p.`thumbnail`,
                        (SELECT COUNT(*) FROM `instagram_comments` c WHERE c.`dev_id` = p.`dev_id` AND c.`account_id` = p.`account_id` AND c.`post_id` = p.`post_id`) comments
                    FROM `instagram_posts` p
                    WHERE 
                        p.`dev_id` = {$devId} AND
                        p.`account_id` = {$accountId} AND
                        p.`timestamp` >= {$dateFrom} AND
                        p.`timestamp` <= {$dateTo}
                    LIMIT {$start}, {$recordsPerPage}")->fetchAll();

        foreach ($posts as $key => $value) {
            $posts[$key]['thumbnail'] = $this->getCDNAuthorizedUrl($value['thumbnail']);
        }

        return array(
            'data' => $posts,
            'page' => (int) $page,
            'recordsPerPage' => $recordsPerPage,
            'records' => $records,
            'request' => " {$start}, {$recordsPerPage}"
        );
    }

    public function getPost($devId, $accountId, $post)
    {
        $devId = $this->getDb()->quote($devId);
        $accountId = $this->getDb()->quote($accountId);
        $post = $this->getDb()->quote($post);
        
        return $this->getDb()->query("SELECT
                        p.*,
                        u.`nickname` author_nickname,
                        u.`avatar_path` author_avatar_path
                    FROM `instagram_posts` p
                    LEFT JOIN `instagram_users` u ON u.`dev_id` = p.`dev_id` AND u.`account_id` = p.`account_id` AND u.`user_id` = p.`author_id`
                    WHERE
                        p.`dev_id` = {$devId} AND
                        p.`account_id` = {$accountId} AND
                        p.`post_id` = {$post}
                    LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    }

}
