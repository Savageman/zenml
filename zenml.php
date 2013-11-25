<?php



/**
 * Parser for Zenml syntax, an agnostic template language
 *
 * Class Zenml
 */
class Zenml
{
    public static function render($structure, $vars = array(), $tabs = "")
    {
        if (is_string($structure)) {
            $structure = static::parse($structure);
        }
        $rendered = array();
        foreach ($structure as $node) {

            $tag = $node['tag'];
            $offset = 0;
            $attrs = array();
            $tag_name = '';
            while (preg_match('`(?:([\w:(){}]+))|(?:#([\w:(){}-]+))|(?:\.([\w:(){}-]+))|(?:\[([\w:(){}-]+)=([\w:(){}.-]+)\])`', $tag, $m, null, $offset)) {
                if (!empty($m[1])) {
                    $tag_name = $m[1];
                }
                if (!empty($m[2])) {
                    $attrs['id'] = $m[2];
                }
                if (!empty($m[3])) {
                    $attrs['class'][] = $m[3];
                }
                if (!empty($m[4])) {
                    $attrs[$m[4]] = $m[5];
                }
                if (strlen($m[0]) == 0) {
                    break;
                }
                $offset += strlen($m[0]);
            }
            if (!empty($attrs['class'])) {
                $attrs['class'] = implode(' ', $attrs['class']);
            }
            if (empty($tag_name)) {
                $tag_name = 'div';
            }

            debug(array(
                $tag_name => $attrs,
            ));
            $tag_name = static::_replaceVars($tag_name, $vars);
            foreach($attrs as $name => &$value)
            {
                $value = static::_replaceVars($value, $vars);
            }
            unset($value);
            debug(array(
                $tag_name => $attrs,
            ));

            if (!empty($node['children'])) {
                $text = static::render($node['children'], $vars, $tabs."\t");
            } else {
                $text = static::_replaceVars($node['text'], $vars);
            }

            if (!empty($node['loop_name'])) {
                $loop_name = $node['loop_name'];
                if (!empty($vars[$loop_name])) {
                    unset($node['loop_name']);
                    $loop = array();
                    foreach ($vars[$loop_name] as $loop_vars) {
                        //$loop_vars = array(array_map(function($v) { return '%'.$v; }, array_keys($loop)), array_values($loop));
                        $loop[] = static::render($node['children'], $loop_vars, $tabs);
                    }
                    $text = $tabs.implode("\n$tabs", $loop);
                } else if (!empty($node['loop_empty'])) {
                    $rendered[] = static::render($node['loop_empty'], $vars, $tabs);
                    continue;
                }
            }

            $attrs = static::array_to_attr($attrs);

            $line = array(
                sprintf('%s<%s%s>', $tabs, $tag_name, !empty($attrs) ? ' '.$attrs : ''),
                $text,
                (!empty($node['children']) ? $tabs : '' )."</$tag_name>"
            );
            $rendered[] = implode(!empty($node['children']) ? "\n" : '', $line);

        }
        return implode("\n", $rendered);
    }

    // Credits : FuelPHP framework MIT Licence
    protected static function array_to_attr($attr)
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

    /**
     * @param $template
     * @return static
     */
    public static function parse($template)
    {
        $structure_root = array();
        $structure = &$structure_root;
        $levels = array();
        $loops = array();
        $lines = explode("\n", $template);
        $previous_indentation_level = 0;
        $previous_text = null;
        foreach ($lines as $line)
        {
            // Ignore lines we don't understand
            if (!preg_match('`^(\s*)(\+\+(\w+)\s|--)?([\w.%#\[\]=():{}]+)(?:\s(.+))?$`', $line, $m)) {
                debug(strtoupper($line));
                continue;
            }
            list(, $indentation, $loop, $loop_name, $tag) = $m;
            $text = empty($m[5]) ? '' : $m[5];
            $indentation_level = substr_count($indentation, '    ');

            $node = array(
                'tag' => $tag,
                'text' => $text,
                //'debug' => $m,
            );
            if (!empty($loop) && $loop != '--') {
                $node['loop_name'] = $loop_name;
                $loops[$indentation_level] = &$node;
            }

            if ($loop == '--') {
                $loops[$indentation_level]['loop_empty'][] = &$node;
                unset($previous_node);
                $previous_node = &$node;
                $structure = &$node;
            }
            else if ($indentation_level == $previous_indentation_level)
            {
                // Same level => Just add the next tag to the list
                unset($previous_node);
                $previous_node = &$node;
                $structure[] = &$previous_node;
            }
            else if ($indentation_level > $previous_indentation_level)
            {
                // 1 more indentation : store the current structure reference
                $levels[$previous_indentation_level] = &$structure;
                $previous_node['children'] = array(
                    &$node,
                );
                $structure = &$previous_node['children'];
                unset($previous_node);
                $previous_node = &$node;
            }
            else if ($indentation_level < $previous_indentation_level)
            {
                // 1 less indentation : retrieve the structure
                $structure =& $levels[$indentation_level];
                unset($levels[$indentation_level]);
                unset($previous_node);
                $previous_node = &$node;
                $structure[] = &$node;
            }
            unset($node);
            $previous_indentation_level = $indentation_level;
        }

        return $structure_root;
    }

    protected static function _replaceVars($text, array $vars)
    {
        foreach ($vars as $name => $value) {
            if (is_array($value)) {
                continue;
            }
            $text = str_replace('(:'.$name.')', $value, $text);
            $text = str_replace('{'.$name.'}', $value, $text);
        }
        return $text;
    }
}


/**
 * Old parser for simple syntax and simple structure
 *
 * Class ZenmlSimple
 */
class ZenmlSimple
{
    public static function parse($template)
    {
        $structure_root = array();
        $structure = &$structure_root;
        $levels = array();
        $lines = explode("\n", $template);
        $previous_indentation_level = 0;
        $previous_text = null;
        foreach ($lines as $line)
        {
            preg_match('`^(\s*)(.+)$`', $line, $m);
            $indentation_level = substr_count($m[1], '    ');
            $line = explode(' ', $m[2], 2);
            list($tag) = $line;
            $text = empty($line[1]) ? '' : $line[1];
            if ($indentation_level == $previous_indentation_level)
            {
                // Same level => Just add the next tag to the list
                unset($previous_text);
                $previous_text = &$text;
                $structure[$tag] = &$previous_text;
                unset($text);
            }
            else if ($indentation_level > $previous_indentation_level)
            {
                // 1 more indentation : store the current structure reference
                $levels[$previous_indentation_level] = &$structure;
                // Create a new array for the value
                $previous_text = array($tag => $text);
                $structure = &$previous_text;
                unset($previous_text);
            }
            else if ($indentation_level < $previous_indentation_level)
            {
                // 1 less indentation : retrieve the structure
                $structure =& $levels[$indentation_level];
                unset($levels[$indentation_level]);
                $structure[$tag] = $text;
                unset($text);
            }
            $previous_indentation_level = $indentation_level;
        }
        return $structure_root;
    }

    public static function render($structure, $vars, $tabs = "")
    {
        if (is_string($structure)) {
            $structure = static::parse($structure);
        }
        $rendered = array();
        foreach ($structure as $tag => $text) {
            $tag = static::_replaceVars($tag, $vars);
            // Extract tag name / id / classes
            $tag_name = preg_match('`^([\w%]+)`', $tag, $m_tag) ? $m_tag[1] : '';
            $id = preg_match('`#([\w%]+)`', $tag, $m_tag) ? $m_tag[1] : '';
            $classes = preg_match_all('`\.([\w%]+)`', $tag, $m_tag) ? implode(' ', $m_tag[1]) : '';

            $text = static::_replaceVars($text, $vars);

            $line = array(
                sprintf('%s<%s%s%s>', $tabs, $tag_name, !empty($id) ? ' id="'.$id.'"' : '', !empty($classes) ? ' class="'.$classes.'"' : ''),
                is_array($text) ? static::render($text, $vars, $tabs."\t") : $text,
                (is_array($text) ? $tabs : '' )."</$tag_name>"
            );
            $rendered[] = implode(is_array($text) ? "\n" : '', $line);
        }
        return implode("\n", $rendered);
    }

    protected static function _replaceVars($text, $vars)
    {
        foreach ($vars as $name => $value) {
            if (is_array($value)) {
                continue;
            }
            $text = str_replace('%'.$name, $value, $text);
        }
        return $text;
    }
}
