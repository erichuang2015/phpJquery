<?php

namespace Parse;

/**
 * User: xiangzhiping
 * Date: 2018/9/5
 */

class DocumentQuery
{
    public static $debug = false;

    private $document;

    private $xpath;

    /**
     * DOMQuery constructor.
     *
     * @param DocumentWrapper|string|\DOMDocument $document
     *
     * @throws
     */
    public function __construct($document)
    {
        if (is_string($document)) {
            $this->document = (new DocumentWrapper($document))->document;
        } elseif ($document instanceof DocumentWrapper) {
            $this->document = $document->document;
        } elseif ($document instanceof \DOMDocument) {
            $this->document = $document;
        } else {
            throw new \Exception('unSupport type');
        }

        $this->xpath = new \DOMXPath($this->document);
    }

    /**
     * 执行xpath 返回
     *
     * @param                  $xpath
     * @param callable|null    $callback
     * @param \DOMElement|null $node
     *
     * @return array  DOMElement
     */
    public function runXpath($xpath, \DOMElement $node = null, callable $callback = null)
    {
        self::debug('query xpath: ' . $xpath . ($node ? ' with node relative path ' . $node->getNodePath() : ''));

        /** @var \DOMNodeList $nodeList */
        $nodeList = $this->xpath->query($xpath, $node);
        $result   = [];
        /** @var \DOMElement $node */
        foreach ($nodeList as $node) {
            if (!$callback || call_user_func_array($callback, [$node])) {
                $result[] = $node;
            }
        }

        return $result;
    }

    /**
     * 执行js选择器--转换成xpath执行
     *
     * @param string   $selector
     * @param \DOMNode $root 相对的查找节点
     *
     * @return array
     * @throws
     */
    public function runSelector($selector, $root = null)
    {
        $queries = self::parseSelector($selector);
        self::debug('queries : ' . json_encode($queries));
        $list  = null;
        $first = false;

        $lastDetailList = [];
        $xpath          = '';
        foreach ($queries as $s) {
            if (self::isTagName($s)) { // TAG
                $xpath .= ($xpath || $first === false || $root ? '' : 'descendant::') . $s;
            } elseif ($s{0} == '#') { // ID
                $xpath .= ($xpath || $first === false || $root ? '*' : 'descendant::*') . '[@id="' . substr($s, 1) . '"]';
            } elseif ($s{0} == '.') {
                $xpath .= ($xpath || $first === false || $root ? '*' : 'descendant::*') . '[contains(@class, "' . substr($s, 1) . '")]';
            } elseif ($s{0} == '>') {
                $xpath .= './';
                continue;
            } elseif ($s{0} == '~') {
                $xpath .= '../';
                continue;
            } elseif ($s{0} == '+') {
                $xpath .= 'following-sibling::';
                continue;
            } elseif ($s{0} == ':') {
                $xpath = '';
                $list  = $this->_filter($list, $s, $lastDetailList);
                continue;
            } else {
                throw new \Exception('un support query: ' . $s);
            }

            if ($first == true) { // 在上次的结果集节点继续查询
                $result = [];
                /** @var \DOMElement $item */
                $lastDetailList = [];
                foreach ($list as $item) {
                    $merge = $this->runXpath($xpath, $item);
                    if ($merge) $result = self::unique($result, $merge);
                    $lastDetailList[] = $merge;
                }
                $list = $result;
            } else {
                $list           = $this->runXpath(($root ? '' : '//') . $xpath, $root);// 第一次从所有文档查询
                $lastDetailList = [$list];
                $first          = true;
            }
            $xpath = ''; // 查询过的xpath 重置

            if (count($list) <= 0) break;
        }

        return $list;
    }

    /**
     * 冒号筛选
     *
     * @param $list
     * @param $class
     * @param $lastDetailList
     *
     * @return array
     */
    private function _filter($list, $class, $lastDetailList = [])
    {
        $class = ltrim($class, ':');
        $args  = '';
        if (($pos = strpos($class, '(')) !== false) {
            $args  = substr($class, $pos + 1, -1);
            $class = substr($class, 0, $pos);
        }
        $stack = [];

        /** @var \DOMElement|\DOMNode $item */
        switch ($class) {
            case 'eq':
                $key   = intval($args);
                $stack = isset($list[$key]) ? [$list[$key]] : [];
                break;
            case 'gt':
                $stack = array_slice($list, $args + 1);
                break;
            case 'lt':
                $stack = array_slice($list, 0, $args + 1);
                break;
            case 'first':
                $stack = isset($list[0]) ? [$list[0]] : [];
                break;
            case 'last':
                $stack = $list ? [$list[count($list) - 1]] : [];
                break;
            case 'checked':
                foreach ($list as $item) {
                    if ($item->tagName == 'input' && in_array($item->getAttribute('type'), ['checkbox', 'radio'])
                        && $item->getAttribute('checked') == 'checked') {
                        $stack[] = $item;
                    }
                }
                break;
            case 'selected':
                $stack = [];
                foreach ($list as $item) {
                    if ($item->tagName == 'option' && $item->getAttribute('selected') == 'selected') {
                        $stack[] = $item;
                    }
                }
                break;
            case 'disabled':
                foreach ($list as $item) {
                    if (in_array($item->tagName, ['button', 'input', 'option', 'select',
                            'textarea', 'fieldset', 'menuitem']) && $item->getAttribute('disabled') == 'disabled') {
                        $stack[] = $item;
                    }
                }
                break;
            case 'header':
                foreach ($list as $item) {
                    if (in_array($item->tagName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'h7'])) {
                        $stack[] = $item;
                    }
                }
                break;
            case 'even': // 偶数
                foreach ($list as $i => $item) {
                    if ($i % 2 == 0) {
                        $stack[] = $item;
                    }
                }
                break;
            case 'odd': // 奇数
                foreach ($list as $i => $item) {
                    if ($i % 2 == 1) {
                        $stack[] = $item;
                    }
                }
                break;
            case 'slice':
                $args   = explode(',', str_replace(', ', ',', trim($args, "\"'")));
                $start  = $args[0] ?? 0;
                $length = $args[1] ?? 0;
                $stack  = array_slice($list, $start, $length);
                break;
            case 'empty':
                foreach ($list as $i => $item) {
                    if ($item->childNodes->length == 0) {
                        $stack[] = $item;
                    }
                }
                break;
            case 'parent':
                foreach ($list as $i => $item) {
                    if ($item->childNodes->length > 0) {
                        $stack[] = $item;
                    }
                }
                break;
            case 'not':
                $selector = trim($args, "\"'");
                foreach ($list as $item) {
                    if (!$this->runSelector($selector, $item)) {
                        $stack[] = $item;
                    }
                }
                break;
                break;
            case 'has':  // 包含选择器
                $selector = trim($args, "\"'");
                foreach ($list as $item) {
                    if ($this->runSelector($selector, $item)) {
                        $stack[] = $item;
                    }
                }
                break;
            case 'contains':
                $text = trim($args, '"\'');
                if ($text === '') break;

                /** @var \DOMNode|\DOMElement $item */
                foreach ($list as $item) {
                    if (mb_stripos($item->textContent, $text) !== false) {
                        $stack[] = $item;
                    }
                }
                break;
            case 'first-child':
                foreach ($lastDetailList as $mergeList) {
                    if (isset($mergeList[0])) {
                        $stack[] = $mergeList[0];
                    }
                }
                break;
            case 'last-child':
                foreach ($lastDetailList as $mergeList) {
                    if ($mergeList) {
                        $stack[] = $mergeList[count($mergeList) - 1];
                    }
                }
                break;
            case 'nth-child':
                $param = trim($args, "\"'");
                if ($param == 'even') {
                    foreach ($lastDetailList as $mergeList) {
                        foreach ($mergeList as $i => $item) {
                            if ($i % 2 == 0) {
                                $stack[] = $item;
                            }
                        }
                    }
                } elseif ($param == 'odd') {
                    foreach ($lastDetailList as $mergeList) {
                        foreach ($mergeList as $i => $item) {
                            if ($i % 2 == 1) {
                                $stack[] = $item;
                            }
                        }
                    }
                } elseif (preg_match('@(\d*)n(([\+\-])(\d+))?@i', $param, $matches) > 0) {// an+b;
                    $a = $matches[1] ?? 1 ?: 1;
                    $o = $matches[3] ?? '+' ?: '+';
                    $b = $matches[4] ?? 0 ?: 0;
                    if ($o == '-') {
                        $b = -$b;
                    }
                    foreach ($lastDetailList as $mergeList) {
                        foreach ($mergeList as $i => $item) {
                            if ($i + 1 - $b > 0 && ($i + 1 - $b) % $a == 0) {
                                $stack[] = $item;
                            }
                        }
                    }
                } elseif (is_numeric($param)) {
                    $param = intval($param) - 1;
                    foreach ($lastDetailList as $mergeList) {
                        if (isset($mergeList[$param])) {
                            $stack[] = $mergeList[$param];
                        }
                    }
                }
                break;
            case 'only-child': // 如果某个元素是其父元素的唯一子元素，那么它就会被选中
                foreach ($lastDetailList as $mergeList) {
                    if (count($mergeList) == 1) {
                        $stack[] = $mergeList[0];
                    }
                }
                break;
            default:
                $stack = [];
        }

        return $stack;
    }

    /**
     * 去重
     *
     * @param $result
     * @param $merge
     *
     * @return array
     */
    private static function unique($result, $merge)
    {
        if (!$merge) return $result;
        if (!is_array($merge)) $merge = [$merge];
        /** @var \DOMElement $item */
        foreach ($merge as $item) {

            $flag = false;
            /** @var \DOMElement $testOne */
            foreach ($result as $testOne) {
                if ($testOne->isSameNode($item)) {
                    $flag = true;
                    break;
                }
            }

            if ($flag === false) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * 解析jquery选择器
     *
     * @param $selector
     *
     * @return array
     */
    private static function parseSelector($selector)
    {
        // 多个空格合并一个空格  去除>+~两边空格 去除两端空格
        $selector = trim(preg_replace('@\s+@', ' ', preg_replace('@\s*(>|\\+|~)\s*@', '\\1', $selector)));

        $charList    = self::toSplit($selector);
        $charListLen = count($charList);

        // 解析： div .class #id  div~ip ,
        $i      = 0;
        $return = [];
        while ($i < $charListLen) {
            $char = $charList[$i]; // 第一个字符
            $tmp  = '';
            if (self::isChar($char) || $charList[$i] == '*') { // 第一个字符是普通字符 怎认为是 tagname
                while (isset($charList[$i]) && (self::isChar($charList[$i]) || in_array($charList[$i], ['-', '*']))) {
                    $tmp .= $charList[$i];
                    ++$i;
                }
                $return[] = $tmp;
            } elseif ($char == '#') { // ID 选择器
                $tmp = $char;
                ++$i;
                while (isset($charList[$i]) && (self::isChar($charList[$i]) || in_array($charList[$i], ['-']))) {
                    $tmp .= $charList[$i];
                    $i++;
                }
                $return[] = $tmp;
            } elseif ($char == '.') { // 类选择器
                $tmp = $char;
                ++$i;
                while (isset($charList[$i]) && (self::isChar($charList[$i]) || in_array($charList[$i], ['-']))) {
                    $tmp .= $charList[$i];
                    ++$i;
                }
                $return[] = $tmp;
            } elseif ($char == '>') {// 直接下级
                $tmp = $char;
                ++$i;
                $return[] = $tmp;
            } elseif ($char == '~') { // 同级所有
                $tmp = $char;
                ++$i;
                $return[] = $tmp;
            } elseif ($char == '+') { // 同级下一个
                $tmp = $char;
                ++$i;
                $return[] = $tmp;
            } elseif ($char == ':') {
                $tmp = $char;
                ++$i;
                while (isset($charList[$i]) && (self::isChar($charList[$i]) || in_array($charList[$i], ['-']))) {
                    $tmp .= $charList[$i];
                    ++$i;
                }

                if (isset($charList[$i]) && $charList[$i] == '(') {
                    $tmp   .= $charList[$i];
                    $stack = 1;
                    while (isset($charList[++$i])) {
                        $tmp .= $charList[$i];
                        if ($charList[$i] == '(') {
                            ++$stack;
                        } elseif ($charList[$i] == ')') {
                            --$stack;

                            if ($stack == 0) {
                                break;
                            }
                        }
                    }
                    ++$i;
                }
                $return[] = $tmp;
            } else {
                ++$i;
            }
        }

        return $return;
    }

    private function isTagName($str)
    {
        return preg_match('@^[\w|\||-]+$@', $str) > 0 || $str == '*';
    }

    /**
     * 是否是字符 字母数字下划线
     *
     * @param $char
     *
     * @return bool
     */
    protected static function isChar($char)
    {
        return mb_eregi('\w', $char);
    }

    /**
     * 分割单个字符
     *
     * @param $str
     *
     * @return array
     */
    private static function toSplit($str)
    {
        $len   = mb_strlen($str);
        $chars = [];
        for ($i = 0; $i < $len; $i++) {
            $chars[] = mb_substr($str, $i, 1);
        }

        return $chars;
    }

    /**
     * 打印调试信息
     *
     * @param $msg
     */
    public static function debug($msg)
    {
        if (self::$debug) {
            printf($msg . PHP_EOL);
        }
    }
}