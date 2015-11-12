<?php

class SiriClientException extends Exception { }
class StopsNotLoadedException extends Exception { }
class StopNotAvailableException extends Exception { }

class SiriClient
{
protected $url;
protected $cachedir='./';
protected $stops;
protected $vehicles;
protected $stop;
protected $ua="PHP-SIRI Library/0.0.1; (+http://www.tal.org/projects/php-siri-library; onion@tal.org)";

const VM_OK=true;
const VM_PENDING=1;
const VM_ERROR=2;

const SM_OK=true;
const SM_ERROR=false;

public function __construct($url)
{
$this->url=$url;
$this->stop=array();
}

private function getcurl($url)
{
$curl=curl_init($url);
$header=array( 'Content-Type: application/json');
$options=array(
	CURLOPT_HEADER => FALSE,
	CURLOPT_RETURNTRANSFER => TRUE,
	CURLINFO_HEADER_OUT => TRUE,
	CURLOPT_USERAGENT => $this->ua,
	CURLOPT_HTTPHEADER => $header);
curl_setopt_array($curl, $options);
return $curl;
}

protected function handleStatus($status, $error, $response)
{
switch ($status) {
	case 0:
	case 401:
	case 403:
	case 404:
	case 500:
		throw new SiriClientException($error, $status);
	case 200:
		return true;
	default:
		throw new Exception($response, $status);
}

}


protected function executeGET($endpoint, array $query=null)
{
$url=$this->url.$endpoint;
$q=array();
if (is_array($query))
	$q=array_merge($query, $q);

$url.='?'.http_build_query($q);

$curl=$this->getcurl($url);
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($curl, CURLOPT_ENCODING, '');

$response=curl_exec($curl);
$status=curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error=curl_error($curl);
curl_close($curl);

$this->handleStatus($status, $error, $response);

return $response;
}

/**
 * VM - Vehicle Monitoring
 **/
public function loadVehicles($cache=true)
{
$r=$this->executeGET('vm');
$data=json_decode($r, true);
if ($data['status']=='PENDING')
	return self::VM_PENDING;

if ($cache)
	file_put_contents($this->cachedir.'vm.json', json_encode($data, JSON_PRETTY_PRINT));

return self::VM_OK;
}

/**
 * SM - Stop Monitoring
 **/
public function loadStops($cache=true)
{
if (file_exists($this->cachedir.'sm.json') && $cache===true)
	$r=file_get_contents($this->cachedir.'sm.json');
else
	$r=$this->executeGET('sm');

$this->stops=json_decode($r, true);
ksort($this->stops);

if (!file_exists('sm.json') && $cache)
	file_put_contents($this->cachedir.'sm.json', json_encode($this->stops, JSON_PRETTY_PRINT));

return self::SM_OK;
}

public function hasStops()
{
return count($this->stops)>0;
}

public function getStops()
{
if ($this->hasStops()==false)
	throw new SiriClientException('Stops are not loaded');

return $this->stops;
}

public function loadStop($id, $cache=true)
{
if ($this->hasStops()==false)
	throw new SiriClientException('Stops are not loaded');
if (!array_key_exists($id, $this->stops))
	throw new SiriClientException('Invalid stop ID');

$r=$this->executeGET('sm/'.$id);
$data=json_decode($r, false);

if ($cache)
	file_put_contents($this->cachedir.'sm-'.$id.'.json', json_encode($data, JSON_PRETTY_PRINT));

$this->stop[$id]=$data;

return self::SM_OK;
}

public function getStop($id)
{
return array_key_exists($id, $this->stop) ? $this->stop[$id] : false;
}

} // php
?>
