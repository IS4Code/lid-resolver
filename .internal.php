<?php

require_once '.resolver.php';

function get_updated_json_file($file, &$renew)
{
  if(file_exists($file))
  {
    if(time() - filemtime($file) >= (48 * 60 + rand(-120, 120)) * 60)
    {
      touch($file);
      $renew = true;
    }else{
      $renew = false;
    }
    $data = json_decode(file_get_contents($file), true);
    if($data === null)
    {
      $renew = true;
    }else{
      return $data;
    }
  }
  return array('@context' => array());
}

function flush_output()
{
  header('Content-Encoding: none');
  header('Content-Length: '.ob_get_length());
  header('Connection: close');
  ob_end_flush();
  ob_flush();
  flush();
}

function get_common_context()
{
  static $cache_file = __DIR__ . '/.common.json';
  
  $data = get_updated_json_file($cache_file, $renew);
  if($renew)
  {
    ob_start();
    register_shutdown_function(function($cache_file)
    {
      flush_output();
      
      $info = stream_context_create(array('http' => array('user_agent' => 'IS4 lid: resolver', 'header' => 'Connection: close\r\n')));
      
      $json = file_get_contents('http://prefix.cc/context.jsonld', false, $info);
      if($json === false)
      {
        return;
      }
      $data = json_decode($json, true);
      if($data === null)
      {
        return;
      }
      if(!isset($data['@context']))
      {
        return;
      }
      file_put_contents($cache_file, json_encode($data, JSON_UNESCAPED_SLASHES));
    }, $cache_file);
  }
  return $data;
}

function get_context()
{
  static $cache_file = __DIR__ . '/.context.json';
  
  $data = get_updated_json_file($cache_file, $renew);
  if($renew)
  {
    ob_start();
    register_shutdown_function(function($cache_file)
    {
      flush_output();
      
      $info = stream_context_create(array('http' => array('user_agent' => 'IS4 lid: resolver', 'header' => 'Connection: close\r\n')));
      
      $json = file_get_contents('http://www.w3.org/2013/json-ld-context/rdfa11', false, $info);
      if($json === false)
      {
        return;
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
        return;
      }
      $xpath = new DOMXPath($xml);
      $xpath->registerNamespace('reg', 'http://www.iana.org/assignments');
      
      foreach($context as $key => $value)
      {
        if($key !== '' && is_string($value) && is_absolute_uri($value))
        {
          $char = substr($value, -1);
          if($char === '#' || $char === '/' || $char === ':')
          {
            continue;
          }
        }
        unset($context[$key]);
      }
      
      foreach($xpath->query('//reg:record[reg:status = "Permanent"]/reg:value/text()') as $scheme)
      {
        $name = trim($scheme->wholeText);
        $context[$name] = "$name:";
      }
      
      foreach($xpath->query('//reg:record[reg:status != "Permanent"]/reg:value/text()') as $scheme)
      {
        $name = trim($scheme->wholeText);
        if(!isset($context[$name]) && strlen($name) >= 4)
        {
          $context[$name] = "$name:";
        }
      }
      
      $context['rdf'] = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
      $context['rdfs'] = 'http://www.w3.org/2000/01/rdf-schema#';
      $context['owl'] = 'http://www.w3.org/2002/07/owl#';
      $context['skos'] = 'http://www.w3.org/2004/02/skos/core#';
      $context['xsd'] = 'http://www.w3.org/2001/XMLSchema#';
      
      if(!isset($context['xs']))
      {
        $context['xs'] = $context['xsd'];
      }
      
      foreach(array('http', 'https', 'urn', 'tag', 'mailto', 'data', 'file', 'ftp', 'lid') as $name)
      {
        $context[$name] = "$name:";
      }
      
      $context['base'] = '';
      ksort($context);
      
      if(file_exists($cache_file))
      {
        file_put_contents($cache_file, json_encode($data, JSON_UNESCAPED_SLASHES));
      }
    }, $cache_file);
  }
  
  return $data;
}