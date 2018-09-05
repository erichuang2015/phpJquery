<?php
namespace Parse;

/**
 * User: xiangzhiping
 * Date: 2018/9/5
 */

class Jquery implements \ArrayAccess, \IteratorAggregate
{
    /**
     * @var DocumentQuery
     */
    public static $query;

    /**
     * @var \DOMNode[]
     */
    protected $nodeList;

    public function __construct($selector, $root = null)
    {
        if (is_string($selector)) {
            if (!self::$query || !self::$query instanceof DocumentQuery)
                throw new \Exception('please set Jquery::$query');
            $this->nodeList = self::$query->runSelector($selector, $root);
        } elseif ($selector instanceof \DOMElement) {
            $this->nodeList = [$selector];
        } elseif ($selector instanceof \DOMNodeList) {
            foreach ($selector as $item) {
                $this->nodeList[] = $item;
            }
        } elseif (is_array($selector)) {
            if (isset($selector[0]) && $selector[0] instanceof \DOMElement) {
                $this->nodeList = $selector;
            } else {
                $this->nodeList = [];
            }
        }
    }

    /**
     * 获取属性
     *
     * @param      $name
     * @param bool $one
     *
     * @return array|null
     */
    public function attr($name, $one = true)
    {
        if ($one == true) {
            return $this->nodeList ? $this->nodeList[0]->getAttribute($name) : null;
        } else {
            return array_map(function ($item) use ($name) {
                /**@var \DOMNode|\DOMElement $item */
                return $item->getAttribute($name);
            }, $this->nodeList);
        }
    }

    public function text($one = true)
    {
        if ($one == true) {
            return $this->nodeList ? $this->nodeList[0]->textContent : null;
        } else {
            return array_map(function ($item) {
                /**@var \DOMNode|\DOMElement $item */
                return $item->textContent;
            }, $this->nodeList);
        }
    }

    /**
     * 获取第一个节点
     *
     * @return \DOMNode|null
     */
    public function first()
    {
        return $this->nodeList ? $this->nodeList[0] : null;
    }

    /**
     * 获取节点数据
     *
     * @return array|\DOMNode[]
     */
    public function getElements()
    {
        return $this->nodeList;
    }

    /**
     * 判断当前节点是否包含后代节点选择器
     *
     * @param $selector
     *
     * @return bool
     */
    public function has($selector)
    {
        return $this->query($selector) ? true : false;
    }

    /**
     * 内部查找
     *
     * @param $selector
     *
     * @return array
     */
    public function query($selector)
    {
        return self::$query->runSelector($selector, $this->first());
    }

    public function offsetExists($offset)
    {
        return isset($this->nodeList[$offset]) ? true : false;
    }

    public function offsetGet($offset)
    {
        return $this->nodeList[$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        return $this->nodeList[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->nodeList[$offset]);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->nodeList);
    }
}