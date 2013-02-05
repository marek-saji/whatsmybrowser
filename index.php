<?php
/**
 * Quick and dirty script for collecting browser information.
 * Useful for bug reports.
 * (c) Marek `saji` Augustynowicz
 * Licensed under MIT. http://marek-saji.mit-license.org/
 */

$path_flat = trim(@$_SERVER['PATH_INFO'], '/');
$path = $path_flat ? explode('/', $path_flat) : array();

if (@$_POST)
{
    $data = $_POST;
    foreach ($data as & $one_data)
    {
        $one_data = json_decode($one_data, true);
        unset($one_data);
    }
    $serialized_data = json_encode($data, JSON_PRETTY_PRINT);
    $ident = substr(sha1($serialized_data), 0, 7);
    $file_path = "./results/{$ident}.json";
    file_put_contents($file_path, $serialized_data);
    header('Location: ./' . $ident);
}
else if (false === empty($path))
{
    @list($ident, $format) = explode('.', $path[0], 2);
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
    $server = array();
    foreach ($_SERVER as $name => & $one_data)
    {
        if (substr($name, 0, 5) === 'HTTP_')
        {
            $server[substr($name, 5)] =& $one_data;
        }
        unset($name, $one_data);
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>What's my browser?</title>
    <script src="modules/JSON-js/json2.js"></script>
</head>

<body class=" nojs ">
    <script>
        (function (b) {
            b.className = b.className.replace(/ nojs /, " js ");
        }(window.document.body));
    </script>

    <?php if (false === empty($data)) : ?>

        <?php if ('json' === $format) : ?>
            <pre><?=htmlspecialchars($data)?></pre>
        <?php else : ?>
            <p>Include this link in your bug report:</p>
            <strong>http://<?=$_SERVER['HTTP_HOST']?><?=$_SERVER['REQUEST_URI']?></strong>
        <?php endif; ?>

    <?php else : ?>

        <form action="." method="post" id="form">
            <input type="hidden" name="server" value='<?=str_replace("'", "'&#039;", htmlspecialchars(json_encode($server), ENT_NOQUOTES|ENT_HTML5))?>' />
            <input type="hidden" name="client" id="client" value="" />
            <input type="submit" value="Get the results" />
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
                        "CSSStyleSheet" : CSSStyleSheet
                    };

                    // Censor some paths containing dates and times to encourage
                    // results' ident reuse.
                    var censoredPaths = {
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
                            else if (typeof input[p] === "object")
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

    <?php endif; ?>

</body>

</html>
