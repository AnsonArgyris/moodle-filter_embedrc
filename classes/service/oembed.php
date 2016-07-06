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
 * Filter for component 'filter_embedrc'
 *
 * @package   filter_embedrc
 * @copyright Erich M. Wappis / Guy Thomas 2016
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * code based on the following filter
 * oEmbed filter ( Mike Churchward, James McQuillan, Vinayak (Vin) Bhalerao, Josh Gavant and Rob Dolin)
 */
namespace filter_embedrc\service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');

class oembed {

    /**
     * @var array
     */
    protected $warnings = [];

    /**
     * @var array|mixed
     */
    protected $providers = [];

    /**
     * @var array
     */
    protected $sites = [];

    /**
     * Constructor - protected singeton.
     */
    protected function __construct() {
        $this->set_providers();
        $this->sites = $this->get_sites();
    }

    /**
     * Singleton
     *
     * @return oembed
     */
    public static function get_instance() {
        /** @var $instance oembed */
        static $instance;
        if ($instance) {
            return $instance;
        } else {
            return new oembed();
        }
    }

    /**
     * Get cached providers
     *
     * @param bool $ignorelifespan
     * @return bool|mixed
     * @throws \Exception
     * @throws \dml_exception
     */
    protected function get_cached_providers($ignorelifespan = false) {
        $config = get_config('filter_embedrc');

        if (empty($config->cachelifespan )) {
            // When unset or set to not cache.
            $cachelifespan = 0;
        } else if ($config->cachelifespan == '1') {
            $cachelifespan = DAYSECS;
        } else if ($config->cachelifespan == '2') {
            $cachelifespan = WEEKSECS;
        } else {
            throw new \coding_exception('Unknown cachelifespan setting!', $config->cachelifespan);
        }

        // If config is present and cache fresh and available then use it
        if (!empty($config)) {
            if (!empty($config->providers_cachestamp) && !empty($config->providers_cached)) {
                $lastcached = intval($config->providers_cachestamp);
                if ($ignorelifespan || $lastcached > time() - $cachelifespan) {
                    // Use cached providers.
                    $providers = json_decode($config->providers_cached, true);
                    return $providers;
                }
            }
        }
        return false;
    }

    /**
     * Cache provider json string.
     *
     * @param string $json
     */
    protected function cache_provider_json($json) {
        set_config('providers_cached', $json, 'filter_embedrc');
        set_config('providers_cachestamp', time(), 'filter_embedrc');
    }

    /**
     * Set providers property, retrieve from cache if possible.
     *
     * @throws \Exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function set_providers() {
        $config = get_config('filter_embedrc');
        $providers = $this->get_cached_providers();
        if (empty($providers)) {
            $providers = $this->download_providers();
        }
        if (empty($providers)) {
            // OK - we couldn't retrieve the providers via curl, let's hope we have something cached that's usable.
            $providers = $this->get_cached_providers(true);
        }
        if (empty($providers)) {
            // Couldn't get anything via curl or from cache, use local static copy.
            $ret = file_get_contents(__DIR__.'/../../providers.json');
            $providers = json_decode($ret, true);
        }

        $this->providers = $providers;

        if (!empty($config->providers_restrict)) {
            if (!empty($config->providers_allowed)) {
                // We want to restrict the providers that are used
                $whitelist = explode(',', $config->providers_allowed);
                $wlist = array_filter($providers, function ($val) use ($whitelist) {
                    if (in_array($val['provider_name'], $whitelist)) {
                        return true;
                    }
                });
                set_config('providers_whitelisted', $wlist, 'filter_embedrc');
                $this->providers_whitelisted = $wlist;
            } else {
                $this->providers_whitelisted = [];
            }
        }
    }

    /**
     * Get the latest provider list from http://oembed.com/providers.json
     * If connection fails, take local list
     *
     * @return space array
     */
    protected function download_providers() {
        $www ='http://oembed.com/providers.json';

        $timeout = 15;   

        $ret = download_file_content($www, null, null, true, $timeout, 20, false, NULL, false);

        if ($ret->status == '200') {
            $ret = $ret->results;
        } else {
            $this->warnings[] = 'Failed to load providers from '.$www;
            return false;
        }
        
        $providers = json_decode($ret, true);

        if (!is_array($providers)) {
            $providers = false;
        }

        if (empty($providers)) {
            throw new \moodle_exception('error:noproviders', 'filter_embedrc', '');
        }

        // Cache provider json.
        $this->cache_provider_json($ret);
              
        return $providers;
    }
    
    /**
     * Check if the provided url matches any supported content providers
     *
     * @return array
     */
    protected function get_sites() {

        $sites = [];
        $config = get_config('filter_embedrc');

        if (!empty($config->providers_restrict)) {
            $provider_list = $this->providers_whitelisted;
        }
        else {
            $provider_list = $this->providers;
        }

        foreach ($provider_list as $provider) {
            $providerurl = $provider['provider_url'];
            $endpoints = $provider['endpoints'];
            $endpointsarr = $endpoints[0];
            $endpointurl = $endpointsarr['url'];
            $endpointurl = str_replace('{format}', 'json', $endpointurl);

            // Check if schemes are definded for this provider.
            // If not take the provider url for creating a regex.
            if (array_key_exists('schemes', $endpointsarr)){
                $regexschemes = $endpointsarr['schemes'];
            }
            else {
                $regexschemes = array($providerurl);
            }

            $sites[] = [
                'provider_name' => $provider['provider_name'],
                'regex'         => $this->create_regex_from_scheme($regexschemes),
                'endpoint'      => $endpointurl
            ];

        }
        return $sites;
    }

    /**
     * Create regular expressions from the providers list to check
     * for supported providers
     *
     * @param array $schemes
     */
    protected function create_regex_from_scheme(array $schemes){

        foreach ($schemes as $scheme) {

            $url1 = preg_split('/(https?:\/\/)/', $scheme);
            $url2 = preg_split('/\//', $url1[1]);
            unset($regex_array);
            foreach ($url2 as $url) {
                $find = ['.','*'];
                $replace =['\.','.*?'];
                $url = str_replace($find, $replace, $url);
                $regex_array[] = '('.$url.')';
            }

            $regex[] = '/(https?:\/\/)'.implode('\/', $regex_array).'/';
        }
        return $regex;
    }

    /**
     * Get the actual json from content provider
     *
     * @param string $www
     * @return array
     */
    protected function oembed_curlcall($www) {
        
        $ret = download_file_content($www, null, null, true, 300, 20, false, NULL, false);
        
        $this->providerurl = $www;
        $this->providerjson = $ret->results;
        $result = json_decode($ret->results, true);

        return $result;
    }

    /**
     * @return array
     */
    public function get_warnings() {
        return $this->warnings;
    }

    /**
     * Get oembed html.
     *
     * @param array $jsonarr
     * @param string $params
     * @return string
     * @throws \coding_exception
     */
    protected function oembed_gethtml($jsonarr, $params = '') {

        if ($jsonarr === null) {
            //return '<h3>'. get_string('connection_error', 'filter_embedrc') .'</h3>';
            $this->warnings[] = get_string('connection_error', 'filter_embedrc');
            return '';
        }

        $embed = $jsonarr['html'];

        if ($params != ''){
            $embed = str_replace('?feature=oembed', '?feature=oembed'.htmlspecialchars($params), $embed );
        }

        $embedcode = $embed;
        return $embedcode;
    }

    /**
     * Filter text - convert links into oembed code.
     *
     * @param string $text
     * @return string
     */
    public function html_output($text){
        $url2 = '&format=json';
        foreach ($this->sites as $site) {
            foreach ($site['regex'] as $regex) {
                if (preg_match($regex, $text)) {
                    $url = $site['endpoint'].'?url='.$text.$url2;
                    $jsonret = $this->oembed_curlcall($url);
                    if (!$jsonret) {
                        return '';
                    }
                    return $this->oembed_gethtml($jsonret);
                }
            }
        }
        return '';
    }

    public function __get($name) {
        $allowed = ['providers', 'warnings', 'sites'];
        if (in_array($name, $allowed)) {
            return $this->$name;
        } else {
            throw new \coding_exception($name.' is not a publicly accessible property of '.get_class($this));
        }
    }
}
