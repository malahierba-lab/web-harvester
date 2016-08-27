var system = require('system');

supportedArgs = [
    'url',                  //URL to process
    'resource-timeout',     //Miliseconds for wait each resource of url (css, fonts, images, etc...)
    'user-agent',           //User Agent
    'exec-javascript',      //Execute Javascript on page
    'load-images',          //Wait for images
    'wait-after-load',      //Usefull for wait a javascript event (example: wait for loading ajax content)
    'web-security',         //Determine whether web security should be enabled or not (default to true)
    'max-execution-time'    //Max timelife in millisecconds before end the page processing. Use 0 for no limit
];

//default options
var options = {
    'url'                : '',
    'user_agent'         : false,
    'resource_timeout'   : 2500,
    'exec_javascript'    : true,
    'load_images'        : false,
    'wait_after_load'    : 0,
    'web_security'       : true,
    'max_execution_time' : 0,
};

function processArguments()
{
    system.args.forEach(function(arg, i)
    {
        //skip first argument, which is the name of the script
        if (i == 0)
            return;

        argument = arg.split('=');

        //check if argument appear as well formed
        if (argument.length < 2)
            return;

        //check for argument is supported
        if (supportedArgs.indexOf(argument[0]) == -1)
            return

        //rebuild the parameters for argument if is necessary ("=" appear more than one time)
        if (argument.length > 2) {

            tempArgument    = argument;
            value           = '';

            tempArgument.forEach(function(component, u)
            {
                //skip first component, which is the name of argument
                if (u == 0)
                    return;

                //use "=" as glue, except for first component
                if (u != 1)
                    value = value + '=';

                value = value + component;
            });

            //rebuild the argument
            argument = [
                tempArgument[0],
                value,
            ];

            delete tempArgument;
            delete value;
        }

        //prepare the name of option
        option_name = argument[0].replace(/-/g, '_');

        //prepare the value for option
        option_value = argument[1];

        //fine tunning for option values

        if (option_value == 'true')
            option_value = true;

        if (option_value == 'false')
            option_value = false;

        if (! isNaN(option_value))
            option_value = parseInt(option_value);
        
        //save the option
        options[option_name] = option_value;
    });
}

function getArgs()
{
    processArguments();

    return options;
}

module.exports = {
    getArgs : getArgs,
};