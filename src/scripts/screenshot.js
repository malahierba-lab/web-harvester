var system = require('system');

url     = system.args[1];

content         = null;
requested_url   = url;
effective_url   = url;

var page        = require('webpage').create();

page.settings = {
    javascriptEnabled   : true,
    loadImages          : true,
    userAgent           : 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:32.0) Gecko/20100101 Firefox/32.0',
    resourceTimeout     : 10500,
}

page.viewportSize = {
    width: 1024,
    height: 768
};

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
        content = 'data:image/png;base64,' + page.renderBase64('PNG');

        console.log(requested_url);
        console.log(effective_url);
        console.log(content);

        phantom.exit();
    }, 2500);
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