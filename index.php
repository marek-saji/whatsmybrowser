<?php
/**
 * Quick and dirty script for collecting browser information.
 * Useful for bug reports.
 *
 * If follow_to GET param is included, after collecting information,
 * user will be redirected to given page. `%s` in the param will
 * be replaced with URL displaying collected information.
 *
 * (c) Marek `saji` Augustynowicz
 * Licensed under MIT. http://marek-saji.mit-license.org/
 */
list($path_flat) = explode('?', trim(@$_SERVER['REQUEST_URI'], '/'), 2);
$path = $path_flat ? explode('/', $path_flat) : array();

if (false === empty($_GET['server']))
{
    // Save new information

    // decode each entry in $_REQUEST (== GET + POST),
    // then encode everything together
    $data = json_encode(
        array_map(
            function ($json) { return json_decode($json, true); },
            $_REQUEST)
        ,
        JSON_PRETTY_PRINT
    );
    $ident = substr(sha1($data), 0, 7);

    $file_path = "./results/{$ident}.json";
    file_put_contents($file_path, $data);

    $follow_to = "http://{$_SERVER['HTTP_HOST']}/{$ident}";
    if (isset($_GET['follow_to']))
    {
        $follow_to = sprintf($_GET['follow_to'], $follow_to);
    }
    header('Location: ' . $follow_to);
}
else if (false === empty($path))
{
    // Display saved information

    $ident = reset($path);

    if (basename($ident) !== $ident)
    {
        header('HTTP/1.0 500 Internal Server Error');
        echo '500 Go to hell.';
        die(500);
    }

    $file_path = "./results/{$ident}.json";
    $data = @ file_get_contents($file_path);

    if (false === $data)
    {
        header('HTTP/1.0 404 Not Found');
        echo '404 Not Found';
        die(404);
    }
}
else
{
    // Prepare for collecting information: save server data
    $server = array();
    foreach ($_SERVER as $name => & $one_data)
    {
        if ('HTTP_CACHE_CONTROL' !== $name && substr($name, 0, 5) === 'HTTP_')
        {
            $server[substr($name, 5)] =& $one_data;
        }
        unset($name, $one_data);
    }
}

?>
<!DOCTYPE html>
<html>

<?php if (false === empty($data)) : ?>

    <?php
    // Display collected information
    ?>

    <head>
        <title>What's my browser?</title>
        <script src="modules/JSON-js/json2.js"></script>

        <style>

            html, body
            {
                font-family:
                    Roboto,
                    Droid Sans,
                    sans-serif;
                margin: 0;
                padding: 0;
                background-color: silver;
            }
            pre
            {
                font-family:
                    Source Code Pro Light,
                    Source Code Pro,
                    Consolas,
                    Monaco,
                    Droid Sans Mono,
                    Lucida Console,
                    Lucida Sans Typewriter,
                    Andale Mono,
                    monospace;
            }
            p
            {
                margin: 0 0 1em;;
            }
            #share
            {
                vertical-align: middle;

                text-align: center;
                padding: 3em 1em;
                font-size: 1.2em;
            }
            #details
            {
                background-color: white;
                border-radius: 1em 1em 0 0;
                padding: 2em;
            }
        </style>
    </head>

    <body>

        <section id="share">
            <div class="😢">
                <p>Include this link in your bug report:</p>
                <strong>http://<?=$_SERVER['HTTP_HOST']?><?=$_SERVER['REQUEST_URI']?></strong>
            </div>
        </section>

        <section id="details">
            <p>
                Details:
            </p>
            <pre><?= htmlspecialchars($data) ?></pre>
        </section>

    </body>

<?php else : ?>

    <?php
    // Collect information:
    // - for non-JS browsers, meta-refresh will collect only server data
    // - JS browsers will also give up lots of information from window object
    ?>

    <?php
    $target_query = array(
        'server'    => json_encode($server)
    );
    if (isset($_GET['follow_to']))
    {
        $target_query['follow_to'] = $_GET['follow_to'];
    }
    $target_url = '/?' . http_build_query($target_query, ENT_NOQUOTES|ENT_HTML5);
    ?>

    <head>
        <title>What's my browser?</title>
        <script src="modules/JSON-js/json2.js"></script>
        <?php if (true === empty($data)) : ?>
        <meta http-equiv="refresh" content="3;<?=$target_url?>" />
        <?php endif; /* false === empty($data) */ ?>
    </head>

    <body>

        <p>Heading to results page...</p>

        <form method="post" id="form" action="<?=$target_url?>">
            <input type="hidden" name="client" id="client" value="false" />
            <input type="submit" value="" style="display:none" />
        </form>

        <script>
            (function () {
                "use strict";

                function parse (input, maxDepth)
                {
                    var output,
                        refs      = [input],
                        refsPaths = ["this"];

                    // Censor some of object types.
                    var censoredObjects = {
                        "Element"       : Element,
                        "CSSStyleSheet" : CSSStyleSheet,
                        "History"       : History
                    };

                    // Censor some paths to encourage results' ident reuse.
                    var censoredPaths = {
                        "this.history.length"              : true,
                        "this.performance.navigation.type" : true,
                        // dates and times
                        "this.performance.timing"    : true,
                        "this.document.lastModified" : true
                    };

                    maxDepth = maxDepth || 5;

                    function recursion (input, path, depth, undefined)
                    {
                        var output = {},
                            pPath,
                            idx;

                        path  = path  || "this";
                        depth = depth || 0;
                        depth++;

                        if (maxDepth && depth > maxDepth)
                        {
                            return "{depth over " + maxDepth + "}";
                        }

                        for (var p in input)
                        {
                            try
                            {
                                pPath = (path ? (path+".") : "") + p;

                                if (censoredPaths[pPath])
                                {
                                    output[p] = "{censored path}";
                                }
                                else if (typeof input[p] === "function")
                                {
                                    output[p] = "{function}";
                                }
                                else if (typeof input[p] === "undefined")
                                {
                                    // discard "hidden" properties (like document.all)
                                    output[p] = undefined;
                                }
                                else if (null !== input[p] && typeof input[p] === "object")
                                {
                                    for (idx in censoredObjects)
                                    {
                                        if (input[p] instanceof censoredObjects[idx])
                                        {
                                            output[p] = "{" + idx + "}";
                                            break;
                                        }
                                    }

                                    if (undefined === output[p])
                                    {
                                        idx = refs.indexOf(input[p]);

                                        if (-1 !== idx)
                                        {
                                            output[p] = "{reference to " + refsPaths[idx]  + "}";
                                        }
                                        else
                                        {
                                            refs.push(input[p]);
                                            refsPaths.push(pPath);
                                            output[p] = recursion(input[p], pPath, depth);
                                        }
                                    }
                                }
                                else
                                {
                                    output[p] = input[p];
                                }
                            }
                            catch (e)
                            {
                                output[p] = "{exception thrown: " + e + "}";
                            }
                        }

                        return output;
                    }

                    if (typeof input !== "object")
                    {
                        output = input;
                    }
                    else
                    {
                        output = recursion(input);
                    }

                    return output;
                }

                document.getElementById('client').value = JSON.stringify(parse(window));
                document.getElementById('form').submit();
            }());
        </script>

    </body>

<?php endif; ?>

</html>
