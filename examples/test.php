#!/usr/bin/php -q
<?php
require_once('../lib/SiriClient.php');

$c=new SiriClient('http://data.foli.fi/siri/');

$c->loadStops();
$c->loadStop(1170);
$vmr=$c->loadVehicles();
if ($vmr===SiriClient::VM_PENDING)
	echo "VM data is pending\n";

?>
