<?php

// Use Composer autoload.php (assume we're installed in the default 'vendor/' directory)
include '../../autoload.php';

// Zen/Emmet coding + Haml indentation + Handlebars helpers
$template = <<<TEMPLATE
%tag id="test"
    %p text="First line"
    %p text="Second line"
Plain-text for the end
TEMPLATE;

$zenml = new Zenml\Zenml(array(
    'prepend' => '',
    'input_indentation' => '    ',
    'output_indentation' => '    ',
));

d($zenml->render($template));


// Debugging
function debug($var)
{
    echo '<pre>';
    echo is_string($var) ? htmlspecialchars($var) : print_r($var, true);
    echo '</pre>';
}

// d aliased to debug
function d($var)
{
    debug($var);
}

// dd = debug + die
function dd($var)
{
    debug($var);
    exit();
}

