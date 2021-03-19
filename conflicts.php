<?php
require '.internal.php';
$data = get_context();
$context = &$data['@context'];

$data = get_common_context();
if($data === null)
{
  http_response_code(503);
  die;
}
$context2 = &$data['@context'];

?><!DOCTYPE html>
<html lang="en">
<head>
<title>lid: scheme conflicts</title>
<link rel="stylesheet" href="//is4.site/styles/terminal.css?theme=4">
</head>
<body>
<p>These are some commonly used prefixes (courtesy of <a href="//prefix.cc">prefix.cc</a>) that are redefined by a URI scheme of the same name.</p>
<p><a href="structure.php">Back to the scheme description.</a></p>
<table>
<tr><th>Prefix</th><th>Namespace</th></tr>
<?php
foreach($context as $key => $value)
{
  if("$key:" === $value && isset($context2[$key]) && $context2[$key] !== $value)
  {
    $uri = htmlspecialchars($context2[$key]);
    ?><tr><th><?=$value?></th><td><a href="<?=$uri?>"><?=$uri?></a></td></tr><?php
  }
}
?>
</table>
</body>
</html>