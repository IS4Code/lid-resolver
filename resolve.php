<?php

///*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//*/

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
<p><mark><?=$message?></mark></p>
<p><a href=".">Back to the main page.</a></p>
</body>
</html><?php
  die;
}

if(isset($_SERVER['REDIRECT_URL']))
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

$uri = parse_url($uri);

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

if(!isset($uri['scheme']))
{
  report_error(400, 'The URI must be absolute and have the <mark>lid:</mark> scheme!');
}

if($uri['scheme'] !== 'lid')
{
  $uri['scheme'] = htmlspecialchars($uri['scheme']);
  report_error(400, "Scheme must be <mark>lid:</mark> (was <q>$uri[scheme]</q>)!");
}

$uri['scheme'] = 'http';
$path = @$uri['path'];
if(isset($uri['host']))
{
  $path = substr($path, 1);
}
$uri['path'] = '/sparql/';

$components = explode('/', $path);
$identifier = array_pop($components);

require '.internal.php';
$data = get_context();
$context = &$data['@context'];

function concat($a, $b)
{
  if(is_string($a)) return $a.$b;
  return array($a[0], $a[1].$b);
}

function resolve_name($name, $allowEmpty = false)
{
  global $context;
  $qname = explode(':', $name, 2);
  $qname[0] = urldecode($qname[0]);
  if(isset($qname[1]))
  {
    $qname[1] = urldecode($qname[1]);
    list($prefix, $local) = $qname;
    if(isset($context[$prefix]))
    {
      return concat($context[$prefix], $local);
    }else if(!preg_match('/^(|[a-zA-Z]([-a-zA-Z0-9_.]*[-a-zA-Z0-9_])?)$/', $prefix))
    {
      $prefix = htmlspecialchars($prefix);
      report_error(400, "An unknown prefix contains invalid characters (prefix <q>$prefix</q>)!");
    }else{
      return $qname;
    }
  }else if(empty($name))
  {
    if($allowEmpty) return null;
    report_error(400, "URI component must not be empty!");
  }else if(strpos($qname[0], ':') === false)
  {
    $qname[0] = htmlspecialchars($qname[0]);
    report_error(400, "URI component must be a prefixed name or an absolute URI (was <q>$qname[0]</q>)!");
  }else{
    return $qname[0];
  }
}

if(!empty($uri['query']))
{
  foreach(explode('&', $uri['query']) as $part)
  {
    $part = explode('=', $part, 2);
    $key = urldecode($part[0]);
    $value = @$part[1];
    if(substr($part[0], 0, 1) === "_")
    {
      $options[substr($key, 1)] = urldecode($value);
    }else if(isset($part[1]))
    {
      $value = resolve_name($value, true);
      if(isset($context[$key]) && (((!isset($options['simple']) || isset($options['infer'])) ? $key === 'rdfs' : false) || ((!isset($options['simple']) || !isset($options['first']) || isset($options['infer'])) ? $key === 'owl' : false)) && $context[$key] !== $value)
      {
        $key = htmlspecialchars($key);
        $value = htmlspecialchars(is_string($value) ? $value : "$value[0]:$value[1]");
        report_error(400, empty($value) ? "This prefix must not be undefined (prefix <q>$key</q>)!" : "This prefix must not be redefined (prefix <q>$key</q>, value <q>$value</q>)!");
      }
      if($value === null)
      {
        unset($context[$key]);
      }else{
        $context[$key] = $value;
      }
    }else{
      $part[0] = htmlspecialchars($part[0]);
      report_error(400, "Query component that does not start on _ must be assigned a value (variable <q>$part[0]</q>)!");
    }
  }
}
unset($uri['query']);

array_walk($components, function(&$value)
{
  if(substr($value, 0, 1) === "'")
  {
    $value = array(resolve_name(substr($value, 1)), true);
  }else{
    $value = array(resolve_name($value), false);
  }
});

$identifier = explode('@', $identifier, 2);
if(isset($identifier[1]))
{
  $language = urldecode($identifier[1]);
  if(empty($language))
  {
    $identifier = resolve_name($identifier[0]);
  }else if(!preg_match('/^[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*$/', $language))
  {
    unset($language);
    $datatype = resolve_name($identifier[1]);
    $identifier = urldecode($identifier[0]);
  }else{
    $identifier = urldecode($identifier[0]);
  }
}else{
  $identifier = urldecode($identifier[0]);
}


/*
var_dump($uri);
var_dump($components);
var_dump($identifier);
var_dump($language);
var_dump($datatype);
*/

function validate_name($name)
{
  if(preg_match('/[<>{}|\\\^[\]` ]/', $name))
  {
    $name = htmlspecialchars($name);
    report_error(400, "Component contains invalid characters (name <q>$name</q>)!");
  }
}

function format_name($name)
{
  static $escape = '_~.-!$&\'()*+,;=/?#@%';
  if(is_string($name))
  {
    validate_name($name);
    return "<$name>";
  }
  validate_name($name[1]);
  return $name[0].':'.addcslashes($name[1], $escape);
}

$query = array();

function get_query($query)
{
  global $options;
  $query = array('query' => $query);
  foreach($options as $key => $value)
  {
    if(substr($key, 0, 1) === '_')
    {
      $key = substr($key, 1);
      if($key === 'query')
      {
        report_error(400, "This query parameter must not be redefined (name <q>$name</q>)!");
      }
      $query[$key] = $value;
    }
  }
  return $query;
}

if(isset($options['print']))
{
  $uriquery = get_query('');
  unset($uriquery['query']);
  $uri['query'] = http_build_query($uriquery, null, '&');
  if(empty($uri['query']))
  {
    unset($uri['query']);
  }
  $query[] = '# This query would be sent to '.unparse_url($uri);
  $query[] = '';
}

if(!isset($options['simple']) || isset($options['infer']))
{
  $query[] = 'PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>';
}
if(!isset($options['simple']) || !isset($options['first']) || isset($options['infer']))
{
  $query[] = 'PREFIX owl: <http://www.w3.org/2002/07/owl#>';
  $query[] = '';
}
if(isset($options['first']))
{
  $query[] = 'DESCRIBE ?s0';
}else{
  $query[] = 'DESCRIBE ?s';
}
$query[] = 'WHERE {';

if(!isset($options['simple']))
{
  $any = false;
  foreach(array_unique($components, SORT_REGULAR) as $index => list($name, $reverse))
  {
    if($name == 'http://www.w3.org/2002/07/owl#sameAs') continue;
    $any = true;
    $name = format_name($name);
    $query[] = '  FILTER EXISTS {';
    $query[] = '    {';
    $query[] = '      '.$name.' (rdfs:subPropertyOf|owl:equivalentProperty|^owl:equivalentProperty)*/a/(rdfs:subClassOf|owl:equivalentClass|^owl:equivalentClass)* owl:'.($reverse ? '' : 'Inverse').'FunctionalProperty .';
    $query[] = '    } UNION {';
    $query[] = '      '.$name.' (rdfs:subPropertyOf|owl:equivalentProperty|^owl:equivalentProperty)*/owl:inverseOf/(rdfs:subClassOf|owl:equivalentClass|^owl:equivalentClass)*/a/(rdfs:subClassOf|owl:equivalentClass|^owl:equivalentClass)* owl:'.($reverse ? 'Inverse' : '').'FunctionalProperty .';
    $query[] = '    }';
    $query[] = '  }';
  }
  if($any)
  {
    $query[] = '';
  }
}

if(isset($language) && empty($language))
{
  $identifier = format_name($identifier);
}else{
  $identifier = '"'.addslashes($identifier).'"';
  if(isset($language))
  {
    $identifier = "$identifier@$language";
  }else if(isset($datatype))
  {
    $identifier = "$identifier^^".format_name($datatype);
  }else{
    $filter = $identifier;
    $identifier = '?id';
  }
}

if(!isset($options['first']))
{
  $query[] = '  ?s (owl:sameAs|^owl:sameAs)* ?s0 .';
  $query[] = '';
}

if(!isset($options['infer']))
{
  array_walk($components, function(&$value)
  {
    $name = format_name($value[0]);
    if($value[1]) $name = "^$name";
    $value = $name;
  });

  $query[] = '  ?s0 '.implode('/', $components)." $identifier .";
}else{
  foreach($components as $index => $value)
  {
    if($index >= 1)
    {
      $query[] = "  ?r${index} (owl:sameAs|^owl:sameAs)* ?s${index} .";
    }
    $subj = "?s${index}";
    $obj = '?r'.($index + 1);
    $query[] = '  {';
    $query[] = "    SELECT $subj $obj";
    $query[] = '    WHERE {';
    $query[] = '      OPTIONAL {';
    $query[] = '        ?p'.$index.' (rdfs:subPropertyOf|owl:equivalentProperty|^owl:equivalentProperty)* '.format_name($value[0]).' .';
    if($value[1])
    {
      $query[] = "        $obj ?p$index $subj .";
    }else{
      $query[] = "        $subj ?p$index $obj .";
    }
    $query[] = '      }';
    $query[] = '      OPTIONAL {';
    $query[] = '        ?p'.$index.' (rdfs:subPropertyOf|owl:equivalentProperty|^owl:equivalentProperty)*/owl:inverseOf/(rdfs:subPropertyOf|owl:equivalentProperty|^owl:equivalentProperty)* '.format_name($value[0]).' .';
    $subj = "?s${index}";
    $obj = '?r'.($index + 1);
    if($value[1])
    {
      $query[] = "        $subj ?p$index $obj .";
    }else{
      $query[] = "        $obj ?p$index $subj .";
    }
    $query[] = '      }';
    $query[] = "      FILTER (bound($subj) && bound($obj))";
    $query[] = '    }';
    $query[] = '  }';
  }
  $query[] = '  ?r'.count($components)." (owl:sameAs|^owl:sameAs)* $identifier .";
}

if(isset($filter))
{
  $query[] = '  FILTER (isLiteral(?id) && str(?id) = '.$filter.')';
}

$query[] = '}';
if(isset($options['first']))
{
  $query[] = 'LIMIT 1';
}
$query[] = '';

if(!isset($options['print']))
{
  array_walk($query, function(&$value)
  {
    $value = trim($value);
  });
}

$query = implode("\n", $query);

if(isset($options['print']))
{
  if(!isset($options['html']))
  {
    header('Content-Type: application/sparql-query');
    header('Content-Disposition: inline; filename="query.sparql"');
    echo $query;
  }else{
    $query = htmlspecialchars($query);
    ?><!DOCTYPE html>
<html lang="en">
<head>
<title>lid: resolver</title>
<link rel="stylesheet" href="//is4.site/styles/terminal.css?theme=4">
</head>
<body>
<pre style="white-space:pre-wrap"><?=$query?></pre>
<p style="float:left"><a href=".">Back to the main page.</a></p>
<form method="POST" action="http://www.sparql.org/validate/query" style="float:right">
<textarea name="query" style="display:none"><?=$query?></textarea>
<input type="hidden" name="languageSyntax" value="SPARQL">
<input type="hidden" name="outputFormat" value="sparql">
<input type="submit" value="Validate">
</form>
</body>
</html><?php
  }
}else{
  $uri['query'] = http_build_query(get_query($query), null, '&');
  http_response_code(303);
  header('Location: '.unparse_url($uri));
}
