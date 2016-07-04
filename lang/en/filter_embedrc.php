<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for component 'filter_oembed'
 *
 * @package   filter_oembed
 * @copyright 2012 Matthew Cannings; modified 2015 by Microsoft Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * code based on the following filters...
 * Screencast (Mark Schall)
 * Soundcloud (Troy Williams)
 */

$string['filtername'] = 'Embed Remote Content Filter';
$string['cachelifespan_disabled'] = 'Cache lifespan disabled';
$string['cachelifespan'] = 'Cache lifespan';
$string['cachelifespan_desc'] = 'The duration of time before the providers list should be refreshed.';
$string['cachelifespan_daily'] = '1 day';
$string['cachelifespan_weekly'] = '1 week';
$string['atag'] = 'Filter on &lt; a &gt; tags';
$string['divtag'] = 'Filter on &lt; div &gt; tags';
$string['targettag'] = 'Target tag';
$string['targettag_desc'] = 'What tag type should be filtered - anchors or divs with the oembed class.';
$string['providers_restrict'] = 'Restrict providers';
$string['providers_restrict_desc'] = 'Restrict providers to a list of allowed providers';
$string['providers_allowed'] = 'Providers allowed.';
$string['providers_allowed_desc'] = 'Providers whitelisted to be used with this plugin';
$string['error:notloggedin'] = 'You must be logged in to use the oembed filter';