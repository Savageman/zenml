<?php

namespace Zenml;

/**
 * Parser for Zenml syntax, an agnostic template language
 *
 * Class Zenml_Engine
 */
class Zenml
{
    protected $options = array();

    public function __construct($options = array())
    {
        $options = array_merge(array(
            'prepend' => '',
            'input_indentation' => '    ',
            'output_indentation' => '    ',
        ), $options);

        $this->setOptions($options);
    }

    public function setOptions(array $options = array())
    {
        if (!array_key_exists('empty_line', $options)) {
            $options['empty_line'] = $options['prepend'];
        }
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Transform the input Zenml template into a Handlebars template string
     *
     * @param string $templateString
     * @return string
     */
    public function render($templateString)
    {
        // Convert template string to tree based on indentation
        $tree = static::_indentedTextToArray($templateString, $this->options['input_indentation']);
        // Figure out which lines contains a tag or a helper (assume plain-text when unknown)
        $tree = static::_parseTree($tree);
        // Move children nodes appropriately (to prepare rendering)
        $parsedTree = static::_parseChildren($tree);
        debug($parsedTree);

        return static::_renderParsed($parsedTree, $this->options);
    }

    /**
     * Convert template string to tree based on indentation
     *
     * @param string $templateString
     * @param string $input_indentation
     * @return array
     */
    protected static function _indentedTextToArray($templateString, $input_indentation = '    ')
    {
        $structure_root = array();
        $structure = &$structure_root;
        $levels = array();
        // \R is a short-hand for \r|\n|\r\n
        $lines = preg_split("`\R`", $templateString);
        $previous_indentation_level = 0;
        $empty_count = 0;
        foreach ($lines as $line)
        {
            // Stack empty until we know what's the appropriate indentation
            if (empty($line)) {
                $empty_count++;
                continue;
            }
            preg_match('`^((?:'.preg_quote($input_indentation).')*)(.*)$`', $line, $m);
            if (empty($m[1])) {
                $m[1] = '';
                if (empty($m[2])) {
                    $m[2] = '';
                }
            }
            $text = $m[2];
            $indentation_level = substr_count($m[1], $input_indentation);

            if ($indentation_level == $previous_indentation_level)
            {
                while ($empty_count > 0) {
                    $structure[] = '';
                    $empty_count--;
                }

                // Same level => Just add the next tag to the list
                $structure[] = $text;
            }
            else if ($indentation_level > $previous_indentation_level)
            {
                // 1 more indentation : store the current structure reference
                $levels[$previous_indentation_level] = &$structure;
                // Create a new array for the value
                $children = array();
                while ($empty_count > 0) {
                    $children[] = '';
                    $empty_count--;
                }
                $structure[] =& $children;
                $structure =& $children;
                $structure[] = $text;
                unset($children);
            }
            else if ($indentation_level < $previous_indentation_level)
            {
                // 1 less indentation : retrieve the structure
                $structure =& $levels[$indentation_level];
                while ($empty_count > 0) {
                    $structure[] = '';
                    $empty_count--;
                }
                unset($levels[$indentation_level]);
                $structure[] = $text;
            }
            $previous_indentation_level = $indentation_level;
        }
        return $structure_root;
    }

    /**
     * Figure out which lines contains a tag or a helper (assume plain-text when unknown)
     *
     * @param array $tree
     * @return array
     */
    protected static function _parseTree($tree)
    {
        foreach ($tree as $index => $child)
        {
            if (is_array($child)) {
                $tree[$index] = static::_parseTree($child);
            } else if (!empty($child)) {
                // Trim spaces inside template tags & vars
                $child = preg_replace('`{{\s+(\S+?)\s+}}`', '{{$1}}', $child);
                if (isset($child[0]) && $child[0] == '%') {
                    preg_match('`^%([\w{}]+)`', $child, $m);
                    $tag = $m[1];
                    try {
                        $attrs = static::_parseAttrs(substr($child, strlen($tag) + 1));

                        $tree[$index] = array(
                            'type' => 'tag',
                            'tag' => $tag,
                            'attrs' => $attrs,
                        );
                    } catch (\Exception $e) {
                        $tree[$index] = array(
                            'type' => 'text',
                            'text' => $child,
                        );
                    }
                } else if (preg_match('`^{{\s*(#([\w-]+).+?)\s*}}$`', $child, $m)) {
                    $tree[$index] = array(
                        'type' => 'helper',
                        'name' => $m[2],
                        'helper' => '{{'.$m[1].'}}',
                    );
                } else if (preg_match('`^{{else}}$`', $child)) {
                    $tree[$index] = array(
                        'type' => 'else',
                    );
                } else {
                    $tree[$index] = array(
                        'type' => 'text',
                        'text' => $child,
                    );
                }
            } else {
                $tree[$index] = array(
                    'type' => 'empty',
                );
            }
        }
        return $tree;
    }

    /**
     *  Move children node appropriately
     *
     * @param array $tree
     * @return array
     */
    protected static function _parseChildren($tree)
    {
        $count = count($tree);
        for ($i = 0; $i < $count ; $i++) {
            if (isset($tree[$i + 1]) && isset($tree[$i + 1][0])) {
                $tree[$i]['children'] = static::_parseChildren($tree[$i + 1]);
                unset($tree[$i + 1]);
                $i++;
            }
        }
        return $tree;
    }

    public static function extractContextAndAttrs($string) {
        list($context, $attrs) = explode(' ', $string.' ', 2);
        $attrs = empty($attrs) ? array() : static::_parseAttrs($attrs);
        return array($context, $attrs);
    }

    protected static function _parseAttrs($string) {
        // Pre-parse ID using CSS/Emmet notation
        preg_match('`#([\w{}-]+)(?=\s|$)`', $string, $matches, PREG_OFFSET_CAPTURE);
        $id = null;
        if (!empty($matches[1])) {
            $id = $matches[1][0];
            $string = substr_replace($string, '', $matches[0][1], strlen($matches[0][0]));
        }
        // Pre-parse classes using CSS/Emmet notation
        preg_match_all('`\.([\w:{}-]+)(?=\s|$)`', $string, $matches, PREG_OFFSET_CAPTURE);
        $classes = null;
        if (!empty($matches[1])) {
            $offset = 0;
            $classes = array();
            foreach ($matches[0] as $mid => $match) {
                list($class, $pos) = $match;
                $classes[] = $matches[1][$mid][0];
                $length = strlen($class);
                $string = substr_replace($string, '', $pos + $offset, $length);
                $offset -= $length;
            }
        }

        // Allow un-quoted attributes
        $string = preg_replace('`(\s\w+)=([^"\s]+)`', '$1="$2"', "<div $string />");

        $a = new \SimpleXMLElement($string, LIBXML_NOERROR);
        $r = (array) $a->attributes();
        $attributes = !empty($r['@attributes']) ? $r['@attributes'] : array();

        if ($id !== null) {
            $attributes['id'] = $id;
        }
        if ($classes !== null) {
            if (empty($attributes['class'])) {
                $attributes['class'] = '';
            } else {
                $attributes['class'] .= ' ';
            }
            $attributes['class'] .= implode(' ', $classes);
        }
        return $attributes;
    }

    protected static function _renderParsed($tree, $options = array(), &$emptyStack = null)
    {
        $prepend = $options['prepend'];
        $rendered = array();
        if (!is_array($tree)) {
            return $tree;
        }
        // Re-index properly
        $tree = array_values($tree);
        $length = count($tree);
        for ($i = 0 ; $i < $length ; $i++) {
            $emptyStack = array();

            $node = $tree[$i];

            if (!empty($node['children'])) {
                $newOptions = $options;
                $newOptions['prepend'] .= $options['output_indentation'];
                $children = $node['children'];
                $lastChild = end($children);
                while (isset($lastChild['type']) && $lastChild['type'] == 'empty') {
                    $emptyStack[] = "\n";
                    array_pop($children);
                    $lastChild = end($children);
                }
                $emptyStack = implode($options['empty_line'], $emptyStack);
                $children = Zenml::_renderParsed($children, $newOptions);
            } else {
                $children = null;
                $emptyStack = '';
            }

            if ($node['type'] == 'empty') {
                $rendered[] = $options['empty_line'];
                $children && $rendered[] = $children.$emptyStack;
                continue;
            }

            if ($node['type'] == 'text')
            {
                $rendered[] = $prepend.$node['text'];
                $children && $rendered[] = $children.$emptyStack;
            }

            if ($node['type'] == 'tag')
            {
                $text = '';
                if (isset($node['attrs']['text'])) {
                    $text = $node['attrs']['text'];
                    unset($node['attrs']['text']);

                    $text = static::_renderParsed($text, $options);

                } else if ($children) {
                    $text = $children;
                }

                $tag_name = $node['tag'];
                $attrs = static::arrayToAttr($node['attrs']);

                if (in_array($tag_name, array('img', 'br', 'input', 'hr'))) {
                    $rendered[] = sprintf('%s%s<%s%s />',
                        $prepend,
                        empty($tag_name)? $tag_name : '',
                        !empty($tag_name) ? $tag_name : 'div',
                        !empty($attrs) ? ' '.$attrs : ''
                    ).$emptyStack;;
                } else {
                    $rendered[] = sprintf('%s%s<%s%s>%s%s%s%s</%s>',
                        $prepend,
                        empty($tag_name)? $tag_name : '',
                        !empty($tag_name) ? $tag_name : 'div',
                        !empty($attrs) ? ' '.$attrs : '',
                        ($children ? "\n" : ''),
                        $text,
                        ($children ? "\n" : ''),
                        ($children ? $prepend : ''),
                        !empty($tag_name) ? $tag_name : 'div'
                    ).$emptyStack;
                }
            }

            if ($node['type'] == 'helper')
            {
                $rendered[] = $prepend.$node['helper'];
                if ($children) {
                    $rendered[] = $children;

                    $elseEmptyStack = '';
                    $j = $i + 1;
                    while(isset($tree[$j]) && $tree[$j]['type'] == 'empty') {
                        $j++;
                    }

                    if (isset($tree[$j]) && $tree[$j]['type'] == 'else') {
                        for ($k = $i + 1 ; $k < $j ; $k++) {
                            $rendered[] .= $options['empty_line'];
                        }
                        $rendered[] = Zenml::_renderParsed(array($tree[$j]), $options, $elseEmptyStack).$emptyStack;
                        unset($tree[$j]);
                        $emptyStack = '';
                        $i = $j;
                    }
                    $rendered[] = $prepend.'{{/'.$node['name'].'}}'.$emptyStack.$elseEmptyStack;
                }
            }

            if ($node['type'] == 'else' && isset($node['children'])) {
                $rendered[] = $prepend.'{{else}}';
                $rendered[] = $children;
            }
        }
        return implode("\n", $rendered);
    }

    // Credits : FuelPHP framework MIT Licence
    public static function arrayToAttr($attr)
    {
        $attr_str = '';

        foreach ((array) $attr as $property => $value)
        {
            // Ignore null/false
            if ($value === null or $value === false)
            {
                continue;
            }

            // If the key is numeric then it must be something like selected="selected"
            if (is_numeric($property))
            {
                $property = $value;
            }

            $attr_str .= $property.'="'.$value.'" ';
        }

        // We strip off the last space for return
        return trim($attr_str);
    }
}

