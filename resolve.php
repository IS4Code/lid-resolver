<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//

function report_error($code, $message)
{
  http_response_code($code);
  ?><!DOCTYPE html>
<html lang="en">
<head>
<title>lid: resolver</title>
<link rel="stylesheet" href="//is4.site/styles/terminal.css?theme=4">
</head>
<body>
<p>The input URI or its parts could not be processed.</p>
<p><strong><?=$message?></strong></p>
<p><a href=".">Back to the main page.</a></p>
</body>
</html><?php
  die;
}

if(isset($_SERVER['REDIRECT_URL']) && $_SERVER['REDIRECT_URL'] !== '/lid/resolve')
{
  $uri = substr($_SERVER['REQUEST_URI'], 1);
  $options = array();
}else if(isset($_GET['uri']))
{
  $uri = $_GET['uri'];
  unset($_GET['uri']);
  $options = $_GET;
}else{
  http_response_code(301);
  header('Location: .');
  die;
}

require_once '.internal.php';
require_once '.resolver.php';
require_once '.resolver_class.php';

$uri = analyze_uri($uri, $components, $identifier, $query);

$data = get_context();
$context = &$data['@context'];

$resolver = new Resolver($context, $options);

if(isset($query))
{
  $resolver->parse_query($query);
}

$reconstructed_uri = $uri;
$reconstructed_uri['scheme'] = 'lid';
foreach($options as $key => $value)
{
  $query[] = '_'.rawurlencode($key).'='.rawurlencode($value);
}
if(isset($query))
{
  $reconstructed_uri['query'] = implode('&', $query);
}
$reconstructed_uri['path'] = implode('/', array_merge(isset($uri['host']) ? array('') : array(), $components, array($identifier)));
  
$resolver->parse_properties($components);

$identifier = $resolver->parse_identifier($identifier);

list($sparql, $sparql_inner) = $resolver->build_query($uri, $components, $identifier);

$unresolved_prefixes = $resolver->unresolved_prefixes;

if(!empty($options['endpoint']))
{
  $uri['path'] = "/$options[endpoint]";
}else{
  if(@$options['method'] === 'triples')
  {
    $uri['path'] = '/.well-known/void';
  }else{
    if(strcasecmp(@$uri['host'], 'query.wikidata.org') === 0)
    {
      $uri['path'] = '/sparql';
    }else{
      $uri['path'] = '/sparql/';
    }
  }
}

if(!empty($options['scheme']))
{
  $uri['scheme'] = $options['scheme'];
}else{
  $uri['scheme'] = @$_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
}

require_once '.resolver_output.php';

switch(@$options['action'])
{
  case 'redirect':
    output_redirect($uri, $sparql, $sparql_inner, $options, $reconstructed_uri, $unresolved_prefixes);
    break;
  case 'debug':
    output_debug($uri, $sparql, $sparql_inner, $options, $reconstructed_uri, $unresolved_prefixes);
    break;
  case 'print':
    output_print($uri, $sparql, $sparql_inner, $options, $reconstructed_uri, $unresolved_prefixes);
    break;
  case 'describe':
    output_navigate($uri, $sparql, $sparql_inner, $options, $reconstructed_uri, $unresolved_prefixes, true);
    break;
  default:
    output_navigate($uri, $sparql, $sparql_inner, $options, $reconstructed_uri, $unresolved_prefixes, false);
    break;
}
