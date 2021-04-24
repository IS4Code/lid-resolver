<?php

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

