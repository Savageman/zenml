ZenML
=====

I love Handlebars, but I find it still lacks indentation-based DOM tree (like Haml) & Emmet/CSS syntax for ID and classes.

So I made this pre-processor to solve the problem. It's generates Handlebars.

Quick guide
-----------

* A line containing an HTML tag starts with `%` ;
* Indentation is used to create the DOM tree ;
* Indentation also works for Handlebars `{{#helpers}}` ;
* Attributes are added using the regular HTML syntax: `title="This is the title"` ;
* Text node can be added either:
  * On a new line using indentation
  * As a `text` attribute of the current tag: `text="This is my text"`
* Any unrecognised line is interpreted as plain-text.

Example
-------

```
%h1 text="This is my title"

%div #container

    {{ #each items }}
        %h2 text="{{title}}"
        %span .date text="{{date}}"
        %div .description
            The description is a bit longer,
            so I split it on several lines.

    {{ else }}
        %p text="Nothing here..."

%footer
    This is the end of the example.
```

is converted into:

```html
<h1>This is my title</h1>

<div id="container">

    {{#each items}}
        <h2>{{title}}</h2>
        <span class="date">{{date}}</span>
        <div class="description">
            The description is a bit longer,
            so I split it on several lines.
        </div>

    {{else}}
        <p>Nothing here...</p>
    {{/each}}
</div>

<footer>
    This is the end of the example.
</footer>
```

Limitations
-----------

(I'll be glad if people could help me out sorting these out.)

* You must put a space between the tag name and each CSS-like `#id` and `.classes` attributes ;
* Text node must be on a new line (it would be nice to allow it at the end of the line) ;
* Only one tag per line.


Todo
----

* Include some examples using [voodoophp/handlebars](https://www.github.com/mardix/Handlebars)

