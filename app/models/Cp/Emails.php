<?php

namespace Models\Cp;

class Emails extends BaseModel
{

    const PATH_ALL_KEY = '*ALL*';
    const PATH_INBOX = 'inbox';
    const PATH_SENT = 'sent';
    const PATH_TRASH = 'trash';

    private $allowedTags = array(
        'html' => false,
        'h1' => array('id', 'class'),
        'h2' => array('id', 'class'),
        'h3' => array('id', 'class'),
        'h4' => array('id', 'class'),
        'h5' => array('id', 'class'),
        'h6' => array('id', 'class'),
        'p' => array('id', 'class'),
        'span' => array('id', 'class'),
        'a' => array('id', 'class', 'href'),
        'img' => array('id', 'class', 'src', 'alt', FALSE),
        'br' => array(FALSE),
        'hr' => array(FALSE),
        'pre' => array('id', 'class'),
        'code' => array('id', 'class'),
        'ul' => array('id', 'class'),
        'ol' => array('id', 'class'),
        'li' => array('id', 'class'),
        'table' => array('id', 'class'),
        'tr' => array('id', 'class'),
        'td' => array('id', 'class'),
        'th' => array('id', 'class'),
        'thead' => array('id', 'class'),
        'tbody' => array('id', 'class'),
        'tfoot' => array('id', 'class'),
        'cut' => array('text', FALSE),
        'video' => array()
    );

    public function getDataTableData($devId, $params = array())
    {
        $devId = $this->getDb()->quote($devId);

        if (count($params['sortColumns'])) {
            if ($params['path'] === self::PATH_SENT) {
                $columns = ['e.`timestamp`', 'e.`to`', 'e.`subject`'];
            } else {
                $columns = ['e.`timestamp`', 'e.`from`', 'e.`subject`'];
            }

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        } else {
            $sort = '`timestamp` ASC';
        }

        if ($params['path'] === self::PATH_SENT) {
            $select = "SELECT e.`timestamp`, e.`to`, e.`subject`";
        } else {
            $select = "SELECT e.`timestamp`, e.`from`, e.`subject`";
        }

        $timeFrom = $this->getDb()->quote($params['timeFrom']);
        $timeTo = $this->getDb()->quote($params['timeTo']);
        $account = $this->getDb()->quote($params['account']);

        if ($params['path'] === self::PATH_ALL_KEY) {
            $fromWhere = "FROM `emails` e
                          WHERE
                                e.`dev_id` = {$devId} AND
                                e.`account` = {$account} AND
                                e.`timestamp` >= {$timeFrom} AND
                                e.`timestamp` <= {$timeTo}";
        } else {
            $path = $this->getDb()->quote($params['path']);

            $fromWhere = "FROM `emails` e
                          WHERE
                                e.`dev_id` = {$devId} AND
                                e.`account` = {$account} AND
                                e.`parent` = {$path} AND
                                e.`timestamp` >= {$timeFrom} AND
                                e.`timestamp` <= {$timeTo}";
        }


        $query = "{$select} {$fromWhere}"
                . " ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";

        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_NUM)
        );

        if (empty($result['aaData'])) {
            $result['iTotalRecords'] = 0;
            $result['iTotalDisplayRecords'] = 0;
        } else {
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT e.`timestamp` {$fromWhere}) a")->fetchColumn();
            $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
        }

        return $result;
    }

    public function getPathsList($devId, $account)
    {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);

        $list = $this->getDb()->query("SELECT DISTINCT `parent` FROM `emails` WHERE `dev_id` = {$devId} AND `account` = {$account} ORDER BY `parent`")->fetchAll(\PDO::FETCH_COLUMN);
        $result = array_combine($list, $list);

        return array_merge(array(
            self::PATH_ALL_KEY => $this->di['t']->_('All')
                ), $result);
    }

    public function getAccountsList($devId)
    {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT DISTINCT `account` FROM `emails` WHERE `dev_id` = {$devId} ORDER BY `account`")->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getEmailView($devId, $account, $timestamp)
    {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $timestamp = $this->getDb()->quote($timestamp);

        return $this->getDb()->query("SELECT `timestamp`, `from`, `to`, `subject` FROM `emails` WHERE `dev_id` = {$devId} AND `account` = {$account} AND `timestamp` = {$timestamp} LIMIT 1")->fetch();
    }

    public function getEmailContent($devId, $account, $timestamp)
    {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $timestamp = $this->getDb()->quote($timestamp);

        return $this->getDb()->query("SELECT `text` FROM `emails` WHERE `dev_id` = {$devId} AND `account` = {$account} AND `timestamp` = {$timestamp} LIMIT 1")->fetchColumn();
    }

    private function xssClean($data)
    {
// Fix &entity\n;
        $data = str_replace(array('&amp;', '&lt;', '&gt;'), array('&amp;amp;', '&amp;lt;', '&amp;gt;'), $data);
        $data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
        $data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
        $data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

// Remove any attribute starting with "on" or xmlns
//        $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);
//
//        // Remove javascript: and vbscript: protocols
//        $data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
//        $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
//        $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);
//
//        // Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
//        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
//        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
//        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);
// Remove namespaced elements (we do not need them)
        $data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);

        do {
// Remove really unwanted tags
            $old_data = $data;
            $data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|xml)[^>]*+>#i', '', $data);
        } while ($old_data !== $data);

// we are done...
        return $data;
    }

    private function processDom()
    {
        
    }

    public function replaceImageSrc($content)
    {
        function crawlerProcessor($crawler, $level = 0) {
            //echo '<b>Level: </b>' . $level . PHP_EOL;
            foreach ($crawler as $value) {
                //p($value);
                crawlerProcessor($crawler->children(), ++$level);
            }
        };

        //$content = $this->xssClean($content);

        $crawler = new \Symfony\Component\DomCrawler\Crawler();
        $crawler->addContent($content);

        crawlerProcessor($crawler);
        die;
        var_dump($crawler->html());
        
        //p($crawler->count());

        p($crawler->children());

        foreach ($crawler as $value) {
            p($value);
        }

        return 1; //$content;

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        $dom->loadHTML('<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . $content);
        $dom->normalizeDocument();

        $images = $dom->getElementsByTagName("img");

        foreach ($images as $item) {
            $item->setAttribute('tmp-src', $item->getAttribute('src'));
            $item->setAttribute('src', '');
        }

        return $dom->saveHTML();
    }

}
