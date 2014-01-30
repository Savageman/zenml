ZenML internal
==============

This document explains how it works.

The parsing is done in 3 steps:
1. Build a tree based on indentation ;
2. Analyse each line to figure out which one is a tag or a helper (assume plain-text when unknown) ;
3. Dispatch children into their parent node (to prepare rendering) ;
4. Rendering itself.

Example
-------

Assume the following input string:

```
%div id="test"
    %p text="First line"
    %p text="Second line"
Plain-text for the end
```

The **first** step builds a tree based on indentation

```php
array(
    0 => '%div id="test"'
    1 => array(
        0 => '%p text="First line"',
        1 => '%p text="Second line"',
    ),
    2 => 'Plain-text for the end',
)
```

The **second** step figure out which one is a tag or a helper (assume plain-text when unknown):

```php
array(
    0 => array(
        'type' => 'tag',
        'tag' => 'div',
        'attrs' => array(
            'id' => 'test',
        ),
    ),
    1 => array(
        0 => array(
            'type' => 'tag',
            'tag' => 'p',
            'attrs' => array(
                'text' => 'First line',
            ),
        ),
        1 => array(
            'type' => 'tag',
            'tag' => 'p',
            'attrs' => array(
                'text' => 'Second line',
            ),
        ),
    ),
    2 => array(
        'type' => 'text',
        'text' => 'Plain-text for the end',
    ),
);
```

The **third** step moves children nodes appropriately:

```php
array(
    0 => array(
        'type' => 'tag',
        'tag' => 'div',
        'attrs' => array(
            'id' => 'test',
        ),
        'children' => array(
            0 => array(
                'type' => 'tag',
                'tag' => 'p',
                'attrs' => array(
                    'text' => 'First line',
                ),
            ),
            1 => array(
                'type' => 'tag',
                'tag' => 'p',
                'attrs' => array(
                    'text' => 'Second line',
                ),
            ),
        ),
    ),
    2 => array(
        'type' => 'text',
        'text' => 'Plain-text for the end',
    ),
);
```

The **fourth** step iterates to do the rendering:


```html
<tag id="test">
    <p>First line</p>
    <p>Second line</p>
</tag>
Plain-text for the end
```
