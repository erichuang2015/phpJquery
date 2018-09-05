<?php
/**
 * User: xiangzhiping
 * Date: 2018/9/5
 */

/**
 * JQ短语法
 *
 * @param      $selector
 * @param null $root
 *
 * @return \Parse\Jquery
 */
function JQ($selector, $root = null)
{
    return new \Parse\Jquery($selector, $root);
}