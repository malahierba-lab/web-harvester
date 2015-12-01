<?php
namespace Malahierba\WebHarvester;

use Config;
use Exception;

class WebHarvester {
    
    protected $requested_url = [
        'full'      => null,
        'scheme'    => null,
        'host'      => null,
        'port'      => null,
        'path'      => null,
        'query'     => null,
        'fragment'  => null,
    ];

    protected $real_url = [
        'full'      => null,
        'scheme'    => null,
        'host'      => null,
        'port'      => null,
        'path'      => null,
        'query'     => null,
        'fragment'  => null,
    ];

    protected $content;

    protected $domdocument = false;

    protected $links  = array();

    // Default options
    protected $user_agent               = 'Malahierba WebHarvester';
    protected $resource_timeout         = 3000;
    protected $wait_after_load          = 7000;
    protected $ignore_ssl_errors        = true;

    /**
     * Load an URL and put the content and info at instance vars
     *
     * @param   string  $url
     * @param   array   $options
     * @return  string
     */
    public function load($url, $options = array())
    {
        $default_options = [
            'max_execution_time'    => 120,
        ];

        $options = array_merge($default_options, $options);

        if ($options['max_execution_time'])
            set_time_limit($options['max_execution_time']);

        $this->reset();

        if (empty($url))
            return false;

        $command = $this->getLoadCommand($url);

        exec($command, $output, $status);

        if ($status !== 0)
            return false;
    
        $this->extractData($output);

        //try to load content as domdocument
        $domdocument = new \DomDocument;

        libxml_use_internal_errors(TRUE);
        if ($domdocument->loadHTML('<?xml encoding="utf-8" ?>' . $this->content())) {
            $this->domdocument = $domdocument;

            //get the links from document
            $link_candidates = $domdocument->getElementsByTagName('a');

            foreach ($link_candidates as $link) {
                $url = $this->getAbsoluteUrl($link->getAttribute('href'));

                if ($url && (! in_array($url, $this->links)))
                    $this->links[] = $url;
            }
        }
        libxml_use_internal_errors(FALSE);

        return true;
    }

    /**
     * Take a Screenshot for an URL and put the content and info at instance vars
     *
     * @param   string  $url
     * @param   array   $options
     * @return  string
     */
    public function takeScreenshot($url, $options = array())
    {
        $default_options = [
        ];
        
        $this->reset();

        if (empty($url))
            return false;

        $command = $this->getScreenshotCommand($url);

        exec($command, $output, $status);

        if ($status !== 0)
            return false;
    
        $this->extractData($output);

        return true;
    }

    /**
     * Return the content. Previusly must load an URL (with load method)
     *
     * @return  string
     */
    public function content()
    {
        return $this->content;
    }

    /**
     * Return the data for URL requested.
     *
     * @return  object
     */
    protected function requestedURL()
    {
        return (object) $this->requested_url;
    }

    /**
     * Return the data for URL that finally response to request. If
     * requested an URL and response with redirection, then
     * this return info for that URL.
     *
     * @return  object
     */
    protected function realURL()
    {
        return (object) $this->real_url;
    }

    /**
     * Return the URL requested.
     *
     * @return string
     */
    public function getRequestedURL()
    {
        return $this->requestedURL()->full;
    }

    /**
     * Return the real URL (last url in response)
     *
     * @return string
     */
    public function getRealURL()
    {
        return $this->realURL()->full;
    }

    /**
     * Get the path for HttpClient
     *
     * @return  string
     */
    protected function getHttpClientPath()
    {

        $environment = Config::get('webharvester.environment');

        if (empty($environment))
            throw new Exception("[WebHarvester] Error on Environment Setup: you must define 'environment' var in webharvester config file.", 1);

        $httpclient = __DIR__ . '/bin/phantom/1.9.7-' . $environment . '/phantomjs';
        
        if (! file_exists($httpclient))
            throw new Exception("[WebHarvester] Error: file '" . $httpclient . "' not found. Suggestion: Check the 'environment' var in webharvester config file", 1);

        return $httpclient;
    }

    /**
     * Get the command string to execute a load of url
     *
     * @return  string
     */
    protected function getLoadCommand($url)
    {

        $httpclient_path    = $this->getHttpClientPath();
        $script_path        = __DIR__ . '/scripts/load.js';

        $command     = $httpclient_path;
        $command    .= ' --ssl-protocol=any';
        $command    .= $this->ignore_ssl_errors ? ' --ignore-ssl-errors=true' : ' --ignore-ssl-errors=false';
        $command    .= ' ' . $script_path;
        $command    .= ' url=' . $url;
        $command    .= ' wait-after-load=7000';
        $command    .= ' resource-timeout=' . $this->resource_timeout;
        $command    .= ' web-security=false';
        $command    .= ' user-agent="' . $this->user_agent . '"';

        return $command;
    }

    /**
     * Get the command string to execute a screenshot of url
     *
     * @return  string
     */
    protected function getScreenshotCommand($url)
    {

        $httpclient_path    = $this->getHttpClientPath();
        $script_path        = __DIR__ . '/scripts/screenshot.js';

        $command     = $httpclient_path;
        $command    .= ' --ssl-protocol=any';
        $command    .= $this->ignore_ssl_errors ? ' --ignore-ssl-errors=true' : ' --ignore-ssl-errors=false';
        $command    .= ' ' . $script_path;
        $command    .= ' url=' . $url;
        $command    .= ' wait-after-load=5000';
        $command    .= ' resource-timeout=3000' . $this->resource_timeout;
        $command    .= ' web-security=false';
        $command    .= ' load-images=true';
        $command    .= ' user-agent="' . $this->user_agent . '"';

        return $command;
    }

    /**
     * Reset the instance vars
     */
    protected function reset()
    {
        foreach ($this->requested_url as $key => $value) {
            $this->requested_url[$key] = null;
        }

        foreach ($this->real_url as $key => $value) {
            $this->requested_url[$key] = null;
        }

        $this->content          = null;
        $this->domdocument      = false;
        $this->links            = array();
    }

    /**
     * Extract Data from PhantomJS output an save the components into the instance vars
     *
     * @param   array   $output
     * @return  void
     */
    protected function extractData($output)
    {
        foreach($output as $key => $line) {

            if ($key == 0) {
                $this->requested_url = $this->getInfoFromURL($line);
            } elseif ($key == 1) {
                $this->real_url = $this->getInfoFromURL($line);
            } else {
                $this->content .= $this->stringToUTF8($line);
            }
        }
    }

    /**
     * Get info from an URL and return its components
     *
     * @param   string   $url
     * @return  array
     */
    protected function getInfoFromURL($url)
    {
        $url_components = parse_url($url);

        $info = [
            'full'      => $url,
            'scheme'    => isset($url_components['scheme']) ? $url_components['scheme'] : null,
            'host'      => isset($url_components['host']) ? $url_components['host'] : null,
            'port'      => isset($url_components['port']) ? $url_components['port'] : null,
            'path'      => isset($url_components['path']) ? $url_components['path'] : null,
            'query'     => isset($url_components['query']) ? $url_components['query'] : null,
            'fragment'  => isset($url_components['fragment']) ? $url_components['fragment'] : null,
        ];

        return $info;
    }

    /**
     * Convert a string into UTF8
     *
     * @param   string  $string
     * @return  string
     */
    protected function stringToUTF8($string)
    {
        $codifications =    array(
                                'ASCII',
                                'ISO-8859-1',
                                'UTF-16',
                                'UTF-16BE',
                                'UTF-16LE',
                                'CP1251',
                                'CP1252',
                                'BASE64',
                                'ISO-8859-2',
                                'ISO-8859-3',
                                'ISO-8859-4',
                                'ISO-8859-5',
                                'ISO-8859-6',
                                'ISO-8859-7',
                                'ISO-8859-8',
                                'ISO-8859-9',
                                'ISO-8859-10',
                                'ISO-8859-13',
                                'ISO-8859-14',
                                'ISO-8859-15',
                                'UTF-8',
                            );
        
        $original_charset = mb_detect_encoding($string, mb_detect_order(), true);

        if (! $original_charset)
            return false;

        if ($original_charset != 'UTF-8') {
            $string = mb_convert_encoding($string, 'UTF-8', $original_charset);
        }
        
        //$string = mb_convert_encoding($string, 'UTF-8', 'HTML-ENTITIES');

        return $string;
    }

    /**
     * Get the URL for Featured Image
     *
     * @param   string          url (default) | base64
     * @return  string|false
     */
    public function getFeaturedImage($return_as = 'url')
    {
        if (! $this->domdocument)
            return false;

        $metas      = $this->domdocument->getElementsByTagName('meta');

        $image_url  = '';
            
        foreach($metas as $meta) {
        
            //Opengraph Test
            if ($meta->getAttribute('property') == 'og:image') {

                $image_url =  trim($meta->getAttribute('content'));

                if (! empty($image_url))
                    break;
            }

            //Twitter Tags Test
            if ($meta->getAttribute('name') == 'twitter:image') {
                $image_url =  trim($meta->getAttribute('content'));

                if (! empty($image_url))
                    break;
            }
        }

        if (empty($image_url))
            return false;

        $image_absolute_url = $this->getAbsoluteUrl($image_url);

        if (! $image_absolute_url)
            return false;

        //Return Featured Image
        if ($return_as == 'url')

            return $image_absolute_url;

        elseif ($return_as == 'base64')

            return $this->getImageAsBase64($image_absolute_url);

        //If using invalid parameter $return_as
        throw new Exception("[WebHarvester] Error on getFeaturedImage: value '" . $return_as . "' not supported.", 1);
    }

    /**
     * Get image and convert to base64
     *
     * @param   string
     * @return  string|false
     */
    protected function getImageAsBase64($url)
    {
        // setup http client
        $http_client = curl_init();
        curl_setopt($http_client, CURLOPT_URL,              $url);
        curl_setopt($http_client, CURLOPT_FOLLOWLOCATION,   false);
        curl_setopt($http_client, CURLOPT_RETURNTRANSFER,   true);
        curl_setopt($http_client, CURLOPT_CONNECTTIMEOUT,   $this->resource_timeout);
        curl_setopt($http_client, CURLOPT_MAXREDIRS,        0);
        curl_setopt($http_client, CURLOPT_TIMEOUT,          $this->resource_timeout);
        curl_setopt($http_client, CURLOPT_USERAGENT,        $this->user_agent);

        // try to get image
        $response = curl_exec($http_client);

        //check http code 200
        if (curl_getinfo($http_client, CURLINFO_HTTP_CODE) != 200)
            return false;

        $mime = curl_getinfo($http_client, CURLINFO_CONTENT_TYPE);

        //check if content is a supported image
        if (! in_array($mime, [
            'image/jpeg',
            'image/png',
        ]))
            return false;

        return 'data:' . $mime . ';base64,' . base64_encode($response);
    }

    /**
     * Get the Title
     *
     * @param   void
     * @return  string|false
     */
    public function getTitle()
    {
        if (! $this->domdocument)
            return false;

        $metas      = $this->domdocument->getElementsByTagName('meta');

        $title  = [];
            
        foreach($metas as $meta) {
        
            //Opengraph Test
            if ($meta->getAttribute('property') == 'og:title')

                $title['opengraph']     = trim($meta->getAttribute('content'));

            //Twitter Tags Test
            elseif ($meta->getAttribute('name') == 'twitter:title')

                $title['twittercard']   = trim($meta->getAttribute('content'));
        }

        //Return Title
        if (! empty($title['opengraph']))
            return $title['opengraph'];

        if (! empty($title['twittercard']))
            return $title['twittercard'];

        $title_tag = trim($this->domdocument->getElementsByTagName('title'));

        return empty($title_tag) ? false : $title_tag;
    }

    /**
     * Get the Description
     *
     * @param   void
     * @return  string|false
     */
    public function getDescription()
    {
        if (! $this->domdocument)
            return false;

        $metas      = $this->domdocument->getElementsByTagName('meta');

        $description  = [];
            
        foreach($metas as $meta) {
        
            //Opengraph Test
            if ($meta->getAttribute('property') == 'og:description')

                $description['opengraph']       = trim($meta->getAttribute('content'));

            //Twitter Tags Test
            elseif ($meta->getAttribute('name') == 'twitter:description')

                $description['twittercard']     =  trim($meta->getAttribute('content'));

            //Description Meta Test
            elseif ($meta->getAttribute('name') == 'description')

                $description['meta']            =  trim($meta->getAttribute('content'));
        }

        //Return Description

        if (! empty($description['opengraph']))
            return $description['opengraph'];

        if (! empty($description['twittercard']))
            return $description['twittercard'];

        if (! empty($description['meta']))
            return $description['meta'];

        return false;
    }

    /**
     * Get the Site Name
     *
     * @param   void
     * @return  string|false
     */
    public function getSiteName()
    {
        if (! $this->domdocument)
            return false;

        $metas      = $this->domdocument->getElementsByTagName('meta');

        $sitename  = [];
            
        foreach($metas as $meta) {
        
            //Opengraph Test
            if ($meta->getAttribute('property') == 'og:site_name')

                $sitename['opengraph'] = trim($meta->getAttribute('content'));

        }

        //Return Site Name
        if (! empty($sitename['opengraph']))
            return $sitename['opengraph'];

        return false;
    }

    /**
     * Get the Base Path for current document. Work only if current document is a HTML document.
     *
     * @param   void
     * @return  string|false
     */
    public function getBasePath()
    {
        if (! $this->domdocument)
            return false;

        $base_tag       = $this->domdocument->getElementsByTagName('base');

        if ($base_tag->length == 0)
            return $this->realURL()->scheme . '://' . $this->realURL()->host . '/';

        $base_path      = $base_tag->item(0)->getAttribute('href');

        if (! (empty($base_path))) {

            $base_parse = parse_url($base_path);

            //URL Builder
            if ($base_parse) {

                //Scheme
                if (isset($base_parse['scheme'])) {
                    $base_path_temp = $base_parse['scheme'] . '://';
                } elseif (! empty($this->realURL()->scheme)) {
                    $base_path_temp = $this->realURL()->scheme . '://';
                } else {
                    $base_path_temp = 'http://';
                }

                //Host
                if (isset($base_parse['host'])) {
                    $base_path_temp .= $base_parse['host'];
                } elseif (! empty($this->realURL()->host)) {
                    $base_path_temp .= $this->realURL()->host;
                } else {
                    return false; // --> without a host we can't continue
                }

                //Path
                if (isset($base_parse['path'])) {
                    $base_path_temp .= $base_parse['path'];
                } elseif (! empty($this->realURL()->path)) {
                    $base_path_temp .= $this->realURL()->path;
                } else {
                    $base_path_temp .= '/';
                }

                $base_path = $base_path_temp;
            }
        }

        return ! empty($base_path) ? $base_path : $this->realURL()->scheme . '://' . $this->realURL()->host . '/';
    }

    /**
     * Try to get the absolute url for given url. If fail return false.
     *
     * @param   string      url (absolute or relative)
     * @return  string|false
     */
    protected function getAbsoluteUrl($url)
    {
        if (empty($url))
            return false;

        //Test for full or relative URL
        $test_url = parse_url($url);

        if (! $test_url)
            return false;

        if (! isset($test_url['host'])) {

            if ($url[0] == '/')
                $url = substr($url, 1);

            $url = $this->getBasePath() . $url;

        } else {

            $temp_url = $url;

            $url = empty($test_url['scheme']) ? 'http' : $test_url['scheme'] ;
            $url .= '://';
            $url .= $test_url['host'];

            if (! empty($test_url['path']))
                $url .= $test_url['path'];

            if (! empty($test_url['query']))
                $url .= '?' . $test_url['query'];

        }

        return $url;
    }

    /**
     * Return the links presents in document (only work after load method)
     *
     * @param   void
     * @return  array
     */
    public function getLinks($options = [])
    {
        $links = $this->links;

        foreach ($links as $key => $link) {

            //remove link with javascript script for safe
            if (strpos($link, 'javascript:')) {
                unset($links[$key]);
                continue;
            }

            //remove some component(s) from url based on option 'remove'
            if (isset($options['remove']) && is_array($options['remove'])) {

                //remove query component from links
                if (in_array('query', $options['remove'])) {

                        $components = parse_url($link);
                        $links[$key] = $components['scheme'] . '://' . $components['host'];

                        if (isset($components['path']))
                            $links[$key] .= $components['path'];

                }

            }

        }

        return array_values($links);
    }

    /**
     * Configure a Custom User Agent
     *
     * @param   string
     * @return  void
     */
    public function setUserAgent($user_agent)
    {
        $this->user_agent = $user_agent;
    }

    /**
     * Configure the number of milliseconds to wait after load a web Page
     *
     * A Page with async content maybe empty when is loaded,
     * this option allows you to wait for more content.
     *
     * @param   integer
     * @return  void
     */
    public function setWaitAfterLoad($milliseconds)
    {
        $this->wait_after_load = $milliseconds;
    }

    /**
     * Configure the number of milliseconds to wait a resource
     *
     * @param   integer
     * @return  void
     */
    public function setResourceTimeout($milliseconds)
    {
        $this->resource_timeout = $milliseconds;
    }

    /**
     * Configure whether the process should ignore ssl errors
     *
     * @param   bool
     * @return  void
     */
    public function setIgnoreSSLErrors($bool)
    {
        $this->ignore_ssl_errors = $bool;
    }
}