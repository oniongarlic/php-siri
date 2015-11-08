<?php

class SiriClientException extends Exception { }

class SiriClient
{
protected $url;

protected $stops;
protected $vehicles;

const VM_OK=true;
const VM_PENDING=1;
const VM_ERROR=2;

const SM_OK=true;
const SM_ERROR=false;

public function __construct($url)
{
$this->url=$url;
}

private function getcurl($url)
{
$curl=curl_init($url);
$header=array( 'Content-Type: application/json');
$options=array(
	CURLOPT_HEADER => FALSE,
	CURLOPT_RETURNTRANSFER => TRUE,
	CURLINFO_HEADER_OUT => TRUE,
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
public function loadVehicles()
{
$r=$this->executeGET('vm');
$data=json_decode($r, true);
print_r($data);
if ($data['status']=='PENDING')
	return self::VM_PENDING;

file_put_contents('vm.json', json_encode($data, JSON_PRETTY_PRINT));

return self::VM_OK;
}

/**
 * SM - Stop Monitoring
 **/
public function loadStops($cache=true)
{
if (file_exists('sm.json') && $cache===true)
	$r=file_get_contents('sm.json');
else
	$r=$this->executeGET('sm');

$this->stops=json_decode($r, true);
ksort($this->stops);

if (!file_exists('sm.json'))
	file_put_contents('sm.json', json_encode($this->stops, JSON_PRETTY_PRINT));

return SM_OK;
}

public function loadStop($id)
{
$r=$this->executeGET('sm/'.$id);
$data=json_decode($r, false);

file_put_contents('sm-'.$id.'.json', json_encode($data, JSON_PRETTY_PRINT));

print_r($data);

return self::SM_OK;
}

} // php
?>
