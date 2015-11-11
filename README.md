# Laravel Web Harvester

A tool for get information from external websites. Powered by PhantomJS project (and malahierba dev team)

## Installation

Add in your composer.json:

    {
        "require": {
            "malahierba-lab/web-harvester": "~1.0"
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

### Get the WebPage Components

    $url = 'http://someurl';
    $webharvester = new WebHarvester;
    
    //Check if we can process the URL and Load it
    if ($webharvester->load($url)) {
        $title              = $webharvester->getTitle();
        $description        = $webharvester->getDescription();
        $featured_image_url = $webharvester->getFeaturedImage();
    }

### Get the Links presents in WebPage (useful for web crawlers, spyders, etc.)

    $url = 'http://someurl';
    $webharvester = new WebHarvester;
    
    //Check if we can process the URL and Load it
    if ($webharvester->load($url)) {
        $links = $webharvester->getLinks();  //retrieve an array with found links
    }

### Get the WebPage Raw Content

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
    
## Licence

This project has MIT licence. For more information please read LICENCE file.