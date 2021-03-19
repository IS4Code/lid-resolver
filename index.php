<!DOCTYPE html>
<html lang="en">
<head>
<title>lid: resolver</title>
<link rel="stylesheet" href="//is4.site/styles/terminal.css?theme=4">
</head>
<body>
<h1><code>lid:</code> URI resolver</h1>
<p>This service can be used as a resolver for URIs in the <mark><code>lid:</code></mark> scheme (described <a href="structure">here</a>).</p>
<p>This website is configured to resolve URIs in the form: <mark><code>https://<?=$_SERVER[HTTP_HOST]?>/lid://<span contenteditable="true">example.org/ex:id/1</span></code></mark>.<br>
You may also use the form below:</p>
<form method="GET" action="resolve">
<p><textarea name="uri" rows="1" cols="100" onkeypress="if(event.which===13&&!event.shiftKey){event.target.form.submit();event.preventDefault();}">lid://dbpedia.org/'foaf:depiction/myprefix:label/Earth?myprefix=rdfs:</textarea></p>
<input type="checkbox" name="print" id="print" checked><label for="print">Just print the SPARQL query; do not redirect (_print).</label><br>
<input type="checkbox" name="html" id="html" checked><label for="html">Display as HTML; only with _print (_html).</label><br>
<input type="checkbox" name="check" id="check"><label for="check">Check functional properties (_check).</label><br>
<input type="checkbox" name="infer" id="infer"><label for="infer">Infer from subproperties (_infer).</label><br>
<input type="checkbox" name="inverse" id="inverse"><label for="inverse">Include additional owl:inverseOf in paths (_inverse).</label><br>
<input type="checkbox" name="unify" id="unify"><label for="unify">Unify with owl:sameAs (_unify).</label><br>
<input type="checkbox" name="first" id="first"><label for="first">Output only the first result (_first).</label><br>
<label for="form">Query form (_form): </label><select id="form" name="form">
<option value="construct">CONSTRUCT</option><option value="select">SELECT</option><option value="describe">DESCRIBE</option>
</select><br>
<label for="_format">Results format (__format): </label><select id="_format" name="_format">
<option disabled selected value></option>
<option value="text/turtle">Turtle</option><option value="application/x-nice-turtle">Turtle (beautified)</option><option value="application/rdf+json">RDF/JSON</option><option value="application/rdf+xml">RDF/XML</option><option value="text/plain">N-Triples</option><option value="application/xhtml+xml">XHTML+RDFa</option><option value="application/atom+xml">ATOM+XML</option><option value="application/odata+json">ODATA/JSON</option><option value="application/x-ld+json">JSON-LD (plain)</option><option value="application/ld+json">JSON-LD (with context)</option><option value="text/x-html+ul">HTML (list)</option><option value="text/x-html+tr">HTML (table)</option><option value="text/html">HTML+Microdata (basic)</option><option value="application/x-nice-microdata">HTML+Microdata (table)</option><option value="text/x-html-script-ld+json">HTML+JSON-LD (basic)</option><option value="text/x-html-script-turtle">HTML+Turtle (basic)</option><option value="text/x-html-nice-turtle">Turtle (beautified - browsing oriented)</option><option value="application/microdata+json">Microdata/JSON</option><option value="text/csv">CSV</option><option value="text/tab-separated-values">TSV</option><option value="application/x-trig">TriG</option><option value="text/cxml">CXML (Pivot Collection)</option><option value="text/cxml+qrcode">CXML (Pivot Collection with QRcodes)</option>
</select><br>
<input type="submit" value="Resolve">
</form>
<p>The prefixes recognized by the service are combined from the <a href="https://www.w3.org/2011/rdfa-context/rdfa-1.1.html">RDFa Core Initial Context</a> and the registered <a href="https://www.iana.org/assignments/uri-schemes/uri-schemes.xhtml">IANA URI schemes</a>. A JSON-LD context can be found <a href="context.jsonld">here</a>.</p>
<section>
<h2>Examples</h2>
<p>The options above can be also specified in the query part of the URI. Any query parameter starting with <mark><code>__</code></mark> is passed to the target endpoint.</p>
<dl>
<dt><code>lid://example.org/rdfs:label/1</code></dt>
<dd>Identifies anything that has a property <mark><code>rdfs:label</code></mark> (known prefix) with a literal value of <mark><q>1</q></mark> (any type).</dd>
<dt><code>lid://example.org/rdfs:label/1@xsd:integer</code></dt>
<dd>Identifies anything that has the property with a literal value of <mark><code>"1"^^xsd:integer</code></mark>.</dd>
<dt><code>lid://example.org/rdfs:label/Person@en</code></dt>
<dd>Identifies anything that has the property with a literal value of <mark><code>"Person"@en</code></mark> (English language tag).</dd>
<dt><code>lid://example.org/ex:id/1</code></dt>
<dd>Identifies anything that has a property <mark><code>ex:id</code></mark> with the specified value. The resolution of <mark><code>ex:</code></mark> is performed by the target endpoint, as it is an undefined prefix.</dd>
<dt><code>lid://example.org/foaf:mbox/mailto:address%40example.org@</code></dt>
<dd>Identifies anything that has a property <mark><code>foaf:mbox</code></mark> (known prefix) with a value of <mark><code>&lt;mailto:address@example.org&gt;</code></mark>.</dd>
<dt><code>lid://example.org/'foaf:age/foaf:mbox/mailto:address%40example.org@</code></dt>
<dd>Identifies the <mark><code>foaf:age</code></mark> of the specified entity.</dd>
<dt><code>lid://example.org/ex:id/1?ex=http://example.org/</code></dt>
<dd>Identifies anything with the specified value of <mark><code>&lt;http://example.org/id&gt;</code></mark> (<mark><code>http:</code></mark> is a known prefix).</dd>
<dt><code>lid://example.org/base:id/1</code></dt>
<dd>Identifies anything with the specified value of <mark><code>&lt;id&gt;</code></mark> (<mark><code>base:</code></mark> is a known prefix).</dd>
</dl>
</section>
</body>
</html>