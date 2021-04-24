<?php

class Resolver
{
  public $unresolved_prefixes = array();
  
  private $context;
  private $options;
  
  public function __construct(&$context, &$options)
  {
    $this->context = &$context;
    $this->options = &$options;
  }

  private function resolve_name($name, $allowEmpty = false)
  {
    $context = &$this->context;
    
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
  
  function parse_properties(&$components)
  {
    array_walk($components, function(&$value)
    {
      if(substr($value, 0, 1) === "'")
      {
        $value = array($this->resolve_name(substr($value, 1)), true);
      }else{
        $value = array($this->resolve_name($value), false);
      }
    });
  }
  
  function parse_identifier($identifier)
  {
    $identifier = explode('@', $identifier, 2);
    $kind = 'plain';
    $type = null;
    
    $is_name = false;
    if(isset($identifier[1]))
    {
      $type = $identifier[1];
      if(empty($type))
      {
        $is_name = true;
      }else if(substr($type, 0, 1) === '@' && strlen($type) > 1)
      {
        $is_name = true;
        $type = substr($type, 1);
      }
      if(!empty($type))
      {
        if(preg_match('/^[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*$/', $type))
        {
          $type = urldecode($type);
          $kind = 'language';
        }else if(preg_match('/^(?:[a-zA-Z]{1,8}|\*)(-(?:[a-zA-Z0-9]{1,8}|\*))*-?$/', $type))
        {
          $type = rtrim(urldecode($type), '-');
          $kind = 'langrange';
        }else{
          $type = $this->resolve_name($type);
          $kind = 'datatype';
        }
      }
    }
    
    if($is_name)
    {
      $identifier = $this->resolve_name($identifier[0]);
    }else{
      $identifier = urldecode($identifier[0]);
    }
    
    return array($identifier, $kind, $type);
  }
  
  function parse_query(&$query)
  {
    $context = &$this->context;
    $options = &$this->options;
    
    foreach($query as $index => &$item)
    {
      $part = explode('=', $item, 2);
      $key = urldecode($part[0]);
      $value = @$part[1];
      if(substr($part[0], 0, 1) === '_')
      {
        $options[substr($key, 1)] = urldecode($value);
        unset($query[$index]);
      }else if(isset($part[1]))
      {
        $value = $this->resolve_name($value, true);
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
  
  private function format_name($name)
  {
    static $escape = '_~.-!$&\'()*+,;=/?#@%';
    
    $unresolved_prefixes = &$this->unresolved_prefixes;
    
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
  
  private function use_prefix($name, &$prefix)
  {
    $context = &$this->context;
    
    $suffix = '';
    while(isset($context[$name.$suffix]) && !is_string($context[$name.$suffix]))
    {
      $suffix++;
    }
    $prefix = $name.$suffix;
  }
  
  private function build_identifier_literal(&$identifier, &$idkind, &$idtype, &$constructor, &$filter_out)
  {
    $needs_filter = false;
    $filter = 'isLITERAL(?id)';
    if(!is_string($identifier))
    {
      $identifier = 'STR('.$this->format_name($identifier).')';
      $needs_filter = true;
    }else{
      $identifier = '"'.addslashes($identifier).'"';
    }
    
    if($idkind === 'language')
    {
      if($needs_filter)
      {
        $idtype = '"'.addslashes($idtype).'"';
        $filter = "$filter && LANG(?id) = $idtype";
        $constructor = "STRLANG($identifier, $idtype)";
      }else{
        $identifier = "$identifier@$idtype";
      }
    }else if($idkind === 'datatype')
    {
      $idtype = $this->format_name($idtype);
      if($needs_filter)
      {
        $filter = "$filter && DATATYPE(?id) = $idtype";
        $constructor = "STRDT($identifier, $idtype)";
      }else{
        $identifier = "$identifier^^$idtype";
      }
    }else if($idkind === 'langrange')
    {
      $needs_filter = true;
      $idtype = '"'.addslashes($idtype).'"';
      $filter = "$filter && LANGMATCHES(lang(?id), $idtype)";
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
    
    if(isset($filter))
    {
      $filter_out = $filter;
    }
  }
  
  function build_query(&$uri, $components, $identifier)
  {
    $options = &$this->options;
    
    list($identifier, $idkind, $idtype) = $identifier;
    
    $query = array();
    
    if(is_option($options, 'print'))
    {
      $uriquery = create_query_array(null, $options);
      $uri['query'] = get_query_string($uriquery);
      if(empty($uri['query']))
      {
        unset($uri['query']);
      }
    }
    
    if(is_option($options, 'check') || is_option($options, 'infer'))
    {
      $this->use_prefix('rdfs', $rdfs);
      $query[] = "PREFIX $rdfs: <http://www.w3.org/2000/01/rdf-schema#>";
    }else{
      $rdfs = '';
    }
    
    if(is_option($options, 'check') || is_option($options, 'unify_owl') || is_option($options, 'infer'))
    {
      $this->use_prefix('owl', $owl);
      $query[] = "PREFIX $owl: <http://www.w3.org/2002/07/owl#>";
    }else{
      $owl = '';
    }
    
    if(is_option($options, 'unify_skos'))
    {
      $this->use_prefix('skos', $skos);
      $query[] = "PREFIX $skos: <http://www.w3.org/2004/02/skos/core#>";
    }else{
      $skos = '';
    }
    
    if(!empty($rdf) || !empty($rdfs) || !empty($owl) || !empty($skos) || !empty($xsd))
    {
      $query[] = '';
    }
    
    $identifier_is_literal = true;
    if(!empty($components))
    {
      $last = $components[count($components) - 1];
      if(get_special_name($last[0]) === 'uri' && !$last[1] && ($idkind === 'plain' || ($idkind === 'datatype' && $idtype === 'http://www.w3.org/2001/XMLSchema#anyURI')))
      {
        array_pop($components);
        $identifier_is_literal = false;
        $identifier = $this->format_name($identifier);
      }
    } 
    
    if($identifier_is_literal)
    {
      $this->build_identifier_literal($identifier, $idkind, $idtype, $constructor, $filter);
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
        $name = $this->format_name($value[0]);
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
      $query_inner = array();
    }else{
      $query_inner = &$query;
    }
    
    if(is_option($options, 'unify_owl'))
    {
      if(is_option($options, 'unify_skos'))
      {
        $unify_path = "($owl:sameAs|^$owl:sameAs|$skos:exactMatch|^$skos:exactMatch)*";
      }else{
        $unify_path = "($owl:sameAs|^$owl:sameAs)*";
      }
    }else if(is_option($options, 'unify_skos'))
    {
      $unify_path = "($skos:exactMatch|^$skos:exactMatch)*";
    }
    
    if(is_option($options, 'first'))
    {
      $selection = 'SELECT';
    }else{
      $selection = 'SELECT DISTINCT';
    }
    
    if(!isset($filter) || isset($constructor))
    {
      $query_inner[] = "$selection $initial";
    }else if(empty($components) && !isset($unify_path))
    {
      $query_inner[] = "$selection $identifier";
    }else{
      $query_inner[] = "$selection $initial $identifier";
    }
    $query_inner[] = "WHERE {";
    
    $subproperty_path = "($rdfs:subPropertyOf|$owl:equivalentProperty|^$owl:equivalentProperty)*";
    $inverse_path = "/$owl:inverseOf/($rdfs:subPropertyOf|$owl:equivalentProperty|^$owl:equivalentProperty)*";
    if(is_option($options, 'inverse'))
    {
      $additional_path = "/($owl:inverseOf/($rdfs:subPropertyOf|$owl:equivalentProperty|^$owl:equivalentProperty)*/$owl:inverseOf/($rdfs:subPropertyOf|$owl:equivalentProperty|^$owl:equivalentProperty)*)*";
    }else{
      $additional_path = '';
    }
    $infer_path = "$subproperty_path$additional_path";
    $infer_inverse_path = "$subproperty_path$inverse_path$additional_path";
    
    if(is_option($options, 'check'))
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
        $name = $this->format_name($name);
        $query_inner[] = '  FILTER EXISTS {';
        $query_inner[] = '    {';
        $query_inner[] = "      $name $infer_path/a/$subclass_path $owl:".($reverse?'':'Inverse').'FunctionalProperty .';
        $query_inner[] = '    } UNION {';
        $query_inner[] = "      $name $infer_inverse_path/a/$subclass_path $owl:".($reverse?'Inverse':'').'FunctionalProperty .';
        $query_inner[] = '    }';
        $query_inner[] = '  }';
      }
      if($any)
      {
        $query_inner[] = '';
      }
    }
    
    if(empty($components))
    {
      if(isset($unify_path))
      {
        $query_inner[] = "  $initial $unify_path $identifier .";
      }else if($constructor)
      {
        $query_inner[] = "  BIND ($constructor AS $initial)";
        unset($filter);
      }else if(isset($filter))
      {
        $query_inner[] = "  ?ls ?lp $identifier .";
      }else{
        $query_inner[] = "  BIND ($identifier AS $initial)";
      }
    }else{
      if(!is_option($options, 'infer') && !array_any($components, function($val)
      {
        return get_special_name($val[0]);
      }))
      {
        array_walk($components, function(&$value)
        {
          $name = $this->format_name($value[0]);
          if($value[1]) $name = "^$name";
          $value = $name;
        });
        
        $delimiter = '/';
        if(isset($unify_path))
        {
          $query_inner[] = "  $initial $unify_path/".implode("/$unify_path/", $components)."/$unify_path $identifier .";
        }else{
          $query_inner[] = "  $initial ".implode($delimiter, $components)." $identifier .";
        }
      }else{
        if(isset($unify_path))
        {
          $query_inner[] = "  ?s $unify_path ?s0 .";
          $initial = '?s0';
        }
        
        foreach($components as $index => $value)
        {
          $next = $index + 1;
          $last = $index == count($components) - 1;
          if($index >= 1 && isset($unify_path))
          {
            $query_inner[] = "  ?r$index $unify_path ?s$index .";
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
              $query_inner[] = "  FILTER (DATATYPE($step_input) = <http://www.w3.org/2001/XMLSchema#anyURI>)";
              if($not_variable)
              {
                $query_inner[] = "  FILTER (IRI(STR($step_input)) = $step_output)";
              }else{
                $query_inner[] = "  BIND (IRI(STR($step_input)) as $step_output)";
              }
            }else{
              if($not_variable)
              {
                $query_inner[] = "  FILTER (STRDT(STR($step_input), <http://www.w3.org/2001/XMLSchema#anyURI>) = $step_output)";
              }else{
                $query_inner[] = "  BIND (STRDT(STR($step_input), <http://www.w3.org/2001/XMLSchema#anyURI>) as $step_output)";
              }
            }
          }else{
            $name = $this->format_name($value[0]);
            if($inverse)
            {
              $triple_subj = $step_output;
              $triple_obj = $step_input;
            }else{
              $triple_subj = $step_input;
              $triple_obj = $step_output;
            }
            
            if(!is_option($options, 'infer'))
            {
              $query_inner[] = "  $triple_subj $name $triple_obj .";
            }else{
              if($last && !isset($unify_path) && $identifier_is_literal)
              {
                if($inverse)
                {
                  $query_inner[] = "  ?i$index $infer_inverse_path $name .";
                  $query_inner[] = "  $step_input ?i$index $step_output .";
                }else{
                  $query_inner[] = "  ?p$index $infer_path $name .";
                  $query_inner[] = "  $step_input ?p$index $step_output .";
                }
              }else{
                $query_inner[] = '  {';
                $query_inner[] = "    SELECT ?p$index ?i$index";
                $query_inner[] = '    WHERE {';
                $query_inner[] = "      ?p$index $infer_path $name .";
                $query_inner[] = '      OPTIONAL {';
                $query_inner[] = "        ?i$index $infer_inverse_path $name .";
                $query_inner[] = '      }';
                $query_inner[] = '    }';
                $query_inner[] = '  }';
                
                $query_inner[] = '  OPTIONAL {';
                $query_inner[] = "    $triple_subj ?p$index $triple_obj .";
                $query_inner[] = '  }';
                $query_inner[] = '  OPTIONAL {';
                $query_inner[] = "    $triple_obj ?i$index $triple_subj .";
                $query_inner[] = '  }';
                if(!$last || isset($unify_path))
                {
                  $query_inner[] = "  FILTER BOUND($step_output)";
                }else if(!isset($filter))
                {
                  $query_inner[] = "  FILTER ($step_output = $identifier)";
                }
              }
            }
          }
        }
        
        if(isset($unify_path))
        {
          $last = '?r'.count($components);
          $query_inner[] = "  $last $unify_path $identifier .";
        }
      }
    }
    
    if(isset($filter))
    {
      $query_inner[] = "  FILTER ($filter)";
    }
    
    $query_inner[] = '}';
    if(is_option($options, 'first'))
    {
      $query_inner[] = 'LIMIT 1';
    }
    if(@$options['form'] !== 'select')
    {
      foreach($query_inner as $line)
      {
        $query[] = "  $line";
      }
      $query[] = '}';
    }
    
    if(!is_option($options, 'print'))
    {
      $query_filtered = array();
      foreach($query as $line)
      {
        $line = trim($line);
        if($line !== '')
        {
          $query_filtered[] = $line;
        }
      }
      $query = $query_filtered;
    }
    
    return implode("\n", $query);
  }
}