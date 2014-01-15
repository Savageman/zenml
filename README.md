ZenML
=====

This little project aims to use "Zen Coding" (which has now been renamed "Emmet") as a markup language to write template files.

It's early stage, you can still look around and even contribute if you want!

Quick guide
-----------

One HTML tag per line using Zen Coding syntax : `div #id .class1 .class2`

Nesting is done using indentation :
```
%h1#title text="Check this out:"
%article
    %span .date text="21/11/2013"
    %h2
        %a href="example.html"
            ZenML template example
%p text="This paragraph is below the article tag."
```

Will be rendered:

```html
<h1 id="title">Check this out:</h1>
<article>
    <span class="date">21/11/2013</span>
    <h2>
        <a href="example.html">ZenML template example</a>
    </h2>
</article>
<p>This paragraph is below the article tag.</p>
```

**Variables** can be inserted with the Mustache syntax `{{var}}`.

**Commands** can be inserted using the Handlebars syntax `{{#command}}`. Currently, only 3 commands are available:
`#if`, `#each` and `#empty`.

```
%p text="You can find my articles below:"
{{ #each articles }}
    %article
        %span .date text="{{date}}"
        %h2 text="{{title}}"
        %div .summary
            {{summary}}
%p text="Thanks for coming here and reading my articles."
```

Things I need to do next
------------------------

* Make command configurable using a callback,
* The `#include` command,
* Killer-feature: add rules based on tag / class / id
* "Internal" `@vars` (i.e. `@text` var in a tag to re-use the text content, `@first` `@odd` and `@even` vars inside a loop),
