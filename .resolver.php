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
      return concat_prefixed($context[$prefix], $local);
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

function parse_query($query)
{
  global $context;
  global $options;
  
  foreach($query as $part)
  {
    $part = explode('=', $part, 2);
    $key = urldecode($part[0]);
    $value = @$part[1];
    if(substr($part[0], 0, 1) === '_')
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

function parse_property(&$value)
{
  if(substr($value, 0, 1) === "'")
  {
    $value = array(resolve_name(substr($value, 1)), true);
  }else{
    $value = array(resolve_name($value), false);
  }
}

function parse_identifier($identifier, &$lang, &$langRange, &$datatype)
{
  $identifier = explode('@', $identifier, 2);
  $is_prefixed = false;
  if(isset($identifier[1]))
  {
    $language = urldecode($identifier[1]);
    if(empty($language))
    {
      $is_prefixed = true;
    }else if(substr($language, 0, 1) === '@' && strlen($language) > 1)
    {
      $is_prefixed = true;
      $language = substr($language, 1);
    }
    if(empty($language))
    {
      unset($language);
    }else if(!preg_match('/^[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*$/', $language))
    {
      if(preg_match('/^(?:[a-zA-Z]{1,8}|\*)(-(?:[a-zA-Z0-9]{1,8}|\*))*-?$/', $language))
      {
        $langRange = rtrim($language, '-');
      }else{
        $datatype = resolve_name($language);
      }
      unset($language);
    }
  }
  if($is_prefixed)
  {
    $identifier = resolve_name($identifier[0]);
  }else{
    $identifier = urldecode($identifier[0]);
  }
  if(isset($language))
  {
    $lang = $language;
  }
  return $identifier;
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

function format_name($name)
{
  static $escape = '_~.-!$&\'()*+,;=/?#@%';
  global $unresolved_prefixes;
  if(is_string($name))
  {
    validate_name($name);
    return "<$name>";
  }
  $special = get_special_name($name);
  if($special === 'uri')
  {
    return '<http://www.w3.org/2000/10/swap/log#uri>';
  }else if($special !== null)
  {
    $special = htmlspecialchars($special);
    report_error(400, "Special name <q>$special</q> was used in an unsupported position!");
  }
  validate_name($name[1]);
  $unresolved_prefixes[$name[0]] = null;
  return $name[0].':'.addcslashes($name[1], $escape);
}

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

function build_query(&$uri, $components, $identifier, $language, $langRange, $datatype)
{
  global $options;
  
  $query = array();
  
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
  
  $identifier_is_literal = true;
  if(!empty($components))
  {
    $last = $components[count($components) - 1];
    if(get_special_name($last[0]) == 'uri' && !$last[1] && !isset($language) && !isset($langRange) && (!isset($datatype) || $datatype === 'http://www.w3.org/2001/XMLSchema#anyURI'))
    {
      array_pop($components);
      $identifier_is_literal = false;
      $identifier = format_name($identifier);
    }
  } 
  
  if($identifier_is_literal)
  {
    $needs_filter = false;
    $filter = 'isLITERAL(?id)';
    if(!is_string($identifier))
    {
      $identifier = 'STR('.format_name($identifier).')';
      $needs_filter = true;
    }else{
      $identifier = '"'.addslashes($identifier).'"';
    }
    
    if(isset($language))
    {
      if($needs_filter)
      {
        $language = '"'.addslashes($language).'"';
        $filter = "$filter && LANG(?id) = $language";
        $constructor = "STRLANG($identifier, $language)";
      }else{
        $identifier = "$identifier@$language";
      }
    }else if(isset($datatype))
    {
      $datatype = format_name($datatype);
      if($needs_filter)
      {
        $filter = "$filter && DATATYPE(?id) = $datatype";
        $constructor = "STRDT($identifier, $datatype)";
      }else{
        $identifier = "$identifier^^$datatype";
      }
    }else if(isset($langRange))
    {
      $needs_filter = true;
      $langRange = '"'.addslashes($langRange).'"';
      $filter = "$filter && LANGMATCHES(lang(?id), $langRange)";
    }else{
      $needs_filter = true;
    }
    $filter = "$filter && STR(?id) = $identifier";
    
    if($needs_filter)
    {
      $identifier = '?id';
    }else{
      unset($filter);
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
  
  if(!isset($filter) || isset($constructor))
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
    if(isset($unify_path))
    {
      $query2[] = "  $initial $unify_path $identifier .";
    }else if($constructor)
    {
      $query2[] = "  BIND ($constructor AS $initial)";
      unset($filter);
    }else if(isset($filter))
    {
      $query2[] = "  ?ls ?lp $identifier .";
    }else{
      $query2[] = "  BIND ($identifier AS $initial)";
    }
  }else{
    if(!isset($options['infer']) && !array_any($components, function($val)
    {
      return get_special_name($val[0]);
    }))
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
      }
      
      foreach($components as $index => $value)
      {
        $next = $index + 1;
        $last = $index == count($components) - 1;
        if($index >= 1 && isset($unify_path))
        {
          $query2[] = "  ?r$index $unify_path ?s$index .";
        }
        
        $inverse = $value[1];
        
        $not_variable = $last && !isset($unify_path);
        $step_input = $index > 0 ? "?s$index" : $initial;
        $step_output = isset($unify_path) ? "?r$next" : ($not_variable ? $identifier : "?s$next");
        
        $special = get_special_name($value[0]);
        if($special === 'uri')
        {
          if($inverse)
          {
            $query2[] = "  FILTER (DATATYPE($step_input) = <http://www.w3.org/2001/XMLSchema#anyURI>)";
            if($not_variable)
            {
              $query2[] = "  FILTER (IRI(STR($step_input)) = $step_output)";
            }else{
              $query2[] = "  BIND (IRI(STR($step_input)) as $step_output)";
            }
          }else{
            if($not_variable)
            {
              $query2[] = "  FILTER (STRDT(STR($step_input), <http://www.w3.org/2001/XMLSchema#anyURI>) = $step_output)";
            }else{
              $query2[] = "  BIND (STRDT(STR($step_input), <http://www.w3.org/2001/XMLSchema#anyURI>) as $step_output)";
            }
          }
        }else{
          $name = format_name($value[0]);
          if($inverse)
          {
            $triple_subj = $step_output;
            $triple_obj = $step_input;
          }else{
            $triple_subj = $step_input;
            $triple_obj = $step_output;
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
                $query2[] = "  $step_input ?i$index $step_output .";
              }else{
                $query2[] = "  ?p$index $infer_path $name .";
                $query2[] = "  $step_input ?p$index $step_output .";
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
                $query2[] = "  FILTER BOUND($step_output)";
              }else if(!isset($filter))
              {
                $query2[] = "  FILTER ($step_output = $identifier)";
              }
            }
          }
        }
      }
      
      if(isset($unify_path))
      {
        $last = '?r'.count($components);
        $query2[] = "  $last $unify_path $identifier .";
      }
    }
  }
  
  if(isset($filter))
  {
    $query2[] = "  FILTER ($filter)";
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
  
  return implode("\n", $query);
}