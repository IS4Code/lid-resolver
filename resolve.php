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

function create_query($uri, $components, $identifier, $query)
{
  $data = get_context();
  $context = &$data['@context'];
  
  $resolver = new Resolver($context, $options);
  
  if(!empty($query))
  {
    $resolver->parse_query($query);
  }
  
  array_walk($components, $resolver->parse_property);
  
  $identifier = $resolver->parse_identifier($identifier, $language, $langRange, $datatype);
  
  return $resolver->build_query($uri, $components, $identifier, $language, $langRange, $datatype);
}

$query = create_query($uri, $components, $identifier, $query);

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
