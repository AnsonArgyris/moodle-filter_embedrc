<?php
// This file is part of Moodle-oembed-Filter
//
// Moodle-oembed-Filter is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle-oembed-Filter is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle-oembed-Filter.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Filter for component 'filter_embedrc'
 *
 * @package   filter_embedrc
 * @copyright 2012 Matthew Cannings; modified 2015 by Microsoft, Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * code based on the following filters...
 * Screencast (Mark Schall)
 * Soundcloud (Troy Williams)
 */

defined('MOODLE_INTERNAL') || die();

use filter_embedrc\service\oembed;

require_once($CFG->libdir.'/filelib.php');
/**
 * Filter for processing HTML content containing links to media from services that support the OEmbed protocol.
 * The filter replaces the links with the embeddable content returned from the service via the Oembed protocol.
 *
 * @package    filter_embedrc
 */
class filter_embedrc extends moodle_text_filter {

    /**
     * Filters the given HTML text, looking for links pointing to media from services that support the Oembed
     * protocol and replacing them with the embeddable content returned from the protocol.
     *
     * @param $text HTML to be processed.
     * @param $options
     * @return string String containing processed HTML.
     */
    public function filter($text, array $options = array()) {

        if (!is_string($text) || empty($text)) {
            // Non string data can not be filtered anyway.
            return $text;
        }

        $targettag = get_config('filter_embedrc', 'targettag');

        if ($targettag == 'atag' && stripos($text, '</a>') === false) {
            // Performance shortcut - all regexes below end with the </a> tag.
            // If not present nothing can match.
            return $text;
        }

        $newtext = $text; // We need to return the original value if regex fails!
        if (get_config('filter_embedrc', 'targettag') == 'divtag') {
            $search = '/(?<=(<div class="oembed">))(.*)(?=<\/div>)/';
            $newtext = preg_replace_callback($search, function ($match) {
                $instance = oembed::get_instance();
                return $instance->html_output($match[0]);
            }, $newtext);
        }

        if (get_config('filter_embedrc', 'targettag') == 'atag') {
            $search = '/<a\s[^>]*href="(.*)"(.*?)>(.*?)<\/a>/';
            $newtext = preg_replace_callback($search, function ($match) {
                $instance = oembed::get_instance();
                return $instance->html_output($match[1]);

            }, $newtext);
        }

        if (empty($newtext) || $newtext === $text) {
            // Error or not filtered.
            unset($newtext);
            return $text;
        }

        return $newtext;
    }
}