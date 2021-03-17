<!DOCTYPE html>
<html lang="en">
<head>
<title>lid: resolver</title>
<link rel="stylesheet" href="//is4.site/styles/terminal.css?theme=4">
</head>
<body>
<h1><code>lid:</code> URI resolver</h1>
<p>This service can be used as a resolver for URIs in the <mark><code>lid:</code></mark> scheme (described <a href="https://is4code.blogspot.com/2021/03/uri-scheme-for-identifying-linked-data.html">here</a>).</p>
<p>This website is configured to resolve URIs in the form: <mark><code>https://<?=$_SERVER[HTTP_HOST]?>/lid://<span contenteditable="true">example.org/ex:id/1</span></code></mark>.<br>
You may also use the form below:</p>
<form method="GET" action="resolve.php">
<p><textarea name="uri" rows="1" cols="100" onkeypress="if(event.which===13&&!event.shiftKey){event.target.form.submit();event.preventDefault();}">lid://example.org/ex:id/1</textarea></p>
<input type="checkbox" name="print" id="print" checked><label for="print">Just print the SPARQL query; do not redirect (_print).</label><br>
<input type="checkbox" name="html" id="html" checked><label for="html">Display as HTML; only with _print (_html).</label><br>
<input type="checkbox" name="check" id="check"><label for="check">Check functional properties (_check).</label><br>
<input type="checkbox" name="infer" id="infer"><label for="infer">Infer from subproperties (_infer).</label><br>
<input type="checkbox" name="inverse" id="inverse"><label for="inverse">Include additional owl:inverseOf (_inverse).</label><br>
<input type="checkbox" name="unify" id="unify"><label for="unify">Unify with owl:sameAs (_unify).</label><br>
<input type="checkbox" name="first" id="first"><label for="first">Output only the first result (_first).</label><br>
<input type="submit" value="Resolve"></p>
</form>
<p>The prefixes recognized by the service are combined from the <a href="https://www.w3.org/2011/rdfa-context/rdfa-1.1.html">RDFa Core Initial Context</a> and the registered <a href="https://www.iana.org/assignments/uri-schemes/uri-schemes.xml">IANA URI schemes</a>. A JSON-LD context can be found <a href="context.php">here</a>.</p>
</body>
</html>