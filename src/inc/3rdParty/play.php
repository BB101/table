<?php
require_once('php-sql-parser.php');

$sql = "WHERE (field1 = 'value' AND (field2 = 'value2' OR table2.field1 = table.field) OR table3.field IN (1,2,3)";
$parser = new PHPSQLParser($sql);

echo $sql."<br/><pre>";
print_r($parser->parsed);

$sql = "ORDER BY table2.field DESC, IF(table1.field='value',1,table2.value2)";
echo $sql."<br/><pre>";
print_r($parser->parsed);
?>