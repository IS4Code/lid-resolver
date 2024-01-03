<?php

class Resolver
{
  public $unresolved_prefixes = array();
  
  private $imported_prefixes = array();
  
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
    $qname[0] = uridecode($qname[0]);
    if(isset($qname[1]))
    {
      $qname[1] = uridecode($qname[1]);
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
    }else if($name === '')
    {
      if($allowEmpty) return null;
      report_error(400, "URI component must be a prefixed name or an absolute URI (was empty)!");
    }else if(!is_absolute_uri($qname[0]))
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
      if(substr($value, 0, 3) === '%27')
      {
        $value = array($this->resolve_name(substr($value, 3)), true);
      }else if(substr($value, 0, 1) === "'")
      {
        $value = array($this->resolve_name(substr($value, 1)), true);
      }else{
        $value = array($this->resolve_name($value), false);
      }
    });
  }
  
  function parse_identifier($identifier)
  {
    @list($identifier, $type) = explode('@', $identifier, 2);
    $kind = 'plain';
    
    if(substr($identifier, 0, 1) === '$')
    {
      $identifier = $this->resolve_name(substr($identifier, 1));
    }else{
      $identifier = uridecode($identifier);
    }
    
    if(isset($type))
    {
      if($type === '')
      {
        $type = null;
        $kind = 'datatype';
      }else{
        if(preg_match('/^[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*$/', $type))
        {
          $type = uridecode($type);
          $kind = 'language';
        }else if(preg_match('/^(?:[a-zA-Z]{1,8}|\*)(-(?:[a-zA-Z0-9]{1,8}|\*))*-?$/', $type))
        {
          $type = rtrim(uridecode($type), '-');
          $kind = 'langrange';
        }else{
          $type = $this->resolve_name($type);
          $kind = 'datatype';
        }
      }
    }else{
      $type = null;
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
      $key = uridecode($part[0]);
      $value = @$part[1];
      if(substr($part[0], 0, 1) === '_')
      {
        $options[substr($key, 1)] = uridecode($value);
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
      // Full URI
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
    if($name[0] === null)
    {
      // Relative URI
      return "<$name[1]>";
    }
    $unresolved_prefixes[$name[0]] = null;
    return $name[0].':'.addcslashes($name[1], $escape);
  }
  
  private function has_undefined_prefix($name)
  {
    if(is_string($name))
    {
      return false;
    }
    if(get_special_name($name) !== null)
    {
      return false;
    }
    if($name[0] === null)
    {
      return false;
    }
    return true;
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
  
  private function build_identifier_literal(&$identifier, &$idkind, &$idtype, &$constructor, &$filter_out, &$imported_ns)
  {
    $options = &$this->options;
    
    $needs_filter = false;
    $filter = 'isLITERAL(?id)';
    if(!is_string($identifier))
    {
      if(is_option($options, 'prefixes') && $this->has_undefined_prefix($identifier))
      {
        $imported_ns = $identifier;
        $identifier = 'STR(?idn)';
      }else{
        $identifier = 'STR('.$this->format_name($identifier).')';
      }
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
      if($idtype !== null)
      {
        $idtype = $this->format_name($idtype);
        if($needs_filter)
        {
          $filter = "$filter && DATATYPE(?id) = $idtype";
          $constructor = "STRDT($identifier, $idtype)";
        }else{
          $identifier = "$identifier^^$idtype";
        }
      }else{
        if($needs_filter)
        {
          $constructor = $identifier;
        }
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
  
  private function import_namespace(&$query_inner, $raw_name, $first, $owl, $rdfs, $vann, $rdfa, $__)
  {
    $options = &$this->options;
    
    $prefix = $raw_name[0];
    $prefix_str = '"'.addslashes($prefix).'"';
    
    if(is_option($options, 'infer_prefixes'))
    {
      if($first)
      {
        $subproperty_path = "($rdfs:subPropertyOf|$owl:equivalentProperty|^$owl:equivalentProperty)*";
        $query_inner[] = "${__}VALUES ?npb { $vann:preferredNamespacePrefix $rdfa:prefix }";
        $query_inner[] = "$__?np $subproperty_path ?npb .";
        $query_inner[] = "${__}VALUES ?nub { $vann:preferredNamespaceUri $rdfa:uri }";
        $query_inner[] = "$__?nu $subproperty_path ?nub .";
      }
      $query_inner[] = "$__?nv_$prefix ?np $prefix_str .";
      $query_inner[] = "$__?nv_$prefix ?nu ?n_$prefix .";
    }else{
      $query_inner[] = "$__$prefix_str ^($vann:preferredNamespacePrefix|$rdfa:prefix)/($vann:preferredNamespaceUri|$rdfa:uri) ?n_$prefix .";
    }
    
    $query_inner[] = "${__}FILTER isLiteral(?n_$prefix)";
  }
  
  private function import_namespace_item(&$query_inner, $raw_name, $target, $global, $owl, $rdfs, $vann, $rdfa, $__)
  {
    $prefix = $raw_name[0];
    if($global)
    {
      $imported_prefixes = &$this->imported_prefixes;
      if(empty($imported_prefixes[$prefix]))
      {
        $this->import_namespace($query_inner, $raw_name, empty($imported_prefixes), $owl, $rdfs, $vann, $rdfa, $__);
        $imported_prefixes[$prefix] = true;
      }
    }else{
      $this->import_namespace($query_inner, $raw_name, true, $owl, $rdfs, $vann, $rdfa, $__);
    }
    if(empty($raw_name[1]))
    {
      $query_inner[] = "${__}BIND (URI(STR(?n_$prefix)) AS $target)";
    }else{
      $local_name = '"'.addslashes($raw_name[1]).'"';
      $query_inner[] = "${__}BIND (URI(CONCAT(STR(?n_$prefix), $local_name)) AS $target)";
    }
  }
  
  private function debug_mode()
  {
    $options = &$this->options;
    
    return @$options['action'] === 'print' || @$options['action'] === 'debug';
  }
  
  function build_query(&$uri, $components, $identifier)
  {
    $options = &$this->options;
    
    list($identifier, $idkind, $idtype) = $identifier;
    
    $query = array();
    
    if($this->debug_mode())
    {
      $uriquery = create_query_array(null, $options);
      $uri['query'] = get_query_string($uriquery);
      if($uri['query'] === '')
      {
        unset($uri['query']);
      }
    }
    
    if(is_option($options, 'check') || is_option($options, 'infer') || is_option($options, 'infer_prefixes'))
    {
      $this->use_prefix('rdfs', $rdfs);
      $query[] = "PREFIX $rdfs: <http://www.w3.org/2000/01/rdf-schema#>";
    }else{
      $rdfs = '';
    }
    
    if(is_option($options, 'check') || is_option($options, 'unify_owl') || is_option($options, 'infer') || is_option($options, 'infer_prefixes'))
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
    
    if(is_option($options, 'prefixes'))
    {
      $this->use_prefix('vann', $vann);
      $this->use_prefix('rdfa', $rdfa);
      $query[] = "PREFIX $vann: <http://purl.org/vocab/vann/>";
      $query[] = "PREFIX $rdfa: <http://www.w3.org/ns/rdfa#>";
    }else{
      $vann = '';
      $rdfa = '';
    }
    
    if(!empty($rdf) || !empty($rdfs) || !empty($owl) || !empty($skos) || !empty($xsd) || !empty($vann) || !empty($rdfa))
    {
      $query[] = '';
    }
    
    $identifier_is_literal = true;
    if(!empty($components))
    {
      $last = $components[count($components) - 1];
      if(get_special_name($last[0]) === 'uri' && !$last[1])
      {
        if($idkind === 'plain' || ($idkind === 'datatype' && $idtype === 'http://www.w3.org/2001/XMLSchema#anyURI'))
        {
          if(!is_string($identifier) || is_absolute_uri($identifier))
          {
            array_pop($components);
            $identifier_is_literal = false;
            if(is_option($options, 'prefixes') && $this->has_undefined_prefix($identifier))
            {
              $imported_ns = $identifier;
              $identifier = '?idn';
            }else{
              $identifier = $this->format_name($identifier);
            }
          }else{
            report_error(400, "A relative URI (<q>$identifier</q>) must be prefixed with <q>\$base:</q> in the identifier!");
          }
        }else{
          report_error(400, "The datatype of the value of the special property <q>uri</q> must be either unspecified or xsd:anyURI!");
        }
      }
    } 
    
    $initial = '?s';
    if($identifier_is_literal)
    {
      $this->build_identifier_literal($identifier, $idkind, $idtype, $constructor, $filter, $imported_ns);
      if(empty($components) && !is_option($options, 'unify_owl') && !is_option($options, 'unify_skos'))
      {
        $initial = '?id';
      }
    }
    
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
    
    if(!isset($filter) && !isset($constructor))
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
    $inverse_path_bare = "/$owl:inverseOf";
    if(is_option($options, 'inverse'))
    {
      $additional_path = "/($owl:inverseOf/($rdfs:subPropertyOf|$owl:equivalentProperty|^$owl:equivalentProperty)*/$owl:inverseOf/($rdfs:subPropertyOf|$owl:equivalentProperty|^$owl:equivalentProperty)*)*";
    }else{
      $additional_path = '';
    }
    $infer_path = "$subproperty_path$additional_path";
    $infer_inverse_path = "$subproperty_path$inverse_path$additional_path";
    $infer_inverse_path_bare = "$subproperty_path$inverse_path_bare$additional_path";
    
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
    
    if(isset($imported_ns))
    {
      $this->import_namespace_item($query_inner, $imported_ns, '?idn', true, $owl, $rdfs, $vann, $rdfa, '  ');
    }
    if(isset($constructor))
    {
      $query_inner[] = "  BIND ($constructor AS $identifier)";
    }
    
    if(empty($components))
    {
      // No path, only the identifier
      
      if(isset($unify_path))
      {
        $query_inner[] = "  $initial $unify_path $identifier .";
      }else if(!isset($constructor))
      {
        if(isset($filter))
        {
          $query_inner[] = "  ?ls ?lp $identifier .";
        }else{
          $query_inner[] = "  BIND ($identifier AS $initial)";
        }
      }
    }else{
      // A path is specified
      
      if(!is_option($options, 'infer') && !is_option($options, 'prefixes') && !array_any($components, function($val)
      {
        // A specially handled property (e.g. 'uri') is used
        return get_special_name($val[0]);
      }))
      {
        // Normal SPARQL property path can be used
        
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
        // Each predicate link has to be written one by one
        
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
          
          list($raw_name, $is_inverse) = $value;
          
          $not_variable = $last && !isset($unify_path);          
          $step_input = $index > 0 ? "?s$index" : $initial;
          $step_output = isset($unify_path) ? "?r$next" : ($not_variable ? $identifier : "?s$next");
          
          if($last && isset($filter) && !isset($constructor))
          {
            $not_variable = false;
          }
          
          $special = get_special_name($raw_name);
          if($special === 'uri')
          {
            if($is_inverse)
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
            if(is_option($options, 'prefixes') && $this->has_undefined_prefix($raw_name))
            {
              $name = "?n$index";
              $import_undefined = true;
            }else{
              $name = $this->format_name($raw_name);
              $import_undefined = false;
            }
            
            if($is_inverse)
            {
              $triple_subj = $step_output;
              $triple_obj = $step_input;
            }else{
              $triple_subj = $step_input;
              $triple_obj = $step_output;
            }
            
            if(!is_option($options, 'infer'))
            {
              if($import_undefined)
              {
                $this->import_namespace_item($query_inner, $raw_name, $name, true, $owl, $rdfs, $vann, $rdfa, '  ');
              }
              $query_inner[] = "  $triple_subj $name $triple_obj .";
            }else{
              if($last && !isset($unify_path) && $identifier_is_literal)
              {
                if($import_undefined)
                {
                  $this->import_namespace_item($query_inner, $raw_name, $name, true, $owl, $rdfs, $vann, $rdfa, '  ');
                }
                if($is_inverse)
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
                
                if($import_undefined)
                {
                  $this->import_namespace_item($query_inner, $raw_name, $name, false, $owl, $rdfs, $vann, $rdfa, '      ');
                }
                
                $query_inner[] = "      ?p$index $infer_path $name .";
                $query_inner[] = '      OPTIONAL {';
                $query_inner[] = "        ?i$index $infer_inverse_path_bare ?p$index .";
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
    
    if(isset($filter) && !isset($constructor))
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
    
    $query[] = '';
    
    if(!$this->debug_mode())
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
      
      if(@$options['form'] === 'select')
      {
        $query_inner = $query;
      }else{
        $query_inner_filtered = array();
        foreach($query_inner as $line)
        {
          $line = trim($line);
          if($line !== '')
          {
            $query_inner_filtered[] = $line;
          }
        }
        $query_inner = $query_inner_filtered;
      }
    }
    
    return array(implode("\n", $query), implode("\n", $query_inner));
  }
}