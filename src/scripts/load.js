var arguments   = require('./arguments.js');

var options     = arguments.getArgs();

url             = options.url;

content         = null;
requested_url   = url;
effective_url   = url;

var page        = require('webpage').create();

page.settings = {
    javascriptEnabled   : options.exec_javascript,
    loadImages          : options.load_images,
    userAgent           : options.user_agent,
    resourceTimeout     : options.resource_timeout,
    webSecurityEnabled  : options.web_security,
    encoding            : "utf8",
}

page.onResourceError = function(resourceError)
{
    page.reason = resourceError.errorString;
    page.reason_url = resourceError.url;
};

page.onUrlChanged = function(targetURL)
{
    effective_url = targetURL;
};

page.onError = function(msg, trace)
{
    var msgStack = ['ERROR: ' + msg];
    if (trace && trace.length) {
        msgStack.push('TRACE:');
        trace.forEach(function(t) {
            msgStack.push(' -> ' + t.file + ': ' + t.line + (t.function ? ' (in function "' + t.function + '")' : ''));
        });
    }
    // uncomment to log into the console 
    // console.error(msgStack.join('\n'));
};

page.onLoadFinished = function (status)
{
    setTimeout (function()
    {
        //content = page.content;
        content = page.content;

        console.log(requested_url);
        console.log(effective_url);
        console.log(content);

        phantom.exit();
    }, options.wait_after_load);
}

page.open(url, function (status)
{
    if (status !== 'success') {
        console.log(
            "Error opening url \"" + page.reason_url
            + "\": " + page.reason
        );
        phantom.exit(1); //Status 1: no se pudo cargar la URL
    }
});