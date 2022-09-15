<!DOCTYPE html>
<html lang="en">
<head>
<title>lid: scheme structure</title>
<link rel="stylesheet" href="//is4.site/styles/terminal.css?theme=4">
</head>
<body>
<p style="float:right"><a href=".">Back to the resolver.</a></p>
<h1><code>lid:</code> URI scheme</h1>
<p>A <mark><code>lid:</code></mark> URI has the following structure:</p>
<pre><mark><q>lid:</q> [ <q>//</q> host <q>/</q> ] ( [ <q>'</q> ] name <q>/</q> )* [ <q>$</q> ] value [ <q>@</q> type ] [ <q>?</q> context ] [ <q>#</q> fragment ]</mark></pre>
<dl>
<dt><code>host</code></dt>
<dd>The hostname of the server storing the target dataset. The server is queried, usually with the HTTP or HTTPS protocol, for the entity represented by the remainder of the URI.</dd>
<dt><code>name</code></dt>
<dd>A URI name, as an absolute URI reference or a prefix (may be empty) and a local name, separated with <q>:</q>.</dd>
<dt><code>value</code></dt>
<dd>the compared value of the property chain. If preceded by the <q>$</q>, it is treated as a <mark><code>name</code></mark> and expanded accordingly. May be empty.</dd>
<dt><code>type</code></dt>
<dd>Specifies the type of the literal value. Could be a language code, a language range, a <mark><code>name</code></mark>, or empty. If it is omitted altogether, the literal is simply compared by its string value, without a type comparison. A valid language code or language range with an additional hyphen (<q>-</q>) at the end is always interpreted as a language range stripped of it. Special names listed below are not applicable here, as they already match a language code.</dd>
<dt><code>context</code></dt>
<dd>Additional key-value pairs. If the key starts on <mark><code>_</code></mark>, it is an option, otherwise it is a prefix (re)definition (without the <q>:</q>) and the value is treated as a <mark><code>name</code></mark>. The prefixes are processed in order, that is the second prefix definition uses the context created by the first prefix definition, and so on. Assigning an empty literal value to a prefix name undefines the prefix.</dd>
<dt><code>fragment</code></dt>
<dd>Used to find the target entity within the resource specified by the URI by the navigator. If the location of the resource already contains a fragment, it is replaced.</dd>
</dl>
<p>All of special characters may be escaped with <q>%</q> per standard URI rules to be interpreted literally, without a special meaning. Inside a <mark><code>name</code></mark>, characters <q>!</q>, <q>&amp;</q>, <q>(</q>, <q>)</q>, <q>*</q>, <q>+</q>, <q>,</q>, and <q>;</q> are reserved for future possible use and must be percent-encoded.</p>
<p>The path portion of the URI consists of a property path, followed by an identifier. Each property corresponds to a step in the corresponding property chain with the identifier at its end and the identified entity at its beginning. <q>'</q> before a property represents its inverse. The initial node in the property path is considered the queried entity, while the final node is the identifier (final component of the URI path).</p>
<p>When a <mark><code>name</code></mark> is expected, a special identifier may be used instead, which doesn't match its usual structure. These are:</p>
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
PREFIX file: &lt;file:&gt;
PREFIX ftp: &lt;ftp:&gt;
PREFIX lid: &lt;lid:&gt;</pre>
<p>These prefixes are defined in order to make it possible to write <q>:</q> instead of <q>%3A</q> in a <mark><code>name</code></mark> without relying on the target endpoint to support these prefixes.</p><dd>
<dt>Supplemental prefixes</dt><dd><pre>PREFIX base: &lt;&gt;</pre>
<p>Relative URIs are not allowed by the syntax, thus they have to be represented via <mark><code>base:</code></mark>. <q>.</q> and <q>..</q> are not handled in any special manner.</p>
<p>This prefix would make it possible to express an absolute URI, like in <mark><code>base:urn:something</code></mark>. Producing absolute URIs this way is therefore explicitly disallowed: a prefix that denotes a relative URI cannot be used to produce an absolute URI (the converse is already true by definition for absolute URIs).</p>
</dd>
</dl>
<p>The empty prefix is always undefined initially, as well as any prefix that starts on <q>x.</q>. Any undefined prefix may still be used in any <mark><code>name</code></mark>, but it is up to the target to recognize it.</p>
<p>In addition to the prefixes declared above, a particular resolver may define additional prefixes.</p>
<section>
<h2>Examples of valid syntax</h2>
<p>All of the URIs below are valid, with or without a host portion (<q>//example.org/</q> after <q>lid:</q>).</p>
<dt><code>lid:</code></dt>
<dd>The path may be omitted completely, in which case the URI refers to any empty literal value.</dd>
<dt><code>lid:1@xsd:integer</code></dt>
<dd>This refers to the literal value <mark><code>"1"^^xsd:integer</code></mark> itself.</dd>
<dt><code>lid:1@</code></dt>
<dd>This refers to the literal value <mark><code>"1"</code></mark>, which is treated as a plain untagged literal in RDF 1.0 and an <mark><code>xsd:string</code></mark>-typed literal in RDF 1.1, which may be considered distinct entities by the target.</dd>
<dt><code>lid:example@en</code></dt>
<dd>This refers to the string <q>example</q> in the English language.</dd>
<dt><code>lid:$a</code></dt>
<dd>This refers to the literal <q>http://www.w3.org/1999/02/22-rdf-syntax-ns#type</q> (with any datatype).</dd>
<dt><code>lid:uri/mailto%3Auser%40example.org</code></dt>
<dt><code>lid:uri/mailto:user%40example.org</code></dt>
<dt><code>lid:uri/$mailto%3Auser%40example.org</code></dt>
<dt><code>lid:uri/$mailto:user%40example.org</code></dt>
<dd>This refers to the entity identified by the URI <q>mailto:user@example.org</q>. Only in the last case, the <q>mailto:</q> part is treated as an actual prefix (defined as itself) and other vocabulary prefixes may be used.</dd>
<dt><code>lid:rdfs:isDefinedBy/uri/$foaf:</code></dt>
<dd>This refers to any entity that is defined by the FOAF vocabulary.</dd>
<dt><code>lid:rdfs:label/'uri/rdf:value/x</code></dt>
<dd>This refers to an entity that has a textual label (as <mark><code>xsd:anyURI</code></mark>) which can be interpreted as the URI of an entity with value <q>x</q>.</dd>
</section>
<section>
<h2>Interpretation</h2>
<p>A <code>lid:</code> URI on its own usually refers to entities described by a particular dataset. The purpose of creating such a URI is to produce a persistent identifier when one is unavailable (such as for a blank node) which is easy to read and interpret, and serves as a link into the dataset.</p>
<p><code>lid:</code> URIs do not have a single possible resolution mechanism, but they are designed for use with SPARQL endpoints (located at <q>/sparql</q> under a particular host) and a particular resolver may use parts of the URI to construct a SPARQL query which retrieves the identified resource, in some form specific to the resolver.</p>
<p>The basic translation to a SPARQL query is simple and only uses a single property path, coupled with a check on the identifier. More advanced resolvers, such as the one <a href=".">hosted here</a>, may however offer additional features, for example basic inference from subproperties, or unification based on standard properties like <mark><code>owl:sameAs</code></mark>, in which case the query may become more complex, while still resembling the simple one.</p>
<p>The SPARQL query generated by a resolver should generally be valid, but there is one exception: unbound prefixes may be used. These are not part of the standard SPARQL syntax, but they are commonly understood by SPARQL endpoint implementations. Using an unbound prefix means using whichever namespace is understood by the endpoint for that prefix, if some at all.</p>
<h2>Semantics</h2>
<p>A <code>lid:</code> URI can be constructed to point to specific resources which can be thought of as synonymous under the RDF semantics. Here are some examples of possible entailment that may arise automatically from the use of a <code>lid:</code> URI.</p>
<pre>&lt;lid:example@en&gt; owl:sameAs "example"@en . # a property-less lid: URI is a way to identify a literal value
&lt;lid:uri/urn:something&gt; owl:sameAs &lt;urn:something&gt; . # a way to encode a normal URI if needed, or to shorten it via a known prefix
&lt;lid:15&gt; skos:narrower "15", 15, "15"^^xsd:double . # the concept of a literal value with unspecified type is broader than any of the concrete literals
&lt;lid:hello@en-*&gt; skos:narrower "hello"@en-us, "hello"@en-gb . # likewise for language ranges
</pre>
<p>Additionally, the presence of a hostname in the URI might change its meaning in these ways:</p>
<ul>
  <li>If an unbound prefix is used, its resolution depends solely on the target endpoint and may produce different results, leading to completely unrelated URIs for different endpoints.</li>
  <li>Even if the URI is unambiguous, its resolution could very easily be affected by the knowledge the target endpoint has access to, and could yield different, but semantically linked, results. This link depends on the kind of properties used in the path, e.g. using only inverse functional properties implies <mark><code>owl:sameAs</code></mark>, but other properties may warrant weaker links.</li>
  <li>Even for unambiguous entities (literals or URIs), the interpretation of such URIs could require the participation of the endpoint in further description of the entity. If two endpoints give conflicting facts about the entity, the issue could be resolved by treating the two URIs as different entities, each belonging to its endpoint's world-view.</li>
</ul>
</section>
</body>
</html>