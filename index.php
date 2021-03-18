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
<p><textarea name="uri" rows="1" cols="100" onkeypress="if(event.which===13&&!event.shiftKey){event.target.form.submit();event.preventDefault();}">lid://dbpedia.org/'foaf:depiction/myprefix:label/Earth?myprefix=rdfs:</textarea></p>
<input type="checkbox" name="print" id="print" checked><label for="print">Just print the SPARQL query; do not redirect (_print).</label><br>
<input type="checkbox" name="html" id="html" checked><label for="html">Display as HTML; only with _print (_html).</label><br>
<input type="checkbox" name="check" id="check"><label for="check">Check functional properties (_check).</label><br>
<input type="checkbox" name="infer" id="infer"><label for="infer">Infer from subproperties (_infer).</label><br>
<input type="checkbox" name="inverse" id="inverse"><label for="inverse">Include additional owl:inverseOf in paths (_inverse).</label><br>
<input type="checkbox" name="unify" id="unify"><label for="unify">Unify with owl:sameAs (_unify).</label><br>
<input type="checkbox" name="first" id="first"><label for="first">Output only the first result (_first).</label><br>
<label for="_format">Results format (__format): </label><select id="_format" name="_format">
<option disabled selected value></option>
<option value="text/turtle">Turtle</option><option value="application/x-nice-turtle">Turtle (beautified)</option><option value="application/rdf+json">RDF/JSON</option><option value="application/rdf+xml">RDF/XML</option><option value="text/plain">N-Triples</option><option value="application/xhtml+xml">XHTML+RDFa</option><option value="application/atom+xml">ATOM+XML</option><option value="application/odata+json">ODATA/JSON</option><option value="application/x-ld+json">JSON-LD (plain)</option><option value="application/ld+json">JSON-LD (with context)</option><option value="text/x-html+ul">HTML (list)</option><option value="text/x-html+tr">HTML (table)</option><option value="text/html">HTML+Microdata (basic)</option><option value="application/x-nice-microdata">HTML+Microdata (table)</option><option value="text/x-html-script-ld+json">HTML+JSON-LD (basic)</option><option value="text/x-html-script-turtle">HTML+Turtle (basic)</option><option value="text/x-html-nice-turtle">Turtle (beautified - browsing oriented)</option><option value="application/microdata+json">Microdata/JSON</option><option value="text/csv">CSV</option><option value="text/tab-separated-values">TSV</option><option value="application/x-trig">TriG</option><option value="text/cxml">CXML (Pivot Collection)</option><option value="text/cxml+qrcode">CXML (Pivot Collection with QRcodes)</option>
</select><br>
<input type="submit" value="Resolve">
</form>
<p>The prefixes recognized by the service are combined from the <a href="https://www.w3.org/2011/rdfa-context/rdfa-1.1.html">RDFa Core Initial Context</a> and the registered <a href="https://www.iana.org/assignments/uri-schemes/uri-schemes.xml">IANA URI schemes</a>. A JSON-LD context can be found <a href="context.php">here</a>.</p>
</body>
</html>