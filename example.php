<?php
include_once('libs/include.php');

$resPerson = $Model->Person->find(array('id'=>123), array('order'=>'id DESC', 'limit'=>100, 'fields'=>array('colum1', 'column2')));
$person = $resPerson->getArray();
pr($person);
