<?php

function get_updated_json_file($file)
{
  if(file_exists($file))
  {
    if(time() - filemtime($file) >= (48 * 60 + rand(-120, 120)) * 60)
    {
      touch($file);
    }else if(($data = json_decode(file_get_contents($file), true)) !== null)
    {
      return $data;
    }
  }
  return null;
}

function get_common_context()
{
  static $cache_file = __DIR__ . '/.common.json';
  
  if(($data = get_updated_json_file($cache_file)) !== null)
  {
    return $data;
  }
  
  $info = stream_context_create(array('http' => array('user_agent' => 'IS4 lid: resolver', 'header' => 'Connection: close\r\n')));
  
  $json = file_get_contents('http://prefix.cc/context.jsonld', false, $info);
  if($json === false)
  {
    return null;
  }
  $data = json_decode($json, true);
  if($data === null)
  {
    return null;
  }
  if(!isset($data['@context']))
  {
    return null;
  }
  if(file_exists($cache_file))
  {
    file_put_contents($cache_file, json_encode($data, JSON_UNESCAPED_SLASHES));
  }
  return $data;
}

function get_context()
{
  static $cache_file = __DIR__ . '/.context.json';
  
  if(($data = get_updated_json_file($cache_file)) !== null)
  {
    return $data;
  }
  
  $info = stream_context_create(array('http' => array('user_agent' => 'IS4 lid: resolver', 'header' => 'Connection: close\r\n')));
  
  $json = file_get_contents('http://www.w3.org/2013/json-ld-context/rdfa11', false, $info);
  if($json === false)
  {
    http_response_code(503);
    die;
  }
  $data = json_decode($json, true);
  if($data === null)
  {
    $data = array();
  }
  if(!isset($data['@context']))
  {
    $data['@context'] = array();
  }
  $context = &$data['@context'];
  
  libxml_set_streams_context($info);
  $xml = new DOMDocument;
  $xml->preserveWhiteSpace = false;
  if($xml->load('http://www.iana.org/assignments/uri-schemes/uri-schemes.xml') === false)
  {
    http_response_code(503);
    die;
  }
  $xpath = new DOMXPath($xml);
  $xpath->registerNamespace('reg', 'http://www.iana.org/assignments');
  
  foreach($context as $key => $value)
  {
    $char = substr($value, -1);
    if($char !== '#' && $char !== '/')
    {
      unset($context[$key]);
    }
  }
  
  foreach($xpath->query('//reg:record[reg:status = "Permanent"]/reg:value/text()') as $scheme)
  {
    $name = trim($scheme->wholeText);
    $context[$name] = "$name:";
  }
  
  foreach($xpath->query('//reg:record[reg:status != "Permanent"]/reg:value/text()') as $scheme)
  {
    $name = trim($scheme->wholeText);
    if(!isset($context[$name]))
    {
      $context[$name] = "$name:";
    }
  }
  
  $context['rdf'] = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
  $context['rdfs'] = 'http://www.w3.org/2000/01/rdf-schema#';
  $context['owl'] = 'http://www.w3.org/2002/07/owl#';
  $context['skos'] = 'http://www.w3.org/2004/02/skos/core#';
  $context['xsd'] = 'http://www.w3.org/2001/XMLSchema#';
  
  foreach(array('http', 'https', 'urn', 'tag', 'mailto', 'lid') as $name)
  {
    $context[$name] = "$name:";
  }
  
  $context['base'] = '';
  ksort($context);
  
  if(file_exists($cache_file))
  {
    file_put_contents($cache_file, json_encode($data, JSON_UNESCAPED_SLASHES));
  }
  
  return $data;
}