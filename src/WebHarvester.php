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

    protected $status_code;

    protected $content;

    protected $domdocument = false;

    protected $links  = array();

    // Default options
    protected $user_agent               = 'Malahierba WebHarvester';
    protected $resource_timeout         = 3000;
    protected $wait_after_load          = 3000;
    protected $ignore_ssl_errors        = true;
    protected $max_execution_time       = 20000;

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
        if ($domdocument->loadHTML($this->content())) {
            $this->domdocument = $domdocument;

            //get the links from document
            $link_candidates = $domdocument->getElementsByTagName('a');

            foreach ($link_candidates as $link) {

                // get link url
                $url = $this->getAbsoluteUrl($link->getAttribute('href'));

                // to determine if has rel=nofollow
                $rel = mb_strtolower(trim($link->getAttribute('href')));

                if ((! empty($rel)) && (strpos($rel, 'nofollow') !== false))
                    $follow = false;
                else
                    $follow = true;

                if ($url && (! in_array($url, $this->links)))
                    $this->links[]  = (object) [
                                        'url'       => $url,
                                        'follow'    => $follow,
                                    ];
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

        $httpclient = __DIR__ . '/bin/phantom/2.1.1-' . $environment . '/phantomjs';

        //add .exe extension for windows bin
        if ($environment == 'windows')
            $httpclient .= '.exe';
        
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
        $command    .= ' wait-after-load=' . $this->wait_after_load;
        $command    .= ' resource-timeout=' . $this->resource_timeout;
        $command    .= ' web-security=false';
        $command    .= ' load-images=false';
        $command    .= ' max-execution-time=' . $this->max_execution_time;
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
        $command    .= ' wait-after-load=' . $this->wait_after_load;
        $command    .= ' resource-timeout=' . $this->resource_timeout;
        $command    .= ' web-security=false';
        $command    .= ' load-images=true';
        $command    .= ' max-execution-time=' . $this->max_execution_time;
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

                $this->status_code       = (int) $line;

            } elseif ($key == 1) {

                $this->requested_url     = $this->getInfoFromURL($line);

            } elseif ($key == 2) {

                $this->real_url          = $this->getInfoFromURL($line);

            } else {

                $this->content          .= $this->stringToHTMLENTITIES($line);

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
    protected function HtmlentitiesToUtf8($string)
    {   
        $string = html_entity_decode($string, ENT_COMPAT, 'UTF-8');
        
        return $string ? $string : false ;
    }

    /**
     * Convert a string into HTMLENTITIES
     *
     * @param   string  $string
     * @return  string
     */
    protected function stringToHTMLENTITIES($string)
    {   
        $original_charset = mb_detect_encoding($string, mb_detect_order(), true);

        if (! $original_charset)
            return false;

        if ($original_charset != 'HTML-ENTITIES') {
            $string = mb_convert_encoding($string, 'HTML-ENTITIES', $original_charset);
        }

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
        $output = '';

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
            $output = $title['opengraph'];

        elseif (! empty($title['twittercard']))
            $output = $title['twittercard'];

        else {
            $title_tag = $this->domdocument->getElementsByTagName('title');

            if ($title_tag->length > 0)
                $output = $title_tag->item(0)->textContent;
        }

        return empty($output) ? false : trim($this->HtmlentitiesToUtf8($output));
    }

    /**
     * Get the Description
     *
     * @param   void
     * @return  string|false
     */
    public function getDescription()
    {
        $output = '';

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
            $output = $description['opengraph'];

        elseif (! empty($description['twittercard']))
            $output = $description['twittercard'];

        elseif (! empty($description['meta']))
            $output = $description['meta'];

        return empty($output) ? false : trim($this->HtmlentitiesToUtf8($output));
    }

    /**
     * Get the Site Name
     *
     * @param   void
     * @return  string|false
     */
    public function getSiteName()
    {
        $output = '';

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
            $output = $sitename['opengraph'];

        return empty($output) ? false : trim($this->HtmlentitiesToUtf8($output));
    }

    /**
     * Check meta tag robots to determine if page is indexable
     *
     * @param   void
     * @return  boolean
     */
    public function isIndexable()
    {
        if (! $this->domdocument)
            return false;

        $metas      = $this->domdocument->getElementsByTagName('meta');
            
        foreach($metas as $meta) {
        
            //Opengraph Test
            if ($meta->getAttribute('name') == 'robots') {

                $robots = mb_strtolower($meta->getAttribute('content'));

                return strpos($robots, 'noindex') ? false : true;

            }

        }

        // if meta robots not found default behavior is index
        return true;
    }

    /**
     * Check meta tag robots to determine if page is followable
     *
     * @param   void
     * @return  boolean
     */
    public function isFollowable()
    {
        if (! $this->domdocument)
            return false;

        $metas      = $this->domdocument->getElementsByTagName('meta');
            
        foreach($metas as $meta) {
        
            //Opengraph Test
            if ($meta->getAttribute('name') == 'robots') {

                $robots = mb_strtolower($meta->getAttribute('content'));

                return strpos($robots, 'nofollow') ? false : true;

            }

        }

        // if meta robots not found default behavior is index
        return true;
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
        $default_options =  [
                                'only_urls' => true,
                            ];

        $options = array_merge($default_options, $options);

        $links = $this->links;

        $links_array = [];

        foreach ($links as $key => $link) {

            //remove link with javascript script for safe
            if (strpos($link->url, 'javascript:')) {
                unset($links[$key]);
                continue;
            }

            //remove some component(s) from url based on option 'remove'
            if (isset($options['remove']) && is_array($options['remove'])) {

                //remove query component from links
                if (in_array('query', $options['remove'])) {

                    $components = parse_url($link->url);
                    $links[$key]->url = $components['scheme'] . '://' . $components['host'];

                    if (isset($components['path']))
                        $links[$key]->url .= $components['path'];

                }

            }

            //test for uniqueness
            if (in_array($links[$key]->url, $links_array)) {
                unset($links[$key]);
            }
            else {
                $links_array[] = $links[$key]->url;
            }

        }

        return $options['only_urls'] ? array_values($links_array) : array_values($links);
    }

    /**
     * Return the status code for page requested
     *
     * @return int
     */
    public function getStatusCode()
    {
        return (int) $this->status_code;
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

    /**
     * Configure the max execution time in millisenconds to wait before close phnatomjs
     *
     * @param   integer
     * @return  void
     */
    public function setMaxExecutionTime($milliseconds)
    {
        $this->max_execution_time = $milliseconds;
    }
}