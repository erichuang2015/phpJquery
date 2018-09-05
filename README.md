#### 本类库暂时支持HTML解析   TOTO: XML兼容
#### 可以像JQUERY 那样方便获取DOM节点数据

#### 示例：
```

\Parse\Jquery::$query   = new Parse\DocumentQuery($html);
$jq = JQ('div .class a');

// 获取a标签链接
foreach($jq as $elementNode){
    var_dump(JQ($elementNode)->attr('href'));
    var_dump(JQ($elementNode)->text());
}

// 获取所有标签Node:
$nodeList = $jq->getElements();

```