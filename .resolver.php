<?php

function unparse_url($uri)
{
  $scheme = isset($uri['scheme']) ? "$uri[scheme]:" : '';
  $start = isset($uri['host']) ? '//' : '';
  $host = @$uri['host'];
  $port = isset($uri['port']) ? ":$uri[port]" : '';
  $user = @$uri['user'];
  $pass = isset($uri['pass']) ? ":$uri[pass]" : '';
  $pass = ($user || $pass) ? "$pass@" : '';
  $path = @$uri['path'];
  $query = isset($uri['query']) ? "?$uri[query]" : '';
  $fragment = isset($uri['fragment']) ? "#$uri[fragment]" : '';
  return "$scheme$start$user$pass$host$port$path$query$fragment";
}

function array_any(&$array, $callable)
{
  foreach($array as $value)
  {
    if($callable($value)) return true;
  }
  return false;
}

function analyze_uri($uri, &$components, &$identifier, &$query)
{
  if(strpos($uri, '?') === false && strpos($uri, '#') === false)
  {
    $uri = "$uri?";
  }
  
  $uri = parse_url($uri);
  
  if($uri === false)
  {
    report_error(400, 'The URI is invalid!');
  }
  
  if(!isset($uri['scheme']))
  {
    report_error(400, 'The URI must be absolute and have the <mark>lid:</mark> scheme!');
  }
  
  if($uri['scheme'] !== 'lid')
  {
    $uri['scheme'] = htmlspecialchars($uri['scheme']);
    report_error(400, "Scheme must be <mark>lid:</mark> (was <q>$uri[scheme]</q>)!");
  }
  unset($uri['scheme']);
  
  $path = @$uri['path'];
  if(isset($uri['host']))
  {
    $path = substr($path, 1);
  }
  unset($uri['path']);
  
  $components = explode('/', $path);
  $identifier = array_pop($components);
  
  if(!empty($uri['query']))
  {
    $query = explode('&', $uri['query']);
  }
  unset($uri['query']);
  
  return $uri;
}

function concat_prefixed($a, $b)
{
  if(is_string($a)) return $a.$b;
  return array($a[0], $a[1].$b);
}

function validate_name($name)
{
  if(preg_match('/[<>{}|\\\^[\]` ]/', $name))
  {
    $name = htmlspecialchars($name);
    report_error(400, "Component contains invalid characters (name <q>$name</q>)!");
  }
}

$unresolved_prefixes = array();

function get_special_name($name)
{
  if(!is_string($name) && $name[0] === '_')
  {
    return $name[1];
  }
  return null;
}
