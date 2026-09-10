<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Str;

if (!function_exists('is_assoc_array')) {
    /**
     * @return boolean
     */
    function is_assoc_array(array $var) {
        return count(
            array_filter(array_keys($var), 'is_string')
        ) > 0;
    }
}

if (!function_exists('nsval')) {
    /**
     * String to namespace
     *
     * @return string
     */
    function nsval(string $value) {
        return preg_replace_callback(
            '/(?:(?:^|\.|\s|\\\{1,2})([A-z\_]*))/',
            function ($match) {
                $match[1] = Str::studly($match[1]);
                $match[1] = "\\{$match[1]}";
                return $match[1];
            },
            $value
        );
    }
}

if (!function_exists('ns_search')) {
    /**
     * Seach the same class in another namspace
     *
     * @return string|null
     */
    function ns_search(string $namespace, string $target, ?array $replaces = [], ?string $namespace_prefix = null) {
        // If every substitution below is a no-op (the calling class
        // doesn't sit under the expected folder convention),
        // $namespace ends unchanged and class_exists() would trivially
        // "find" the original calling class again - which callers then
        // instantiate, whose constructor repeats this same resolution
        // indefinitely. A class can never legitimately resolve to
        // itself here, so that outcome is treated as a failed
        // resolution, same as no match at all.
        $original_namespace = $namespace;
        $prefixes = [ 'App\\', 'Rivet\\' ];
        $target = Str::studly($target);

        if (!is_null($namespace_prefix)) {
            $prefixes[] = $namespace_prefix;
        }

        foreach ($replaces as $origin => $value) {
            $namespace = Str::replace($origin, $value, $namespace);
        }

        preg_match('/(?:[A-Z][a-z]*?)$/', $namespace, $origin);
        $origin = $origin[0];

        if (!Str::startsWith(Str::after(Str::remove($prefixes, $namespace), '\\'), Str::plural($origin))) {
            $origin = 'Model';
        }

        $namespace = Str::replace(
            Str::plural($origin), Str::plural($target), $namespace
        );
        $namespace = Str::replace($origin, $target, $namespace);

        if (!class_exists($namespace)) {
            $namespace = Str::replaceLast($target, '', $namespace);
        }

        if (!class_exists($namespace)) {
            $namespace .= $target;
        }

        return (
            class_exists($namespace) && $namespace !== $original_namespace
        )? $namespace: null;
    }
}

if (!function_exists('ra_to_uid')) {
    /**
     * Route action to permission uid
     *
     * @return string|null
     */
    function ra_to_uid(
        Route $route, string $ctrl_folder = 'Http\\Controllers'
    ) {
        $uid = '';
        $action = $route->action['uses'];

        if (is_string($action)) {
            $uri = $route->uri;
            // Hash::make()
            $uri = preg_replace_callback(
                '/(?:^\\/?[A-z]|\\/[A-z]|\\_[A-z]|\\-[A-z])([^\\/\\_$]+)/',
                function ($matches) {
                    return Str::substr(
                        Str::replace($matches[1], '', $matches[0]), -1
                    );
                }, $uri
            );
            $uri = preg_replace('/\\/?\\{[^\\{\\}]+\\}/', '', $uri);

            $uri = ($uri === '/')? '': $uri;

            if (!Str::startsWith($ctrl_folder, '\\')) {
                $ctrl_folder = "\\{$ctrl_folder}";
            }

            if (!Str::endsWith($ctrl_folder, '\\')) {
                $ctrl_folder .= '\\';
            }

            $namespace = Str::before($action, $ctrl_folder);
            $uid = (
                Str::contains($namespace, 'App')?
                    'APP': preg_replace('/[a-z\\\]/', '', $namespace)
            );
            $uid .= "{$uri}_";
            $action = Str::afterLast($action, $ctrl_folder);
            $action = Str::replace('\\', '', $action);
            $action = Str::replace('Controller', '', $action);
            $action = Str::replace('@', '_', $action);
            $uid .= $action;
            $uid = Str::upper($uid);
        }

        return $uid;
    }
}
