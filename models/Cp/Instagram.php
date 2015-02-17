<?php

namespace Models\Cp;

class Instagram extends BaseModel
{

    const AVATAR_EMPTY_VALUE = 'none';

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

    public function getFirstAccount($devId)
    {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT `account_id` FROM `instagram_posts` WHERE `dev_id` = {$devId} AND `status` != 'none' LIMIT 1")->fetchColumn();
    }

    public function getAccounts($devId)
    {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT
                        p.`account_id`,
                        u.`nickname`
                    FROM `instagram_posts` p
                    INNER JOIN `instagram_users` u ON p.`dev_id` = u.`dev_id` AND p.`account_id` = u.`account_id` AND u.`user_id` = p.`account_id`
                    WHERE
                        p.`status` != 'none' AND
                        p.`dev_id` = {$devId}")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function getOwnPostsData($devId, $accountId, $dateFrom, $dateTo, $recordsPerPage = 10, $page = 1)
    {
        if ($page < 1) {
            $page = 1;
        }

        $records = $this->getDb()->query("SELECT COUNT(*) FROM `instagram_posts`
                        WHERE 
                            `dev_id` = {$devId} AND
                            `account_id` = {$accountId} AND
                            `author_id` = {$accountId} AND
                            `status` != 'none' AND
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
                        p.`status`,
                        p.`thumbnail`,
                        (SELECT COUNT(*) FROM `instagram_comments` c WHERE c.`dev_id` = p.`dev_id` AND c.`account_id` = p.`account_id` AND c.`post_id` = p.`post_id`) comments
                    FROM `instagram_posts` p
                    WHERE 
                        p.`dev_id` = {$devId} AND
                        p.`account_id` = {$accountId} AND
                        p.`author_id` = {$accountId} AND
                        p.`status` != 'none' AND
                        p.`timestamp` >= {$dateFrom} AND
                        p.`timestamp` <= {$dateTo}
                    ORDER BY `timestamp` DESC
                    LIMIT {$start}, {$recordsPerPage}")->fetchAll();

        foreach ($posts as $key => $value) {
            $posts[$key]['thumbnail'] = $this->getCDNAuthorizedUrl($value['thumbnail']);
        }

        return array(
            'data' => $posts,
            'page' => (int) $page,
            'recordsPerPage' => $recordsPerPage,
            'records' => $records
        );
    }

    public function getFriendsPostsData($devId, $accountId, $dateFrom, $dateTo, $recordsPerPage = 10, $page = 1)
    {
        if ($page < 1) {
            $page = 1;
        }

        $records = $this->getDb()->query("SELECT COUNT(*) FROM `instagram_posts`
                        WHERE 
                            `dev_id` = {$devId} AND
                            `account_id` = {$accountId} AND
                            `author_id` != {$accountId} AND
                            `status` != 'none' AND
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
                        p.`status`,
                        p.`thumbnail`,
                        (SELECT COUNT(*) FROM `instagram_comments` c WHERE c.`dev_id` = p.`dev_id` AND c.`account_id` = p.`account_id` AND c.`post_id` = p.`post_id`) comments
                    FROM `instagram_posts` p
                    WHERE 
                        p.`dev_id` = {$devId} AND
                        p.`account_id` = {$accountId} AND
                        p.`author_id` != {$accountId} AND
                        p.`status` != 'none' AND
                        p.`timestamp` >= {$dateFrom} AND
                        p.`timestamp` <= {$dateTo}
                    ORDER BY `timestamp` DESC
                    LIMIT {$start}, {$recordsPerPage}")->fetchAll();

        foreach ($posts as $key => $value) {
            $posts[$key]['thumbnail'] = $this->getCDNAuthorizedUrl($value['thumbnail']);
        }

        return array(
            'data' => $posts,
            'page' => (int) $page,
            'recordsPerPage' => $recordsPerPage,
            'records' => $records
        );
    }
    
    public function getCommentedPostsData($devId, $accountId, $dateFrom, $dateTo, $recordsPerPage = 10, $page = 1)
    {
        if ($page < 1) {
            $page = 1;
        }

        $records = $this->getDb()->query("SELECT COUNT(DISTINCT c.`post_id`) 
                        FROM `instagram_comments` c
                        INNER JOIN `instagram_posts` p ON p.`dev_id` = c.`dev_id` AND p.`account_id` = c.`account_id` AND p.`post_id` = c.`post_id`
                        WHERE
                            c.`dev_id` = {$devId} AND
                            c.`account_id` = {$accountId} AND
                            c.`author_id` = {$accountId} AND
                            p.`status` != 'none' AND
                            p.`timestamp` >= {$dateFrom} AND
                            p.`timestamp` <= {$dateTo}")->fetchColumn();

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
                        p.`status`,
                        p.`thumbnail`,
                        (SELECT COUNT(*) FROM `instagram_comments` c WHERE c.`dev_id` = p.`dev_id` AND c.`account_id` = p.`account_id` AND c.`post_id` = p.`post_id`) comments
                    FROM `instagram_comments` c
                    INNER JOIN `instagram_posts` p ON p.`dev_id` = c.`dev_id` AND p.`account_id` = c.`account_id` AND p.`post_id` = c.`post_id`
                    WHERE 
                        c.`dev_id` = {$devId} AND
                        c.`account_id` = {$accountId} AND
                        c.`author_id` = {$accountId} AND
                        p.`status` != 'none' AND
                        p.`timestamp` >= {$dateFrom} AND
                        p.`timestamp` <= {$dateTo}
                    GROUP BY p.`id`
                    ORDER BY `timestamp` DESC
                    LIMIT {$start}, {$recordsPerPage}")->fetchAll();

        foreach ($posts as $key => $value) {
            $posts[$key]['thumbnail'] = $this->getCDNAuthorizedUrl($value['thumbnail']);
        }

        return array(
            'data' => $posts,
            'page' => (int) $page,
            'recordsPerPage' => $recordsPerPage,
            'records' => $records,
            'value' => str_replace("\n", " ", "SELECT COUNT(*) 
                        FROM `instagram_comments` c
                        INNER JOIN `instagram_posts` p ON p.`dev_id` = c.`dev_id` AND p.`account_id` = c.`account_id` AND p.`post_id` = c.`post_id`
                        WHERE
                            c.`dev_id` = {$devId} AND
                            c.`account_id` = {$accountId} AND
                            c.`author_id` = {$accountId} AND
                            p.`status` != 'none' AND
                            p.`timestamp` >= {$dateFrom} AND
                            p.`timestamp` <= {$dateTo}
                        GROUP BY p.`id`")
        );
    }
    
    public function getPostComments($devId, $accountId, $post)
    {
        $devId = $this->getDb()->quote($devId);
        $accountId = $this->getDb()->quote($accountId);
        $post = $this->getDb()->quote($post);

        $data = $this->getDb()->query("SELECT
                        c.*,
                        u.`nickname` author_nickname,
                        u.`avatar_path` author_avatar
                    FROM `instagram_comments` c
                    INNER JOIN `instagram_users` u ON u.`dev_id` = c.`dev_id` AND u.`account_id` = c.`account_id` AND u.`user_id` = c.`author_id`
                    WHERE
                        c.`dev_id` = {$devId} AND
                        c.`account_id` = {$accountId} AND
                        c.`post_id` = {$post}
                    ORDER BY `timestamp`")->fetchAll();

        foreach ($data as $key => $value) {
            if ($value['author_avatar'] !== self::AVATAR_EMPTY_VALUE) {
                $data[$key]['author_avatar'] = $this->getCDNAuthorizedUrl($value['author_avatar']);
            }
        }

        return $data;
    }

    public function setPostVideoRequestedStatus($id)
    {
        $id = $this->getDb()->quote($id);

        return $this->getDb()->exec("UPDATE `instagram_posts` SET `status` = 'video-requested' WHERE `id` = {$id}");
    }

    public function getPost($devId, $accountId, $post)
    {
        $devId = $this->getDb()->quote($devId);
        $accountId = $this->getDb()->quote($accountId);
        $post = $this->getDb()->quote($post);

        $data = $this->getDb()->query("SELECT
                        p.*,
                        u.`nickname` author_nickname,
                        u.`avatar_path` author_avatar
                    FROM `instagram_posts` p
                    INNER JOIN `instagram_users` u ON u.`dev_id` = p.`dev_id` AND u.`account_id` = p.`account_id` AND u.`user_id` = p.`author_id`
                    WHERE
                        p.`dev_id` = {$devId} AND
                        p.`account_id` = {$accountId} AND
                        p.`post_id` = {$post}
                    LIMIT 1")->fetch();

        if ($data === false) {
            return false;
        }

        if ($data['author_avatar'] !== self::AVATAR_EMPTY_VALUE) {
            $data['author_avatar'] = $this->getCDNAuthorizedUrl($data['author_avatar']);
        }

        if ($data['status'] === 'video-saved') {
            $data['video'] = $this->getCDNAuthorizedUrl($data['video']);
        }

        $data['media'] = $this->getCDNAuthorizedUrl($data['media']);

        return $data;
    }

}
