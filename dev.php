<?php

include 'zenml.php';

// I don't like this syntax, we can't understand what ++ and -- do at first sight
$template = <<<TEMPLATE
header My page title
div#blog
    (:title_tag).article
        a[href=test.html] My article
    section.summary.(:summary_class)
        p.date (:date)
    div.content The content goes here
    ++categories div.categories
        span.category [(:name)]
    --p No article here
footer Mon footer
TEMPLATE;

// Old intermediate array structure (simple parser)
debug(ZenmlSimple::parse($template));

// Incoming string
debug($template);
// Intermediate array structure
debug(Zenml::parse($template));

$vars = array(
    'summary_class' => 'js-summary',
    'title_tag' => 'h4',
    'date' => date('d/m/Y', strtotime('now')),
    'categories' => array(
        array('name' => 'First category'),
        array('name' => 'Another category'),
    ),
);
debug($vars);
// Rendering
debug(Zenml::render($template, $vars));


function debug($var)
{
    echo '<pre>';
    echo is_string($var) ? htmlspecialchars($var) : print_r($var, true);
    echo '</pre>';
}


// @todo 1 : handle optional tag (default 'div'), both inside loop and outside loop
// @todo 2 : replace var syntax : use "(:var)" or "{var}" instead of "%var"
// @todo 3 : add a new syntax "(!!var)" or "{!!var}}" to prevent line showing when var is empty (shortcut for -- !empty var -- ...)
// @todo 4 : handle attributes [title=][src=]
// @todo 5 : loop vars (first, last, counter, odd/even)


// Goal : make this work
/*
$template = <<<TEMPLATE
header My title here
-- loop articles --
    div.articles
        article.(:color).{color}
        span.date (:date)
        header
            h2.title
            p (:description)
            -- loop tags --
                section.(:type)[title={label}]
                    img.icon[src=static/(!!icon).png][alt=]
                    (value)
-- !empty articles --
    p No articles are available.
TEMPLATE;
//*/
