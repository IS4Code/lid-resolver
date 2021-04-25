<?php

function output_redirect($uri, $sparql, $sparql_inner, $options, $reconstructed_uri, $unresolved_prefixes)
{
  $uri['query'] = get_query_string(create_query_array($sparql, $options));
  $target_uri = unparse_url($uri);
  
  http_response_code(303);
  header("Location: $target_uri");
}

function output_navigate($uri, $sparql, $sparql_inner, $options, $reconstructed_uri, $unresolved_prefixes)
{
  $uri['query'] = get_query_string(create_query_array($sparql, $options));
  $target_uri = unparse_url($uri);
  
  $reconstructed_uri = unparse_url($reconstructed_uri);
  
  $options['_format'] = 'application/javascript';
  $uri['query'] = get_query_string(create_query_array($sparql_inner, $options));
  $javascript_uri = unparse_url($uri); 
  
  ?><!DOCTYPE html>
<html lang="en">
<head>
<title><?=htmlspecialchars($reconstructed_uri)?></title>
<link rel="stylesheet" href="//is4.site/styles/terminal.css?theme=4">
<base href="<?=htmlspecialchars($target_uri)?>">
<noscript>
<meta http-equiv="refresh" content="2;url=<?=htmlspecialchars($target_uri)?>">
</noscript>
</head>
<body data-redirect="<?=htmlspecialchars($target_uri)?>">
<p>Querying the server...</p>
<noscript>
<p>You have scripts disabled, you will be redirected to the <a id="redirect" href="<?=htmlspecialchars($target_uri)?>">query results</a>.</p>
</noscript>
<div id="query_results" hidden style="display:none"><script type="text/javascript" src="<?=htmlspecialchars($javascript_uri)?>"></script></div>
<script type="text/javascript">
var container = document.getElementById('query_results');
var rows = container.getElementsByTagName('tr');
if(rows.length == 0)
{
  document.write('<p>The SPARQL endopoint did not return any loadable results, executing directly...</p>');
  location.replace(document.body.getAttribute('data-redirect'));
}else{
  var header = rows[0].getElementsByTagName('th');
  var index = -1;
  var idIndex = -1;
  for(var i = 0; i < header.length; i++)
  {
    if(header[i].textContent === 's')
    {
      index = i;
    }
    if(header[i].textContent === 'id')
    {
      idIndex = i;
    }
  }
  if(index == -1)
  {
    index = idIndex;
  }
  if(index == -1)
  {
    document.write('<p>Unrecognized results were returned, executing directly...</p>');
    location.replace(document.body.getAttribute('data-redirect'));
  }else{
    if(rows.length == 1)
    {
      document.write('<p>No results found.</p>');
    }else if(rows.length == 2)
    {
      var cell = rows[1].getElementsByTagName('td')[index];
      var links = cell.getElementsByTagName('a');
      if(links.length >= 1)
      {
        document.write('<p>Navigating to entity...</p>');
        location.replace(links[0].href);
      }else{
        document.write('<p>Only data was returned...</p>');
        location.replace('data:text/html;charset=utf-8;base64,' + btoa(unescape(encodeURIComponent(cell.innerHTML))));
      }
    }else{
      document.write('<p>More than one result returned:</p><ul>');
      for(var i = 1; i < rows.length; i++)
      {
        document.write('<li>');
        document.write(rows[i].getElementsByTagName('td')[index].innerHTML);
        document.write('</li>');
      }
      document.write('</ul>')
    }
  }
}
</script>
<p><a href=".">Back to the main page.</a></p>
</body>
</html><?php
}

function output_print($uri, $sparql, $sparql_inner, $options, $reconstructed_uri, $unresolved_prefixes)
{
  $target_uri = unparse_url($uri);
  $reconstructed_uri = unparse_url($reconstructed_uri);
  
  header('Content-Type: application/sparql-query');
  header('Content-Disposition: inline; filename="query.sparql"');
  echo "# Generated from $reconstructed_uri\n";
  echo "# This query would be sent to $target_uri\n\n";
  echo $sparql;
}

function output_debug($uri, $sparql, $sparql_inner, $options, $reconstructed_uri, $unresolved_prefixes)
{
  $target_uri = unparse_url($uri);
  $reconstructed_uri = unparse_url($reconstructed_uri);
  
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