<?php

class SiriClient
{
protected $url;

protected $stops;
protected $vehicles;

function __construct($url)
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
		throw new Exception($error, $status);
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

$response=curl_exec($curl);
$status=curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error=curl_error($curl);
curl_close($curl);

$this->handleStatus($status, $error, $response);

return $response;
}

/**
 * VM
 **/
public function loadVehicles()
{
$r=$this->executeGET('vm');
$data=json_decode($r, false);
print_r($data);
if ($data['status']=='PENDING')
	return false;

file_put_contents('vm.json', json_encode($data, JSON_PRETTY_PRINT));

return true;
}

/**
 * SM
 **/
public function loadStops()
{
if (file_exists('sm.json'))
	$r=file_get_contents('sm.json');
else
	$r=$this->executeGET('sm');

$this->stops=json_decode($r, true);
ksort($this->stops);

if (!file_exists('sm.json'))
	file_put_contents('sm.json', json_encode($this->stops, JSON_PRETTY_PRINT));

return true;
}

public function loadStop($id)
{
$r=$this->executeGET('sm/'.$id);
$data=json_decode($r, false);

file_put_contents('sm-'.$id.'.json', json_encode($data, JSON_PRETTY_PRINT));

print_r($data);

return true;
}

} // php
?>
