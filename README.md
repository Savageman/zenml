ZenML
=====

This little project aims to use "Zen Coding" (which has now been renamed "Emmet") as a markup language to write template files.

It's very early stage, you can still look around and even contribute if you want!

Quick guide
-----------

One HTML tag per line using Zen Coding syntax : `div#id.class1.class2 Some text here`

Nesting is done using indentation :
```
h1#title Check this out:
article
    span.date 21/11/2013
    h2
        a[href=example.html] ZenML template example
p This paragraph is below the article tag.
```

Will be rendered:

```html
<h1 id="title">Check this out:</h1>
<article>
    <span class="date">21/11/2013</span>
    <h2>
        <a href="example/html">ZenML template example</a>
    </h2>
</article>
<p>This paragraph is below the article tag.</p>
```

**Variables** can be inserted with 2 syntaxs `(:var)` or `{var}`.

Variables followed by a single or double bang will allow respectively yo deny an attribute or an entire line using based on its emptyness :
* `span.(:var1!) Text` will render as `<span>Text</span>` when `(:var1)` is empty.
* `span.(:var2) Text` will not render at all when `(:var1)` is empty.


**Commands** are not implemented yet, here is how I plan to do them:
```
p You can find my articles below:
-- loop articles --
    article
        span.date {date}
        h2 {title}
        div.summary {summary!}
p Thanks for coming here and reading my articles.

```

Here's a quick list to remind me what to start with: `loop`, `empty`, `!empty`, `raw` (to insert raw text or html), `zenml` (to restore parser), `include`.
