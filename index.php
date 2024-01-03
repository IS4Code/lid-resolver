<?php
function getVarText($name, $default)
{
  return htmlspecialchars(@$_GET[$name] ?? $default);
}

function getVarChecked($name)
{
  return @$_GET[$name] ? ' checked' : '';
}

function getVarSelected($name, $option, $default = false)
{
  return (($default && !isset($_GET[$name])) || (@$_GET[$name] === $option)) ? ' selected' : '';
}

function options($name, $list, $default = null)
{
  foreach($list as $key => $value)
  {
     ?><option value="<?=$key?>"<?=
  getVarSelected($name, $key, $key === $default)
?>><?=$value?></option><?php
  }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<title>lid: resolver</title>
<link rel="stylesheet" href="//is4.site/styles/terminal.css?theme=4">
</head>
<body>
<h1><code>lid:</code> URI resolver</h1>
<p>This service can be used as a resolver for URIs in the <b><code>lid:</code></b> scheme (described <a href="structure">here</a>).</p>
<p>The webserver is also configured to resolve URIs in the form: <b><code>https://<?=$_SERVER[HTTP_HOST]?>/lid:<span contenteditable="true">//example.org/ex:id/1<br></span></code></b>
You may also use the form below:</p>
<form method="GET" action="resolve">
<p><textarea name="uri" rows="1" cols="100" onkeypress="if(event.which===13&amp;&amp;!event.shiftKey){event.target.form.submit();event.preventDefault();}"><?=
  getVarText('uri', 'lid://my.data.is4.site/:nick/IS4?=foaf:')
?></textarea></p>
<input type="checkbox" name="check" id="check"<?=
  getVarChecked('check')
?>><label for="check">Check functional properties (<code>_check</code>).</label><br>
<input type="checkbox" name="infer" id="infer"<?=
  getVarChecked('infer')
?>><label for="infer">Infer from subproperties (<code>_infer</code>).</label><br>
<input type="checkbox" name="inverse" id="inverse"<?=
  getVarChecked('inverse')
?>><label for="inverse">Include additional owl:inverseOf in paths (<code>_inverse</code>).</label><br>
<input type="checkbox" name="unify_owl" id="unify_owl"<?=
  getVarChecked('unify_owl')
?>><label for="unify_owl">Unify with owl:sameAs (<code>_unify_owl</code>).</label><br>
<input type="checkbox" name="unify_skos" id="unify_skos"<?=
  getVarChecked('unify_skos')
?>><label for="unify_skos">Unify with skos:exactMatch (<code>_unify_skos</code>).</label><br>
<input type="checkbox" name="prefixes" id="prefixes"<?=
  getVarChecked('prefixes')
?>><label for="prefixes">Resolve undefined prefixes (<code>_prefixes</code>).</label><br>
<input type="checkbox" name="first" id="first"<?=
  getVarChecked('first')
?>><label for="first">Output only the first result (<code>_first</code>).</label><br>
<label for="action">Query action (<code>_action</code>): </label><select id="action" name="action">
<?php
options('action', array(
  'navigate' => "Navigate",
  'describe' => "Describe",
  'redirect' => "Redirect",
  'print' => "Print",
  'debug' => "Debug"
), 'navigate');
?>
</select><br>
<label for="form">Query form (<code>_form</code>): </label><select id="form" name="form">
<?php
options('form', array(
  'construct' => "CONSTRUCT",
  'select' => "SELECT",
  'describe' => "DESCRIBE"
));
?>
</select><br>
<label for="method">Query method (<code>_method</code>): </label><select id="method" name="method">
<?php
options('form', array(
  'sparql' => "SPARQL",
  'triples' => "Triple Pattern Fragments"
));
?>
</select><br>
<label for="_format">Results format (<code>__format</code>): </label><select id="_format" name="_format">
<option disabled selected value></option>
<optgroup label="Graph formats">
<?php
options('_format', array(
  'text/turtle' => "Turtle", 'application/x-nice-turtle' => "Turtle (beautified)", 'application/rdf+json' => "RDF/JSON", 'application/rdf+xml' => "RDF/XML", 'text/plain' => "N-Triples", 'application/xhtml+xml' => "XHTML+RDFa", 'application/atom+xml' => "ATOM+XML", 'application/odata+json' => "ODATA/JSON", 'application/x-ld+json' => "JSON-LD (plain)", 'application/ld+json' => "JSON-LD (with context)", 'text/x-html+ul' => "HTML (list)", 'text/x-html+tr' => "HTML (table)", 'text/html' => "HTML+Microdata (basic)", 'application/x-nice-microdata' => "HTML+Microdata (table)", 'text/x-html-script-ld+json' => "HTML+JSON-LD (basic)", 'text/x-html-script-turtle' => "HTML+Turtle (basic)", 'text/x-html-nice-turtle' => "Turtle (beautified - browsing oriented)", 'application/microdata+json' => "Microdata/JSON", 'text/csv' => "CSV", 'text/tab-separated-values' => "TSV", 'application/x-trig' => "TriG", 'text/cxml' => "CXML (Pivot Collection)", 'text/cxml+qrcode' => "CXML (Pivot Collection with QRcodes)"
));
?>
</optgroup>
<optgroup label="Table formats">
<?php
options('_format', array(
  'text/html' => "HTML", 'text/x-html+tr' => "HTML (Faceted Browsing Links)", 'application/vnd.ms-excel' => "Spreadsheet", 'application/sparql-results+xml' => "XML", 'application/sparql-results+json' => "JSON", 'application/javascript' => "Javascript", 'text/turtle' => "Turtle", 'application/rdf+xml' => "RDF/XML", 'text/plain' => "N-Triples", 'text/csv' => "CSV", 'text/tab-separated-values' => "TSV"
));
?>
</optgroup>
</select><br>
<input type="submit" value="Resolve">
</form>
<p>Using an undefined prefix is valid, but requires the target to understand it. The prefixes recognized by the service are as follows (ordered by priority):</p>
<ol>
<li>Permanent <a href="https://www.iana.org/assignments/uri-schemes/uri-schemes.xhtml">IANA URI schemes</a>. They are defined as themselves.</li>
<li>Recommended <a href="https://www.w3.org/2011/rdfa-context/rdfa-1.1.html">RDFa Core Initial Context</a>. Only definitions that end on <b><code>#</code></b>, <b><code>/</code></b> or <b><code>:</code></b> are considered.</li>
<li>Provisional and historical IANA URI schemes longer than 3 characters. Defined like the other schemes.</li>
</ol>
<p>This means that some common prefixes may be overwritten by URI schemes (see <a href="conflicts">here</a> for examples). The syntax still allows to redefine any such prefix manually.</p>
<p>The list of default prefixes (<a href="context.jsonld">JSON-LD context</a>) is periodically synchronized with the source documents. This could result in ambiguities between different resolvers or points in time, since a particular prefix could turn from undefined to defined when one of the two lists is modified. This is easily prevented by undefining the prefix manually in the URI, or by using the empty prefix or a prefix that starts on <q>x.</q>.</p>
<p>If an entry is removed from the source lists, it could also prevent this resolver from correctly processing URIs that use the prefix. It is assumed entries are never removed from the sources, and if so, it justifies the consequences.</p>
<section>
<h2>Examples</h2>
<p>The options above can be also specified in the query part of the URI. Any query parameter starting with <b><code>__</code></b> is stripped of it and passed to the target endpoint.</p>
<dl>
<dt><code>lid://example.org/rdfs:label/1</code></dt>
<dd>Identifies anything that has a property <b><code>rdfs:label</code></b> (known prefix) with a literal value of <b><q>1</q></b> (any type).</dd>
<dt><code>lid://example.org/rdfs:label/1@</code></dt>
<dd>Identifies anything that has the property with a literal value of <b><code>"1"</code></b> (plain untagged literal in RDF 1.0, <code>xsd:string</code> in RDF 1.1).</dd>
<dt><code>lid://example.org/rdfs:label/1@xsd:integer</code></dt>
<dd>Identifies anything that has the property with a literal value of <b><code>"1"^^xsd:integer</code></b>.</dd>
<dt><code>lid://example.org/rdfs:label/Person@en</code></dt>
<dd>Identifies anything that has the property with a literal value of <b><code>"Person"@en</code></b> (English language tag).</dd>
<dt><code>lid://example.org/ex:id/1</code></dt>
<dd>Identifies anything that has a property <b><code>ex:id</code></b> with the specified value. The resolution of <b><code>ex:</code></b> is performed by the target endpoint, because it is an undefined prefix.</dd>
<dt><code>lid://example.org/foaf:mbox/uri/mailto:address%40example.org</code></dt>
<dd>Identifies anything that has a property <b><code>foaf:mbox</code></b> (known prefix) with a value of <b><code>&lt;mailto:address@example.org&gt;</code></b> (a URI).</dd>
<dt><code>lid://example.org/'foaf:age/foaf:mbox/uri/mailto:address%40example.org</code></dt>
<dd>Identifies the <b><code>foaf:age</code></b> of the specified entity.</dd>
<dt><code>lid://example.org/rdf:value/uri/rdf:nil</code></dt>
<dd>Identifies anything that has the property with a value of <b><code>&lt;rdf:nil&gt;</code></b> (a URI) &ndash; likely incorrect!</dd>
<dt><code>lid://example.org/rdf:value/uri/$rdf:nil</code></dt>
<dd>Identifies anything that has the property with a value of <b><code>rdf:nil</code></b> (using the known prefix).</dd>
<dt><code>lid://example.org/ex:id/1?ex=http://example.org/</code></dt>
<dd>Identifies anything with the specified value of the <b><code>&lt;http://example.org/id&gt;</code></b> property (<b><code>http:</code></b> is treated as a known prefix).</dd>
<dt><code>lid://example.org/base:id/1</code></dt>
<dd>Identifies anything with the specified value of the <b><code>&lt;id&gt;</code></b> property (<b><code>base:</code></b> is a known prefix for producing relative URIs).</dd>
</dl>
</section>
</body>
</html>