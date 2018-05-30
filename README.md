# guzzle-logger-json

Utilisation
```
$stack = HandlerStack::create();
$logger = new Logger($name);
$handler = new StreamHandler($this->logFileCurl, Logger::DEBUG);
$formatter = new LineFormatter("%message%\n", 'Y-m-d\TH:i:s.v\Z', false, true);
$handler->setFormatter($formatter);
$logger->pushHandler($handler);
$stack->push(
    Middleware::log(
        $logger,
        new MessageFormatterJson('{hostname} {dest_host} {dest_method} {dest_uri} {code} {req_body} {dest_target} {error} {req_headers} {res_headers}', ['channel' => $name])
    )
);
$client = new Client([
        'base_uri' => 'http://httpbin.org',
        'handler' => $stack,
    ]);

$chk = $client->request('GET', "http://example.com");
```
