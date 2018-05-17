# guzzle-logger

Utilisation
```
$stack = HandlerStack::create();
$logger = new Logger('guzzle');
$logger->pushHandler(new StreamHandler('/tmp/guzzle.log', Logger::DEBUG));
$stack->push(
    Middleware::log(
        $logger,
        new MessageFormatterJson('{hostname} {host} {method} {uri} {code} {req_body} {target} {error} {req_headers} {res_headers} {res_header_cache-control}')
    )
);

$client = new Client([
        'base_uri' => 'http://httpbin.org',
        'handler' => $stack,
    ]);

$chk = $client->request('GET', "http://example.com");
```
