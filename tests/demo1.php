<?php
/**
 * User: xiangzhiping
 * Date: 2018/9/5
 */

require_once '../vendor/autoload.php';


$html            = file_get_contents('./111.html');
//DOMQuery::$debug = true;
\Parse\Jquery::$query   = new Parse\DocumentQuery($html);

$jq = JQ('.provincetable .provincetr');
$list = [];
foreach($jq as $item){
    foreach(JQ('td:parent', $item) as $tdItem){
        if(trim($tdItem->nodeValue)){
            $obj = JQ($tdItem);
            $a = JQ($obj->query('a'));
            $list[] = [
                'href' => $a->first() ? $a->attr('href') : '',
                'name' => $a->first() ? $a->text() : $obj->text(),
            ];
        }
    }
}

print_r($list);