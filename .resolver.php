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

function uridecode($uri)
{
  return urldecode(str_replace('+', '%2B', $uri));
}

function get_query_string($query)
{
  return http_build_query($query, null, '&', PHP_QUERY_RFC3986);
}

function array_any(&$array, $callable)
{
  foreach($array as $value)
  {
    if($callable($value)) return true;
  }
  return false;
}

function is_option($options, $key)
{
  if(isset($options[$key]))
  {
    $value = $options[$key];
    return !($value === 'off' || $value === 'false' || $value === '0' || $value === 0);
  }
  return false;
}

function parse_uri($uri)
{
  if(strpos($uri, '?') === false && strpos($uri, '#') === false)
  {
    $uri = "$uri?";
  }
  
  $result = parse_url($uri);
  
  if(substr($uri, -1) === '#')
  {
    $result['fragment'] = '';
  }
  
  return $result;
}

function analyze_uri($uri, &$components, &$identifier, &$query)
{
  $uri = parse_uri($uri);
  
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
    $scheme = htmlspecialchars($uri['scheme']);
    report_error(400, "Scheme must be <mark>lid:</mark> (was <q>$scheme</q>)!");
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

function is_absolute_uri($uri)
{
  return preg_match('/^[a-z-A-Z][a-z-A-Z0-9+.-]*:/', $uri);
}

function is_absolute_uri_sparql($uri)
{
  //return strpos($uri, ':') !== false;
  return preg_match('/^[^\/?#]*:/', $uri);
}

function concat_prefixed($a, $b)
{
  if(is_string($a))
  {
    $b = $a.$b;
    if(!is_absolute_uri_sparql($a) && is_absolute_uri_sparql($b))
    {
      $a = htmlspecialchars($a);
      $b = htmlspecialchars($b);
      report_error(400, "Absolute URI must not be produced from a prefix denoting a relative URI (<q>$b</q> produced from <q>$a</q>)!");
    }
    if($a === '')
    {
      return array(null, $b);
    }
    return $b;
  }
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

function get_special_name($name)
{
  if(!is_string($name) && $name[0] === '_')
  {
    return $name[1];
  }
  return null;
}

function create_query_array($query, $options)
{
  $arr = array();
  if($query !== null)
  {
    $arr['query'] = $query;
  }
  foreach($options as $key => $value)
  {
    if(substr($key, 0, 1) === '_')
    {
      $key = substr($key, 1);
      if($key === 'query')
      {
        report_error(400, "This query parameter must not be redefined (name <q>$name</q>)!");
      }
      $arr[$key] = $value;
    }
  }
  return $arr;
}
