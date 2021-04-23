<!DOCTYPE html>
<html lang="en">
<head>
<title>lid: scheme structure</title>
<link rel="stylesheet" href="//is4.site/styles/terminal.css?theme=4">
</head>
<body>
<h1><code>lid:</code> URI scheme</h1>
<p>A <mark><code>lid:</code></mark> URI has one of the following structures:</p>
<pre><mark><q>lid://</q> host <q>/</q> ( [ <q>'</q> ] name <q>/</q> )+ value [ <q>@</q> ] [ <q>@</q> type ] [ <q>?</q> context ]</mark></pre>
<pre><mark><q>lid:</q> ( [ <q>'</q> ] name <q>/</q> )+ value [ <q>@</q> ] [ <q>@</q> type ] [ <q>?</q> context ]</mark></pre>
<dl>
<dt><code>host</code></dt>
<dd>The hostname of the server storing the target dataset. The server is queried, usually with the HTTP or HTTPS protocol, for the entity represented by the URI.</dd>
<dt><code>name</code></dt>
<dd>A URI name, as an absolute URI reference or a prefix (may be empty) and a local name, separated with <q>:</q>.</dd>
<dt><code>value</code></dt>
<dd>the compared value of the property chain. If followed by the <q>@</q>, it is treated as a <mark><code>name</code></mark> and expanded accordingly.</dd>
<dt><code>type</code></dt>
<dd>Specifies the type of the literal value. Could be a language code, a language range or a <mark><code>name</code></mark>. If it is omitted, the literal is simply compared by its string value, without a type comparison.</dd>
<dt><code>context</code></dt>
<dd>Additional key-value pairs. If the key starts on <mark><code>_</code></mark>, it is an option, otherwise it is a prefix (re)definition. The prefixes are processed in order. Assigning an empty literal value to a prefix name undefines the prefix.</dd>
</dl>
<p>All of special characters may be escaped with <q>%</q> per standard URI rules to be interpreted literally, without a special meaning.</p>
<p>The path portion of the URI consists of a property path, followed by an identifier. Each property corresponds to a step in the corresponding property chain with the identifier at its end and the identified entity at its beginning. <q>'</q> before a property represents its inverse. The initial node in the property path is considered the queried the entity, while the final node is the identifier (final component of the URI path).</p>
<p>When a <mark><code>name</code></mark> is expected, a special identifier may be used instead, if it doesn't match a valid production in its place. These are:</p>
<dl>
<dt><code>a</code></dt>
<dd>This is synonymous to the URI <mark><code>http://www.w3.org/1999/02/22-rdf-syntax-ns#type</code></mark> with no additional meaning.</dd>
<dt><code>uri</code></dt>
<dd>This links a URI node to its actual string representation, and has to be used when looking for an entity by its URI, as only literal nodes are compared with the <mark><code>value</code></mark>. Blank nodes and literal nodes do not have this property. When this property is to be materialized, it is represented by <mark><code>http://www.w3.org/2000/10/swap/log#uri</code></mark>, but it is not synonymous with it in any other case.</dd>
</dl>
<p>Every occurence of a <mark><code>name</code></mark> with a prefix is interpreted according to the defined prefixes in the current context. Definitions in the query portion are processed first and they specify the context for the path.</p>
<p>Every variable portion of the URI is eventually percent-decoded exactly once, even absolute URIs stored as a <mark><code>name</code></mark>. International characters (valid in IRI but not URI) are left unchanged.</p>
<p>There are several prefixes known initially. They are divided into three categories:</p>
<dl>
<dt>Common prefixes</dt><dd><pre>PREFIX rdf: &lt;http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX rdfs: &lt;http://www.w3.org/2000/01/rdf-schema#&gt;
PREFIX owl: &lt;http://www.w3.org/2002/07/owl#&gt;
PREFIX skos: &lt;http://www.w3.org/2004/02/skos/core#&gt;
PREFIX xsd: &lt;http://www.w3.org/2001/XMLSchema#&gt;</pre>
<p>These prefixes are always available and commonly used in the constructed queries. Redefining them will only affect their specific usage in the URI, not their generated usage in the query.</p></dd>
<dt>Common URI schemes</dt><dd><pre>PREFIX http: &lt;http:&gt;
PREFIX https: &lt;https:&gt;
PREFIX urn: &lt;urn:&gt;
PREFIX tag: &lt;tag:&gt;
PREFIX mailto: &lt;mailto:&gt;
PREFIX data: &lt;data:&gt;
PREFIX lid: &lt;lid:&gt;</pre>
<p>These prefixes are defined in order to make it possible to write <q>:</q> instead of <mark><code>%3A</code></mark> in a <mark><code>name</code></mark> without relying on the target endpoint to support these prefixes.</p></li>
<dt>Supplemental prefixes</dt><dd><pre>PREFIX base: &lt;&gt;</pre>
<p>Relative URIs are not allowed by the syntax, thus they have to be represted via <mark><code>base:</code></mark>. <q>.</q> and <q>..</q> are not handled in any special manner.</p>
</dd>
</dl>
<p>The empty prefix is always undefined initially. Any undefined prefix may still be used in any <mark><code>name</code></mark>, but it is up to the target endpoint to know them. This is the only case an invalid SPARQL query may be generated.</p>
<p>In addition to the prefixes declared above, a particular resolver (such as the one on this site) may define additional prefixes. The ones used here are as follows (ordered by priority):</p>
<ol>
<li>Permanent <a href="https://www.iana.org/assignments/uri-schemes/uri-schemes.xhtml">IANA URI schemes</a>. They are defined as themselves, like above.</li>
<li>Recommended <a href="https://www.w3.org/2011/rdfa-context/rdfa-1.1.html">RDFa Core Initial Context</a>. Only defintions that end on <mark><code>#</code></mark>, <mark><code>/</code></mark> or <mark><code>:</code></mark> are considered.</li>
<li>Provisional and historical IANA URI schemes longer than 3 characters. Defined like the other schemes.</li>
</ol>
<p>This means that some common prefixes may be treated as URI schemes (see <a href="conflicts">here</a> for examples). The syntax still allows to redefine any such prefix manually.</p>
<p>The list of default prefixes (<a href="context.jsonld">JSON-LD context</a>) is periodically synchronized with the source documents.</p>
<p><a href=".">Back to the main page.</a></p>
</body>
</html>