<?php

namespace Models\Cp;

class Emails extends BaseModel
{

    const PATH_ALL_KEY = '*ALL*';
    const PATH_INBOX = 'inbox';
    const PATH_SENT = 'sent';
    const PATH_TRASH = 'trash';

    private $allowedTags = array(
        'a' => array('href', 'target'),
        'abbr' => array(),
        'acronym' => array(),
        'address' => array(),
        'b' => array(),
        'big' => array(),
        'blockquote' => array('cite'),
        'body' => array('alink', 'background', 'bgcolor', 'bgproperties', 'bottommargin', 'leftmargin', 'link', 'rightmargin', 'scroll', 'text', 'topmargin', 'vlink'),
        'br' => array('clear'),
        'caption' => array('align', 'valign'),
        'center' => array(),
        'cite' => array(),
        'code' => array(),
        'col' => array('align', 'span', 'valign', 'width'),
        'colgroup' => array(),
        'dd' => array(),
        'div' => array('align'),
        'dl' => array(),
        'dt' => array(),
        'em' => array(),
        'font' => array('color', 'face', 'size'),
        'h1' => array('align'),
        'h2' => array('align'),
        'h3' => array('align'),
        'h4' => array('align'),
        'h5' => array('align'),
        'h6' => array('align'),
        'hr' => array('align', 'color', 'noshade', 'size', 'width'),
        'html' => array('xmlns'),
        'i' => array(''),
        'img' => array('align', 'alt', 'border', 'height', 'hspace', 'ismap', 'src', 'vspace', 'width', 'usemap'),
        'li' => array('type', 'value'),
        'ol' => array('type', 'start'),
        'p' => array('align'),
        'pre' => array(),
        'small' => array(),
        'span' => array(),
        'strike' => array(),
        'strong' => array(),
        'sub' => array(),
        'sup' => array(),
        'table' => array('align', 'background', 'bgcolor', 'border', 'bordercolor', 'cellpadding', 'cellspacing', 'cols', 'frame', 'height', 'rules', 'width'),
        'tbody' => array('align', 'bgcolor', 'valign'),
        'td' => array('abbr', 'align', 'background', 'bgcolor', 'bordercolor', 'colspan', 'headers', 'height', 'nowrap', 'rowspan', 'valign', 'width'),
        'tfoot' => array('align', 'bgcolor', 'valign'),
        'th' => array('abbr', 'align', 'background', 'bgcolor', 'bordercolor', 'colspan', 'headers', 'height', 'nowrap', 'rowspan', 'valign', 'width'),
        'thead' => array('align', 'bgcolor', 'valign'),
        'tr' => array('align', 'bgcolor', 'bordercolor', 'valign'),
        'u' => array(),
        'ul' => array('type')
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

    function removeNotAllowedNodes(\DOMNode &$element)
    {
        if ($element->hasChildNodes()) {
            for ($i = $element->childNodes->length - 1; $i >= 0; $i--) {
                $child = $element->childNodes->item($i);

                if ($child instanceof \DOMElement) {
                    if (!key_exists($child->tagName, $this->allowedTags)) {
                        $element->removeChild($child);
                    } else {
                        $this->removeNotAllowedNodes($child);
                    }
                }
            }
        }

        if ($element->hasAttributes()) {
            for ($i = $element->attributes->length - 1; $i >= 0; $i--) {
                $name = $element->attributes->item($i)->name;
                if ($name !== 'style' && !in_array($name, $this->allowedTags[$element->tagName])) {
                    $element->removeAttribute($name);
                }
            }
        }
    }

    public function replaceImageSrc($content)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        $dom->loadHTML('<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . $content);
        $dom->normalizeDocument();
        
        $this->removeNotAllowedNodes($dom);

        $images = $dom->getElementsByTagName("img");

        foreach ($images as $item) {
            $item->setAttribute('tmp-src', $item->getAttribute('src'));
            $item->setAttribute('src', '');
        }

        return $dom->saveHTML();
    }

}
