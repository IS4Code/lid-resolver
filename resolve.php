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

require '.internal.php';
require '.resolver.php';
require '.resolver_class.php';

$uri = analyze_uri($uri, $components, $identifier, $query);

$data = get_context();
$context = &$data['@context'];

$resolver = new Resolver($context, $options);

if(!empty($query))
{
  $resolver->parse_query($query);
}

$reconstructed_uri = $uri;
$reconstructed_uri['scheme'] = 'lid';
foreach($options as $key => $value)
{
  $query[] = '_'.rawurlencode($key).'='.rawurlencode($value);
}
$reconstructed_uri['query'] = implode('&', $query);
$reconstructed_uri['path'] = implode('/', array_merge(isset($uri['host']) ? array('') : array(), $components, array($identifier)));
  
$resolver->parse_properties($components);

$identifier = $resolver->parse_identifier($identifier);

$sparql = $resolver->build_query($uri, $components, $identifier);

$unresolved_prefixes = $resolver->unresolved_prefixes;

if(!empty($options['path']))
{
  $uri['path'] = "/$options[path]";
}else{
  $uri['path'] = '/sparql/';
}

if(!empty($options['scheme']))
{
  $uri['scheme'] = $options['scheme'];
}else{
  $uri['scheme'] = @$_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
}

if(!isset($options['action']) || $options['action'] == 'redirect')
{
  $uri['query'] = get_query_string(create_query_array($sparql, $options));
  $target_uri = unparse_url($uri);
  http_response_code(303);
  header("Location: $target_uri");
}else{
  $target_uri = unparse_url($uri);
  $reconstructed_uri = unparse_url($reconstructed_uri);
  if($options['action'] !== 'debug')
  {
    header('Content-Type: application/sparql-query');
    header('Content-Disposition: inline; filename="query.sparql"');
    echo "# Generated from $reconstructed_uri\n";
    echo "# This query would be sent to $target_uri\n\n";
    echo $sparql;
  }else{
    $sparql = htmlspecialchars($sparql);
    $inputs = create_query_array(null, $options);
    
    $target_uri = htmlspecialchars($target_uri);
    $reconstructed_uri = htmlspecialchars($reconstructed_uri);
    
    unset($uri['query']);
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

    echo "# Generated from $reconstructed_uri\n";
    echo "# This query would be sent to $target_uri\n\n";
    echo $sparql;

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
<textarea name="query" hidden style="display:none"><?=$sparql?></textarea>
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
<textarea name="query" hidden style="display:none"><?=$sparql?></textarea>
<input type="submit" value="Analyze">
</form>
<form style="display:inline" method="POST" action="http://www.sparql.org/validate/query">
<textarea name="query" hidden style="display:none"><?php

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
    echo $sparql;

?></textarea>
<input type="hidden" name="languageSyntax" value="SPARQL">
<input type="hidden" name="outputFormat" value="sparql">
<input type="submit" value="Validate">
</form>
</div>
</body>
</html><?php
  }
}
