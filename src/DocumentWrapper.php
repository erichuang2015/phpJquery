<?php

namespace Parse;

/**
 * User: xiangzhiping
 * Date: 2018/9/5
 */

/**
 * 文档处理包装
 *
 * Class DocumentWrapper
 */
class DocumentWrapper
{
    public static $debug = false;

    public    $document;
    protected $encoding;
    protected $docType;

    public function __construct($doc, $toEncoding = 'utf-8', $fromEncoding = null, $version = '1.0')
    {
        $this->document = $this->createDocument($version, $toEncoding);
        libxml_use_internal_errors(true);
        if ($this->isHtml($doc)) {
            $this->docType = 'html';
            DocumentQuery::debug('detect doc type: html');
            $this->loadHTML($doc, $toEncoding, $fromEncoding);
        } elseif ($this->isXml($doc)) {
            $this->docType = 'xml';
            DocumentQuery::debug('detect doc type: xml');
            $this->loadXml($doc, $toEncoding, $fromEncoding);
        } else {
            throw new \Exception('unSupport file type');
        }
    }

    /**
     * 自动检测html编码
     *
     * @param      $html
     * @param null $fromEncoding
     *
     * @return false|string
     */
    public static function autoDocDetect($html, $docType, $fromEncoding = null)
    {
        $charset = '';
        if ($fromEncoding) {
            $charset = mb_detect_encoding($html, [$fromEncoding, 'AUTO']);
        } else {
            if ($docType == 'html') {
                // 从页面获取编码： <META content="text/html; charset=gb2312" http-equiv=Content-Type>
                if (preg_match('@<meta[^>]+charset=([\w-]+)@i', $html, $matches) > 0) {
                    $charset = isset($matches[1]) ? $matches[1] : '';
                }
            } elseif ($docType == 'xml') {
                if (preg_match('@<\?xml[^>]+encoding=(["|\'])(.*)\1@i', $html, $matches)) {
                    $charset = isset($matches[2]) ? $matches[2] : '';
                }
            }

            if (!$charset) {
                $charset = mb_detect_encoding($html);
            }
        }

        return $charset;
    }


    public function isHtml($html)
    {
        return stripos($html, '<html') !== false;
    }

    public function isXml($html)
    {
        return stripos($html, '<?xml') !== false;
    }

    public function createDocument($version = '1.0', $encoding = 'utf-8')
    {
        return new \DOMDocument($version, $encoding);
    }

    public function loadHTML($doc, $toEncoding, $fromEncoding = null)
    {
        $html = $this->makeUpDoc($doc, 'html', $toEncoding, $fromEncoding);

        $result = $this->document->loadHTML($html);
        if ($result === false) {
            throw new \Exception('load html failed');
        }

        return $this;
    }

    /**
     * 加载xml
     *
     * @param      $doc
     * @param      $toEncoding
     * @param null $fromEncoding
     *
     * @return $this
     */
    public function loadXML($doc, $toEncoding, $fromEncoding = null)
    {
        $html = $this->makeUpDoc($doc, 'xml', $toEncoding, $fromEncoding);

        $this->document->loadXML($html);

        return $this;
    }

    /**
     * 文档编码处理
     *
     * @param      $doc
     * @param      $docType
     * @param      $toEncoding
     * @param null $fromEncoding
     *
     * @return string
     */
    public function makeUpDoc($doc, $docType, $toEncoding, $fromEncoding = null)
    {
        $docCharset = $this->autoDocDetect($doc, $docType, $fromEncoding);
        DocumentQuery::debug('detect doc charset: ' . $docCharset);
        if ($toEncoding != $docCharset) {
            DocumentQuery::debug('convert doc from charset: ' . $docCharset . ' to charset ' . $toEncoding);
            $doc = mb_convert_encoding($doc, $toEncoding, $docCharset);
            $doc = $this->charsetAppendToHTML($doc, $toEncoding);
        }

        return $doc;
    }

    protected function charsetAppendToHTML($html, $charset, $xhtml = false)
    {
        // remove existing meta[type=content-type]
        $html = preg_replace('@<meta[^>]+charset[^>]+>@i', '', $html);
        $meta = '<meta http-equiv="Content-Type" content="text/html;charset=' . $charset . '" ' . ($xhtml ? '/' : '') . '>';
        if (strpos($html, '<head') === false) {
            if (strpos($html, '<html') === false) {
                return $meta . $html;
            } else {
                return preg_replace('@<html(.*?)(?(?<!\?)>)@s', "<html\\1><head>{$meta}</head>", $html);
            }
        } else {
            return preg_replace('@<head(.*?)(?(?<!\?)>)@s', '<head\\1>' . $meta, $html);
        }
    }
}