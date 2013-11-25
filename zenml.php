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
            if (!empty($node['ignore_when']) && empty($vars[$node['ignore_when']])) {
                continue;
            }
            $tag = static::_replaceVars($node['tag'], $vars);
            // Extract tag name / id / classes
            $tag_name = preg_match('`^([\w%]+)`', $tag, $m_tag) ? $m_tag[1] : '';
            $id = preg_match('`#([\w%-]+)`', $tag, $m_tag) ? $m_tag[1] : '';
            $classes = preg_match_all('`\.([\w%-]+)`', $tag, $m_tag) ? implode(' ', $m_tag[1]) : '';

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

            $line = array(
                sprintf('%s<%s%s%s>', $tabs, $tag_name, !empty($id) ? ' id="'.$id.'"' : '', !empty($classes) ? ' class="'.$classes.'"' : ''),
                $text,
                (!empty($node['children']) ? $tabs : '' )."</$tag_name>"
            );
            $rendered[] = implode(!empty($node['children']) ? "\n" : '', $line);

        }
        return implode("\n", $rendered);
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
            if (!preg_match('`^(\s*)(\+\+(\w+)\s|--)?(?:\!(\w+)\s)?([\w.%#]+)(?:\s(.+))?$`', $line, $m)) {
                continue;
            }
            list(, $indentation, $loop, $loop_name, $ignore_when, $tag) = $m;
            $text = empty($m[6]) ? '' : $m[6];
            $indentation_level = substr_count($indentation, '    ');

            $node = array(
                'tag' => $tag,
                'text' => $text,
                //'debug' => $m,
            );
            if (!empty($ignore_when)) {
                $node['ignore_when'] = $ignore_when;
            }
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
            $text = str_replace('%'.$name, $value, $text);
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
