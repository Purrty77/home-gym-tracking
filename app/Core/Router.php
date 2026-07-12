<?php

namespace App\Core;

final class Router
{
    private array $routes = [];
    private static array $namedRoutes = [];

    public function get(string $path, array|callable $action, ?string $name=null): void { $this->add('GET', $path, $action, $name); }
    public function post(string $path, array|callable $action, ?string $name=null): void { $this->add('POST', $path, $action, $name); }
    public function redirect(string $from,string $to): void { $this->get($from,static function()use($to):void{header('Location: '.$to,true,302);exit;}); }

    private function add(string $method, string $path, array|callable $action, ?string $name=null): void
    {
        $pattern = preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $path);
        $this->routes[] = [$method, '#^' . $pattern . '/?$#', $action];
        if($name)self::$namedRoutes[$name]=$path;
    }

    public static function urlFor(string $name,array $parameters=[]): string
    {
        if(!isset(self::$namedRoutes[$name]))throw new \RuntimeException("Unknown route: {$name}");
        $path=self::$namedRoutes[$name];
        foreach($parameters as $key=>$value)$path=str_replace('{'.$key.'}',rawurlencode((string)$value),$path);
        return $path;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        if ($method === 'POST' && !Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            View::render('errors/419');
            return;
        }
        foreach ($this->routes as [$verb, $pattern, $action]) {
            if ($verb === $method && preg_match($pattern, $path, $matches)) {
                array_shift($matches);
                if(is_callable($action))$action(...$matches);else{[$class, $function] = $action;(new $class())->{$function}(...$matches);}
                return;
            }
        }
        http_response_code(404);
        View::render('errors/404');
    }
}
