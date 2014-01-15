<?php

include 'zenml.php';

// Zen coding + Haml indentation + Handlebars
$template = <<<TEMPLATE
%header #header
    My page title
%div #blog .class
    %{{title_tag}} .article
        %a href=test.html title="My article"
            My article
    %section .summary .{{summary_class}}
        %p .date
            {{ #dateFormat date format="d/m/Y H:i:s" }}
    %div .content
        The content goes here
    {{ #if categories }}
        %div .categories
            {{ -- idem "categories" est facultatif  }}
            {{ #each }}
                {{ -- {{ this.name }} == {{ name }}  }}
                %span .category
                    [{{ this.name }}]
    {{ #empty categories }}
        %p text="No article here"
%footer text="Mon footer"
TEMPLATE;

// Incoming string
$zenml = new Zenml_Engine(array(
    'prepend' => '  ',
    'indentation' => "\t",
));

$zenml->registerBlock('dateFormat', function($zenml, $node, $data, $context) {
    $zenml(array(
        array(
            'type' => 'text',
            'text' => date($node['block']['attrs']['format'], is_numeric($data[$context]) ? $data[$context] : strtotime(str_replace('/', '-', $data[$context]))),
        )));
});

$data = array(
    'summary_class' => 'js-summary',
    'title_tag' => 'h4',
    'date' => strtotime('now'),
    'categories' => array(
        array('name' => 'First category'),
        array('name' => 'Another category'),
    ),
);

debug($template);
debug($data);
// Rendering
debug($zenml->render($template, $data));


function debug($var)
{
    echo '<pre>';
    echo is_string($var) ? htmlspecialchars($var) : print_r($var, true);
    echo '</pre>';
}

// @todo : loop vars (first, last, counter, odd/even)
