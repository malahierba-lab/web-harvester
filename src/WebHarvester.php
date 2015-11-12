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
    public function requestedURL()
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
    public function realURL()
    {
        return (object) $this->real_url;
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
     * @param   void
     * @return  string|false
     */
    public function getFeaturedImage()
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

        return $this->getAbsoluteUrl($image_url);
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

        $title  = '';
            
        foreach($metas as $meta) {
        
            //Opengraph Test
            if ($meta->getAttribute('property') == 'og:title') {

                $title_candidate =  trim($meta->getAttribute('content'));

                if (strlen($title_candidate) > strlen($title))
                    $title = $title_candidate;
            }

            //Twitter Tags Test
            if ($meta->getAttribute('name') == 'twitter:title') {

                $title_candidate =  trim($meta->getAttribute('content'));

                if (strlen($title_candidate) > strlen($title))
                    $title = $title_candidate;
            }
        }

        if (empty($title)) {

            //Title tag test
            $title_tag = $this->domdocument->getElementsByTagName('title');

            if ($title_tag->length > 0) {
                $title_candidate = trim($title_tag->item(0)->nodeValue);

                if (strlen($title_candidate) > strlen($title))
                    $title = $title_candidate;
            }

        }

       return empty($title) ? false : $title;
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

        $description  = '';
            
        foreach($metas as $meta) {
        
            //Opengraph Test
            if ($meta->getAttribute('property') == 'og:description') {

                $description_candidate =  trim($meta->getAttribute('content'));

                if (strlen($description_candidate) > strlen($description))
                    $description = $description_candidate;
            }

            //Twitter Tags Test
            if ($meta->getAttribute('name') == 'twitter:description') {

                $description_candidate =  trim($meta->getAttribute('content'));

                if (strlen($description_candidate) > strlen($description))
                    $description = $description_candidate;
            }

            //Description Meta Test
            if ($meta->getAttribute('name') == 'description') {

                $description_candidate =  trim($meta->getAttribute('content'));

                if (strlen($description_candidate) > strlen($description))
                    $description = $description_candidate;
            }
        }

       return empty($description) ? false : $description;
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
                $url .= $test_url['query'];

        }

        return $url;
    }

    /**
     * Return the links presents in document (only work after load method)
     *
     * @param   void
     * @return  array
     */
    public function getLinks()
    {
        return $this->links;
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