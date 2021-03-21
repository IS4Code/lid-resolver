<!DOCTYPE html>
<html lang="en">
<head>
<title>lid: scheme structure</title>
<link rel="stylesheet" href="//is4.site/styles/terminal.css?theme=4">
</head>
<body>
<h1><code>lid:</code> URI scheme</h1>
<p>A <mark><code>lid:</code></mark> URI has the following structure:</p>
<pre><mark><q>lid://</q> host <q>/</q> ( [ <q>'</q> ] name <q>/</q> )+ value [ <q>@</q> type ] [ <q>?</q> context ]</mark></pre>
<dl>
<dt><code>host</code></dt>
<dd>The hostname of the server storing the target dataset.</dd>
<dt><code>name</code></dt>
<dd>A URI name, as an absolute URI reference or a prefix and a local name, separated with <q>:</q>.</dd>
<dt><code>value</code></dt>
<dd>the compared value of the property chain; interpretation based on <mark><code>type</code></mark>.</dd>
<dt><code>type</code></dt>
<dd>Specifies the type of the literal. Could be a language code, a language range, a <mark><code>name</code></mark> or empty, in which case <mark><code>value</code></mark> is treated as a <mark><code>name</code></mark>. If it is omitted together with the <q>@</q>, the literal is simply compared by its string value.</dd>
<dt><code>context</code></dt>
<dd>Additional key-value pairs. If the key starts on <mark><code>_</code></mark>, it is an option, otherwise it is a prefix (re)definition. The prefixes are processed in order. Assigning an empty literal value to a prefix name undefines the prefix.</dd>
</dl>
<p>The path portion of the URI consists of a property path, followed by an identifier. Each property corresponds to a step in the corresponding property chain with the identifier at its end and the identified entity at its beginning. <q>'</q> before a property represents its inverse.</p>
<p>Every occurence of a <mark><code>name</code></mark> with a prefix is interpreted according to the defined prefixes in the current context. Definitions in the query portion are processed before the path.</p>
<p>Every variable portion of the URI is eventually percent-decoded exactly once. International characters (valid in IRI but not URI) are left unchanged.</p>
<p>There are several prefixes known initially. They are divided into three categories:</p>
<dl>
<dt>Common prefixes</dt><dd><pre>PREFIX rdf: &lt;http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX rdfs: &lt;http://www.w3.org/2000/01/rdf-schema#&gt;
PREFIX owl: &lt;http://www.w3.org/2002/07/owl#&gt;
PREFIX skos: &lt;http://www.w3.org/2004/02/skos/core#&gt;
PREFIX xsd: &lt;http://www.w3.org/2001/XMLSchema#&gt;</pre>
<p>These prefixes are always available and sometimes also used in the constructed queries. Redefining them will only affect their specific usage in the URI, not their generated usage in the query.</p></dd>
<dt>Common URI schemes</dt><dd><pre>PREFIX http: &lt;http:&gt;
PREFIX https: &lt;https:&gt;
PREFIX urn: &lt;urn:&gt;
PREFIX tag: &lt;tag:&gt;
PREFIX mailto: &lt;mailto:&gt;
PREFIX lid: &lt;lid:&gt;</pre>
<p>These prefixes are defined in order to make it possible to write <q>:</q> instead of <mark><code>%3A</code></mark>.</p></li>
<dt>Supplemental prefixes</dt><dd><pre>PREFIX base: &lt;&gt;</pre>
<p>Relative URIs are not allowed by the syntax, thus they have to be represted via <mark><code>base:</code></mark>.</p>
</dd>
</dl>
<p>In addition to the prefixes declared above, a particular resolver (such as the one on this site) may define additional prefixes. The ones used here are as follows (ordered by priority):</p>
<ol>
<li>Permanent <a href="https://www.iana.org/assignments/uri-schemes/uri-schemes.xhtml">IANA URI schemes</a>. They are defined as themselves, like above.</li>
<li>Recommended <a href="https://www.w3.org/2011/rdfa-context/rdfa-1.1.html">RDFa Core Initial Context</a>. Only defintions that end on <mark><code>#</code></mark> or <mark><code>/</code></mark> are considered.</li>
<li>Provisional and historical IANA URI schemes. Defined like the other schemes.</li>
</ol>
<p>This means that some common prefixes may be treated as URI schemes (see <a href="conflicts">here</a> for examples). Such a prefix can be undefined or redefined to the intended value manually.</p>
<p>The list of default prefixes is periodically synchronized with the source documents.</p>
<p><a href=".">Back to the main page.</a></p>
</body>
</html>