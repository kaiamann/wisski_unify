<?php

namespace Drupal\wisski_unify\Render;

class MarkupGenerator {
    /**
     * 
     * Generates HTML for a given normalized WissKi entity
     * and its matching/conflicting data
     *
     * @param array $normalizedEntity
     *  The entity data to be converted
     *
     * @param array $matchingData
     *  The matching/conflicting metadata.
     *  Has to have the same structure as $normalizedEntity,
     *  just with the values replaced by numbers in [0, 1, 2].
     *  0 === match => green   
     *  1 === conflict => red
     *  2 === neutral => normal
     *
     * @param number $level
     *  The starting level for headers <h{$level}>
     *
     * @return string
     *  The html string
     */
    static function generateHTML(array $normalizedEntity, array $matchingData, $level = 5) {
        $classes = ['match', 'conflict', 'neutral'];
        $html = "";
        foreach ($normalizedEntity as $key => $value) {
            // label
            if (!is_numeric($key))
                $html .= "<h{$level}>{$key}</h{$level}>";
            // value list
            if (is_array($value)) {
                $html .= self::generateHTML($value, $matchingData[$key], $level + 1);
            } else {
                $class = $classes[$matchingData[$key]];
                $html .= "<div class=\"{$class}\">{$value}</div>";
            }
        }
        return $html;
    }
}
