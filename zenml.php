<?php


/**
 * Parser for Zenml syntax, an agnostic template language
 *
 * Class Zenml
 */
class Zenml
{
    protected $options = array();

    public function __construct($options = array())
    {
        $options = array_merge(array(
            'prepend' => '',
            'indentation' => "\t",
        ), $options);
        $this->setOptions($options);
    }

    public function setOptions(array $options = array())
    {
        $this->options = array_merge($this->options, $options);
    }

    public function render($templateString, $data = array())
    {
        // Convert template string to tree based on indentation
        $tree = static::_indentedTextToArray($templateString);
        // Figure out which lines contains a tag, a comment or a block
        $tree = static::_parseTree($tree);
        // Move children node appropriately and resolve block contexts
        $parsedTree = static::_parseChildren($tree);

        return $this->_renderParsed($parsedTree, $data, $this->options);
    }

    protected static function _indentedTextToArray($templateString)
    {
        $structure_root = array();
        $structure = &$structure_root;
        $levels = array();
        $lines = explode("\n", $templateString);
        $previous_indentation_level = 0;
        foreach ($lines as $line)
        {
            preg_match('`^(\s*)(.+)$`', $line, $m);
            list(, $indentation, $text) = $m;
            $indentation_level = substr_count($indentation, '    ');

            if ($indentation_level == $previous_indentation_level)
            {
                // Same level => Just add the next tag to the list
                $structure[] = $text;
            }
            else if ($indentation_level > $previous_indentation_level)
            {
                // 1 more indentation : store the current structure reference
                $levels[$previous_indentation_level] = &$structure;
                // Create a new array for the value
                $children = array();
                $structure[] =& $children;
                $structure =& $children;
                $structure[] = $text;
                unset($children);
            }
            else if ($indentation_level < $previous_indentation_level)
            {
                // 1 less indentation : retrieve the structure
                $structure =& $levels[$indentation_level];
                unset($levels[$indentation_level]);
                $structure[] = $text;
            }
            $previous_indentation_level = $indentation_level;
        }
        return $structure_root;
    }

    protected static function _parseTree($tree, &$context = null)
    {
        foreach ($tree as $index => $child)
        {
            if (is_array($child)) {
                $tree[$index] = static::_parseTree($child, $context);
            } else {
                if ($child[0] == '%') {
                    preg_match('`^%([\w{}]+)`', $child, $m);
                    $tag = $m[1];
                    $attrs = static::_parseAttrs(substr($child, strlen($tag) + 1));

                    $tree[$index] = array(
                        'type' => 'tag',
                        'tag' => array(
                            'name' => $tag,
                            'attrs' => $attrs,
                        ),
                    );
                } else if (preg_match('`^{{\s?#([\w-]+)\s?(?:(\w+)?\s?)(.*)}}$`', $child, $m)) {

                    if (!empty($m[2])) {
                        $context = $m[2];
                    }
                    $attrs = array();
                    if (!empty($m[3])) {
                        list(, $attrs) = static::_parseAttrs($m[3]);
                    }
                    $tree[$index] = array(
                        'type' => 'block',
                        'block' => array(
                            'name' => $m[1],
                            'context' => $context,
                            'attrs' => $attrs,
                        ),
                    );
                } else if (preg_match('`^{{\s?-- (.*)}}$`', $child, $m)) {
                    $tree[$index] = array(
                        'type' => 'comment',
                        'comment' => $m[1],
                    );
                } else {
                    $tree[$index] = array(
                        'type' => 'text',
                        'text' => $child,
                    );
                }
            }
        }
        return $tree;
    }

    protected static function _parseChildren($tree)
    {
        $count = count($tree);
        for ($i = 0; $i < $count ; $i++) {
            if (isset($tree[$i + 1]) && !isset($tree[$i + 1]['type'])) {
                $children = static::_parseChildren($tree[$i + 1]);
                if (isset($children[0]['type']) && $children[0]['type'] == 'text') {
                    if ($tree[$i]['type'] == 'tag') {
                        $tree[$i]['tag']['attrs']['text'] = $children[0]['text'];
                    } else {
                        $tree[$i]['text'] = $children[0]['text'];
                    }
                } else {
                    $tree[$i]['children'] = $children;
                }
                $i += 1;
                unset($tree[$i]);
            }
        }
        return $tree;
    }

    protected static function _renderParsed($structure, $data = array(), $options = array())
    {
        $prepend = $options['prepend'];
        $rendered = array();
        foreach ($structure as $node) {

            if ($node['type'] == 'comment')
            {
                //$rendered[] = $prepend.'<!-- '.$node['comment'].'-->';
            }

            if ($node['type'] == 'text')
            {
                $rendered[] = $prepend.$node['text'];
            }

            if ($node['type'] == 'tag')
            {
                $tag = $node['tag'];
                $text = '';
                $newOptions = $options;
                if (isset($tag['attrs']['text'])) {
                    $text = $tag['attrs']['text'];
                    unset($tag['attrs']['text']);

                    $text = static::_replaceVars($text, $data);

                } else if (!empty($node['children'])) {
                    $newOptions['prepend'] .= $options['indentation'];
                    $text = static::_renderParsed($node['children'], $data, $newOptions);
                }

                $tag_name = static::_replaceVars($tag['name'], $data, '');
                $attrs = static::_replaceVars(static::_arrayToAttr($tag['attrs']), $data, '');

                $rendered[] = sprintf('%s%s<%s%s>%s%s%s%s</%s>',
                    $prepend,
                    empty($tag_name) ? static::_replaceVars($tag['name'], array(), '<!-- {{$1}} missing, fallback to "div" -->') : '',
                    !empty($tag_name) ? $tag_name : 'div',
                    !empty($attrs) ? ' '.$attrs : '',
                    (!empty($node['children']) ? "\n" : ''),
                    $text,
                    (!empty($node['children']) ? "\n" : ''),
                    (!empty($node['children']) ? $prepend : ''),
                    !empty($tag_name) ? $tag_name : 'div'
                );
            }

            if ($node['type'] == 'block')
            {
                $block = $node['block'];
                $context = $block['context'];
                if ($block['name'] == 'if') {
                    if (!empty($data[$context])) {
                        $rendered[] = static::_renderParsed($node['children'], $data, $options);
                    }
                }
                if ($block['name'] == 'each') {
                    if (!empty($data[$context]) && is_array($data[$context])) {
                        foreach ($data[$context] as $newData) {
                            $rendered[] = static::_renderParsed($node['children'], $newData, $options);
                        }
                    }
                }
                if ($block['name'] == 'empty') {
                    if (empty($data[$context])) {
                        $rendered[] = static::_renderParsed($node['children'], $data, $newOptions);
                    }
                }
            }
        }
        return implode("\n", $rendered);
    }


    protected static function _replaceVars($text, $data, $replaceEmpty = null)
    {
        if ($replaceEmpty === null) {
            $replaceEmpty = '<!-- {{$1}} -->';
        }
        foreach ($data as $name => $value) {
            if (is_array($value)) {
                continue;
            }
            $text = preg_replace('`{{\s*(?:this\.)?'.preg_quote($name).'\s*}}`', $value, $text);
        }
        $text = preg_replace('`{{\s*([\w-]+)\s*}}`', $replaceEmpty, $text);
        return $text;
    }

    // Credits : FuelPHP framework MIT Licence
    protected static function _arrayToAttr($attr)
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

    protected static function _parseAttrs($string) {
        preg_match('`#([\w{}-]+)(?=\s|$)`', $string, $matches, PREG_OFFSET_CAPTURE);
        $id = null;
        if (!empty($matches[1])) {
            $id = $matches[1][0];
            $string = substr_replace($string, '', $matches[0][1], strlen($matches[0][0]));
        }
        preg_match_all('`\s\.([\w:{}-]+)(?=\s|$)`', $string, $matches, PREG_OFFSET_CAPTURE);
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
        $a = new SimpleXMLElement($string);
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
}

