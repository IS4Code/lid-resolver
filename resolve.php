<?php

/*
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

if(strpos($uri, '?') === false && strpos($uri, '#') === false)
{
  $uri = "$uri?";
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
      report_error(400, "An undefined prefix contains invalid characters (prefix <q>$prefix</q>)!");
    }else{
      $context[$prefix] = array($prefix, '');
      return $qname;
    }
  }else if(empty($name))
  {
    if($allowEmpty) return null;
    report_error(400, "URI component must be a prefixed name or an absolute URI (was empty)!");
  }else if(strpos($qname[0], ':') === false)
  {
    switch($qname[0])
    {
      case 'a':
        return 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
      case 'uri':
        return array('_', 'uri');
    }
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
    if(preg_match('/^(?:[a-zA-Z]{1,8}|\*)(-(?:[a-zA-Z0-9]{1,8}|\*))*$/', $language))
    {
      $langRange = $language;
    }else{
      $datatype = resolve_name($identifier[1]);
    }
    unset($language);
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

$unresolved_prefixes = array();

function get_special_name($name)
{
  if(!is_string($name) && $name[0] == '_')
  {
    return $name[1];
  }
  return null;
}

function format_name($name)
{
  static $escape = '_~.-!$&\'()*+,;=/?#@%';
  global $unresolved_prefixes;
  if(is_string($name))
  {
    validate_name($name);
    return "<$name>";
  }
  if(($special = get_special_name($name)) !== null)
  {
    $special = htmlspecialchars($special);
    report_error(400, "Special name <q>$special</q> was used in an unsupported position!");
  }
  validate_name($name[1]);
  $unresolved_prefixes[$name[0]] = null;
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
}

function use_prefix($name, &$prefix)
{
  global $context;
  $suffix = '';
  while(isset($context[$name.$suffix]) && !is_string($context[$name.$suffix]))
  {
    $suffix++;
  }
  $prefix = $name.$suffix;
}

//use_prefix('rdf', $rdf);
//use_prefix('xsd', $xsd);

if(isset($options['check']) || isset($options['infer']))
{
  use_prefix('rdfs', $rdfs);
  $query[] = "PREFIX $rdfs: <http://www.w3.org/2000/01/rdf-schema#>";
}
if(isset($options['check']) || isset($options['unify_owl']) || isset($options['infer']))
{
  use_prefix('owl', $owl);
  $query[] = "PREFIX $owl: <http://www.w3.org/2002/07/owl#>";
}
if(isset($options['unify_skos']))
{
  use_prefix('skos', $skos);
  $query[] = "PREFIX $skos: <http://www.w3.org/2004/02/skos/core#>";
}

if(isset($rdf) || isset($rdfs) || isset($owl) || isset($skos) || isset($xsd))
{
  $query[] = '';
}

$identifier_is_literal = !(isset($language) && empty($language));

if(!$identifier_is_literal)
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
    if(isset($langRange))
    {
      $langRange = '"'.addslashes($langRange).'"';
    }
    $filter = $identifier;
    $identifier = '?id';
  }
}

$initial = '?s';
switch(@$options['form'])
{
  case 'select':
    break;
  case 'describe':
    $query[] = "DESCRIBE $initial";
    break;
  default:
    $query[] = 'CONSTRUCT {';
    foreach($components as $index => $value)
    {
      $subj = $index == 0 ? $initial : "_:s$index";
      $obj = $index == count($components) - 1 ? $identifier : '_:s'.($index + 1);
      $name = format_name($value[0]);
      if($value[1])
      {
        $query[] = "  $obj $name $subj .";
      }else{
        $query[] = "  $subj $name $obj .";
      }
    }
    $query[] = '}';
    break;
}

if(@$options['form'] !== 'select')
{
  $query[] = "WHERE {";
  $query2 = array();
}else{
  $query2 = &$query;
}

if(isset($options['unify_owl']))
{
  if(isset($options['unify_skos']))
  {
    $unify_path = "($owl:sameAs|^$owl:sameAs|$skos:exactMatch|^$skos:exactMatch)*";
  }else{
    $unify_path = "($owl:sameAs|^$owl:sameAs)*";
  }
}else if(isset($options['unify_skos']))
{
  $unify_path = "($skos:exactMatch|^$skos:exactMatch)*";
}

if(!isset($filter))
{
  $query2[] = "SELECT DISTINCT $initial";
  $query2[] = "WHERE {";
}else if(empty($components) && !isset($unify_path))
{
  $query2[] = "SELECT DISTINCT $identifier";
  $query2[] = "WHERE {";
}else{
  $query2[] = "SELECT DISTINCT $initial $identifier";
  $query2[] = "WHERE {";
}

$subproperty_path = "($rdfs:subPropertyOf|$owl:equivalentProperty|^$owl:equivalentProperty)*";
$inverse_path = "/$owl:inverseOf/($rdfs:subPropertyOf|$owl:equivalentProperty|^$owl:equivalentProperty)*";
if(isset($options['inverse']))
{
  $additional_path = "/($owl:inverseOf/($rdfs:subPropertyOf|$owl:equivalentProperty|^$owl:equivalentProperty)*/$owl:inverseOf/($rdfs:subPropertyOf|$owl:equivalentProperty|^$owl:equivalentProperty)*)*";
}else{
  $additional_path = '';
}
$infer_path = "$subproperty_path$additional_path";
$infer_inverse_path = "$subproperty_path$inverse_path$additional_path";

if(isset($options['check']))
{
  $any = false;
  $subclass_path = "($rdfs:subClassOf|$owl:equivalentClass|^$owl:equivalentClass)*";
  foreach(array_unique($components, SORT_REGULAR) as $index => list($name, $reverse))
  {
    if($name == 'http://www.w3.org/2002/07/owl#sameAs') continue;
    if(get_special_name($name) == 'uri')
    {
      if($reverse)
      {
        report_error(400, "Special property <q>uri</q> is not functional!");
      }else{
        continue;
      }      
    }    
    $any = true;
    $name = format_name($name);
    $query2[] = '  FILTER EXISTS {';
    $query2[] = '    {';
    $query2[] = "      $name $infer_path/a/$subclass_path $owl:".($reverse?'':'Inverse').'FunctionalProperty .';
    $query2[] = '    } UNION {';
    $query2[] = "      $name $infer_inverse_path/a/$subclass_path $owl:".($reverse?'Inverse':'').'FunctionalProperty .';
    $query2[] = '    }';
    $query2[] = '  }';
  }
  if($any)
  {
    $query2[] = '';
  }
}

if(empty($components))
{
  if(isset($filter))
  {
    $query2[] = "  ?ls ?lp $identifier .";
  }else if(!isset($unify_path))
  {
    $query2[] = "  BIND($identifier AS $initial)";
  }
}else{
  if(!isset($options['infer']))
  {
    array_walk($components, function(&$value)
    {
      $name = format_name($value[0]);
      if($value[1]) $name = "^$name";
      $value = $name;
    });
    
    $delimiter = '/';
    if(isset($unify_path))
    {
      $query2[] = "  $initial $unify_path/".implode("/$unify_path/", $components)."/$unify_path $identifier .";
    }else{
      $query2[] = "  $initial ".implode($delimiter, $components)." $identifier .";
    }
  }else{
    if(isset($unify_path))
    {
      $query2[] = "  ?s $unify_path ?s0 .";
      $initial = '?s0';
      //$final = '?r'.count($components);
    }
    
    foreach($components as $index => $value)
    {
      $next = $index + 1;
      $last = $index == count($components) - 1;
      if($index >= 1 && isset($unify_path))
      {
        $query2[] = "  ?r$index $unify_path ?s$index .";
      }
      
      $name = format_name($value[0]);
      $inverse = $value[1];
      
      $subj = $index > 0 ? "?s$index" : $initial;
      $obj = isset($unify_path) ? "?r$next" : ($last && (isset($filter) || !isset($options['infer'])) ? $identifier : "?s$next");
      
      if($inverse)
      {
        $triple_subj = $obj;
        $triple_obj = $subj;
      }else{
        $triple_subj = $subj;
        $triple_obj = $obj;
      }
      
      if(!isset($options['infer']))
      {
        $query2[] = "  $triple_subj $name $triple_obj .";
      }else{
        if($last && !isset($unify_path) && $identifier_is_literal)
        {
          if($inverse)
          {
            $query2[] = "  ?i$index $infer_inverse_path $name .";
            $query2[] = "  $subj ?i$index $obj .";
          }else{
            $query2[] = "  ?p$index $infer_path $name .";
            $query2[] = "  $subj ?p$index $obj .";
          }
        }else{
          $query2[] = '  {';
          $query2[] = "    SELECT ?p$index ?i$index";
          $query2[] = '    WHERE {';
          $query2[] = "      ?p$index $infer_path $name .";
          $query2[] = '      OPTIONAL {';
          $query2[] = "        ?i$index $infer_inverse_path $name .";
          $query2[] = '      }';
          $query2[] = '    }';
          $query2[] = '  }';
          
          $query2[] = '  OPTIONAL {';
          $query2[] = "    $triple_subj ?p$index $triple_obj .";
          $query2[] = '  }';
          $query2[] = '  OPTIONAL {';
          $query2[] = "    $triple_obj ?i$index $triple_subj .";
          $query2[] = '  }';
          if(!$last || isset($unify_path))
          {
            $query2[] = "  FILTER bound($obj)";
          }else if(!isset($filter))
          {
            $query2[] = "  FILTER ($obj = $identifier)";
          }
        }
      }
    }
    
    if(isset($unify_path))
    {
      $last = empty($components) ? $initial : '?r'.count($components);
      $query2[] = "  $last $unify_path $identifier .";
    }
  }
}

if(isset($filter))
{
  if(isset($langRange))
  {
    $query2[] = "  FILTER (isLiteral($identifier) && str(?id) = $filter && langMatches(lang($identifier), $langRange))";
  }else{
    $query2[] = "  FILTER (isLiteral($identifier) && str($identifier) = $filter)";
  }
}

$query2[] = '}';
if(isset($options['first']))
{
  $query2[] = 'LIMIT 1';
}
if(@$options['form'] !== 'select')
{
  foreach($query2 as $line)
  {
    $query[] = "  $line";
  }
  $query[] = '}';
}

if(!isset($options['print']))
{
  array_walk($query, function(&$value)
  {
    $value = trim($value);
  });
}

$query = implode("\n", $query);

if(isset($options['path']))
{
  $uri['path'] = "/$options[path]";
}else{
  $uri['path'] = '/sparql/';
}

if(isset($options['scheme']))
{
  $uri['scheme'] = $options['scheme'];
}else{
  $uri['scheme'] = @$_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
}

if(isset($options['print']))
{
  $target_uri = unparse_url($uri);
  if(!isset($options['html']))
  {
    header('Content-Type: application/sparql-query');
    header('Content-Disposition: inline; filename="query.sparql"');
    echo "# This query would be sent to $target_uri\n\n";
    echo $query;
  }else{
    $query = htmlspecialchars($query);
    $inputs = get_query(null);
    unset($inputs['query']);
    unset($uri['query']);
    $target_uri = htmlspecialchars($target_uri);
    $endpoint_uri = htmlspecialchars(unparse_url($uri));
    
    ?><!DOCTYPE html>
<html lang="en">
<head>
<title>lid: resolver</title>
<base href="/lid/">
<link rel="stylesheet" href="//is4.site/styles/terminal.css?theme=4">
<link rel="stylesheet" href="prism.css">
</head>
<body>
<pre><code class="language-sparql"><?php

    echo "# This query would be sent to $target_uri\n\n";
    echo $query;

?></code></pre>
<script src="prism.js"></script>
<p style="float:left"><a href=".">Back to the main page.</a></p>
<div style="float:right">
<form style="display:inline" method="GET" action="<?=$endpoint_uri?>">
<?php

    foreach($inputs as $key => $value)
    {
      ?><input type="hidden" name="<?=htmlspecialchars($key)?>" value="<?=htmlspecialchars($value)?>">
<?php
    }

?>
<textarea name="query" style="display:none"><?=$query?></textarea>
<input type="submit" value="Send">
</form>
<form style="display:inline" method="GET" action="<?=$endpoint_uri?>">
<?php

    foreach($inputs as $key => $value)
    {
      if($key === 'explain') continue;
      ?><input type="hidden" name="<?=htmlspecialchars($key)?>" value="<?=htmlspecialchars($value)?>">
<?php
    }

?>
<input type="hidden" name="explain" value="on">
<textarea name="query" style="display:none"><?=$query?></textarea>
<input type="submit" value="Analyze">
</form>
<form style="display:inline" method="POST" action="http://www.sparql.org/validate/query">
<textarea name="query" style="display:none"><?php

    if(count($unresolved_prefixes) > 0)
    {
      echo "# These prefixes are supposed to be resolved by the target endpoint:\n";
      foreach($unresolved_prefixes as $prefix => $_)
      {
        $prefix = htmlspecialchars($prefix);
        echo "PREFIX $prefix: <$prefix#>\n";
      }
      echo "\n";
    }
    echo $query;

?></textarea>
<input type="hidden" name="languageSyntax" value="SPARQL">
<input type="hidden" name="outputFormat" value="sparql">
<input type="submit" value="Validate">
</form>
</div>
</body>
</html><?php
  }
}else{
  $uri['query'] = http_build_query(get_query($query), null, '&');
  $target_uri = unparse_url($uri);
  http_response_code(303);
  header("Location: $target_uri");
}
