# Laravel Web Harvester

A tool for get information from external websites. Powered by PhantomJS and malahierba.cl dev team

## Installation

Add in your composer.json:

    {
        "require": {
            "malahierba-lab/web-harvester": "1.*"
        }
    }

Then you need run the `composer update` command.

After install you must configure Service Provider. Simply add the service provider in the `config/app.php` providers section:

    Malahierba\WebHarvester\WebHarvesterServiceProvider::class

Now you need publish the config file. Simply execute `php artisan vendor:publish`

## Configuration

Laravel Web Harvester run using PhantomJS headless Webkit browser. This tool is included as binary, so before you can use this package you need to specify your OS. This can be done in config file `config\webharvester.php`.

You need set option `environment` with once of the options supported:

- linux-i686-32
- linux-i686-64
- macosx
- windows

example: `'environment' => 'macosx'`

## Use

**Important**: For documentation purposes, in the examples below, always we assume than you import the library into your namespace using `use Malahierba\WebHarvester;`

### Get WebPage Components

    $url = 'http://someurl';
    $webharvester = new WebHarvester;
    
    //Check if we can process the URL and Load it
    if ($webharvester->load($url)) {

        //Page Title
        $title                   = $webharvester->getTitle();

        //Page Description
        $description             = $webharvester->getDescription();

        //Page Featured Image as URL
        $featured_image_url      = $webharvester->getFeaturedImage();

        //Page Featured Image as Base64
        $featured_image_base_64  = $webharvester->getFeaturedImage('base64');

        //Page real URL (if the $url redirect to another, return the final)
        $real_url                = $webharvester->getRealURL();
    }

### Get found links in WebPage (useful for web crawlers, web spiders, etc.)

    $url = 'http://someurl';
    $webharvester = new WebHarvester;
    
    //Check if we can process the URL and Load it
    if ($webharvester->load($url)) {

        //all full links
        $links = $webharvester->getLinks();  //retrieve an array with found links

        //all links, but query component removed (from the character "?" onwards)
        $links = $webharvester->getLinks([
            'remove' => ['query']
        ]);
    }

**Important**: For security reasons all links with embeded javascript are not included in output array

### Get WebPage Raw Content

    $url = 'http://someurl';
    $webharvester = new WebHarvester;
    
    //Check if we can process the URL and Load it
    if ($webharvester->load($url)) {
        $raw = $webharvester->content();
    }

### Take ScreenShoot of a WebPage

    $url = 'http://someurl';
    $webharvester = new WebHarvester;
    
    //Check if we can process the URL and Load it
    if ($webharvester->takeScreenshot($url)) {
        $image_base_64 = $webharvester->content();  //return a base64 string
    }

### Setup Options

You can customize the webharvester with some functions:

    $webharvester = new WebHarvester;

    //Custom User Agent
    $webharvester->setUserAgent('your user agent');

    //Ignore SSL Errors
    $webharvester->setIgnoreSSLErrors(true);

    //Resource Timeout (in milliseconds)
    $webharvester->setResourceTimeout(3000);

    //Wait after load (in milliseconds)
    $webharvester->setWaitAfterLoad(3000);  // <- useful for get async content
    
## Licence

This project has MIT licence. For more information please read LICENCE file.