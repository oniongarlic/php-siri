#!/usr/bin/php -q
<?php
require_once('../lib/SiriClient.php');

class StopData
{
public $r; // Line Ref
public $eta; // ETA on stop
public $m; // Monitored
public $d; // Destination
}

class StopMonitorUpdater
{
private $mqtt;
private $c;
private $pid;
private $sleepTime=4;
private $maxdata=4;
private $queue;
private $config;
private $location;
private $mstops;
private $srvtime;
private $lastUpdate;

function __construct(array $config)
{
$this->queue=array();
$this->lastUpdate=array();
$this->config=$config['Generic'];
$this->location=$this->config['location'];
$this->url=$this->config['url'];
// XXX use ini for server config

if (isset($this->config['stops']))
	$this->mstops=explode(',', $this->config['stops']);
else
	$this->mstops=false;

$this->mqtt=new Mosquitto\Client('talorg-siri-sm-updater', true);
$this->mqtt->setWill('siri/'.$this->location.'/active', 0, 1, true);

$this->c=new SiriClient($this->url);

$r=$this->connect();
if (!$r)
	echo "ConFail!";
}

private function connect()
{
$r=$this->mqtt->connect($this->config['mqtt_host'], 1883);
$this->mqtt->publish('siri/'.$this->location.'/active', 1);
return $r;
}

private function getStopTopic($location, $id, $offset, $item)
{
// siri/turku/sm/<stop-id>/<offset>/<data>
return sprintf('siri/%s/sm/%s/%d/%s', $location, $id, $offset, $item);
}

private function getStopNameTopic($location, $id)
{
// siri/turku/sm/<stop-id>
return sprintf('siri/%s/stops/%s', $location, $id);
}

/****
      [0] => stdClass Object
             (
                [recordedattime] => 1447066907
                [lineref] => 23
                [monitored] => 1
                [latitude] => 60.689833
                [longitude] => 22.441933
                [datedvehiclejourneyref] => 3936
                [originaimeddeparturetime] => 1447067400
                [destinationaimedarrivaltime] => 1447070160
                [destinationdisplay] => Kauppatori
                [aimedarrivaltime] => 1447068180
                [expectedarrivaltime] => 1447068180
                [aimeddeparturetime] => 1447068180
                [expecteddeparturetime] => 1447068180
             )
*****/

/**
 * id: stop ID
 * data: details
 */
private function publishStop($id, array $data)
{
$topic=$this->getStopTopic($this->location, $id, 0, 'json');
$this->mqtt->publish($topic, json_encode($data), 0, true);
$this->mqtt->loop(1000);
}

private function createStopData(array $datas)
{
$r=array();
$c=0;
foreach ($datas as $data) {
	$c++;
	if ($c>$this->maxdata)
		break;
	//$d=new DateTime($data->expectedarrivaltime);

	// We send the time in seconds to arrival
	$dif=$data->expectedarrivaltime-$data->recordedattime;

	// Display can't handle any "exotic" characters to transliterate to plain ASCII
	$tname=iconv("UTF-8", "ASCII//TRANSLIT", $data->destinationdisplay);

	$tmp=new StopData();
	// $tmp->jr=$data->datedvehiclejourneyref;
	$tmp->d=$tname;
	$tmp->eta=$dif;
	$tmp->r=$data->lineref;
	$tmp->m=$data->monitored ? 1 : 0;

	$r[]=$tmp;
}
return $r;
}

private function refreshStopData($sid)
{
// Initial load
$now=time();
$s=$this->c->getStop($sid);
if ($s==false || $now-$this->lastUpdate[$sid]>30) {
	echo "L: $sid\n";
	$this->c->loadStop($sid);
	$s=$this->c->getStop($sid);
	$this->lastUpdate[$sid]=$now;
} else {
	echo "S: $sid\n";
}

if ($s->status!='OK') {
	echo "E: $sid\n";
	return;
}
// Check timestamps, do we need to refresh
$r=$this->createStopData($s->result);
$this->publishStop($sid, $r);
file_put_contents($sid.'-mqtt.json', json_encode($r, JSON_PRETTY_PRINT));
}

private function refreshStopsData()
{
foreach ($this->mstops as $sid) {
	try {
		$this->refreshStopData($sid);
	} catch (Exception $e) {
		print_r($e);
	}
}
return true;
}

private function sendStopNames()
{
foreach ($this->stops as $id => $data) {
	if ($id=='')
		continue;
	printf("%s,%s\n", $id, $data['stop_name']);
	$this->mqtt->publish($this->getStopNameTopic($this->location, $id), $data['stop_name'], 1, 0);
	$this->mqtt->loop(1000);
}
echo "\n";
}

public function run()
{
echo "Stops data:";
$this->c->loadStops();
$this->stops=$this->c->getStops();
while (true) {
	echo ".";
	$this->refreshStopsData();
	$r=$this->mqtt->loop(1000);
	sleep(1);
	$r=$this->mqtt->loop(1000);
	sleep(1);
	$r=$this->mqtt->loop(1000);
	sleep(1);
}

}

} // class

//*************************************************

if (file_exists("config.ini"))
	$config=parse_ini_file("config.ini", true);
else
	die("Configuration file config.ini is missing\n");

$app=new StopMonitorUpdater($config);
$app->run();

?>
