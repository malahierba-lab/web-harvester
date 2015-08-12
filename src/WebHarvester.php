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
        ];

        $this->reset();

        if (empty($url))
            return false;

        $command = $this->getLoadCommand($url);

        exec($command, $output, $status);

        if ($status !== 0)
            return false;
    
        $this->extractData($output);
        
        $this->content = $this->stringToUTF8($this->content);

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

        $command = $httpclient_path . ' --ssl-protocol=any --ignore-ssl-errors=true ' . $script_path . ' ' . $url;

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

        $command = $httpclient_path . ' --ssl-protocol=any --ignore-ssl-errors=true --web-security=false ' . $script_path . ' ' . $url;

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
                $this->effective_url = $this->getInfoFromURL($line);
            } else {
                $this->content .= $line;
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
                                'UTF-8',
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
                            );
        
        $original_charset = mb_detect_encoding($string, $codifications, true);

        if (! $original_charset)
            return false;

        if ($original_charset != 'UTF-8')
            $string = mb_convert_encoding($string, 'UTF-8', $original_charset);
        
        $string = mb_convert_encoding($string, 'UTF-8', 'HTML-ENTITIES');

        return $string;
    }
}