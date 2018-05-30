<?php
/**
 * Created by IntelliJ IDEA.
 * User: greg
 * Date: 16/05/18
 * Time: 11:35
 */

namespace Interencheres;

use \Exception;
use GuzzleHttp\Psr7;
use Monolog\Logger;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
/**
 * Formats log messages using variable substitutions for requests, responses,
 * and other transactional data.
 *
 * The following variable substitutions are supported:
 *
 * - {request}:        Full HTTP request message
 * - {response}:       Full HTTP response message
 * - {ts}:             ISO 8601 date in GMT
 * - {date_iso_8601}   ISO 8601 date in GMT
 * - {date_common_log} Apache common log date using the configured timezone.
 * - {dest_host}:      Host of the request
 * - {dest_method}:    Method of the request
 * - {dest_uri}:       URI of the request
 * - {dest_version}:   Protocol version
 * - {dest_target}:    Request target of the request (path + query + fragment)
 * - {hostname}:       Hostname of the machine that sent the request
 * - {code}:           Status code of the response (if available)
 * - {phrase}:         Reason phrase of the response  (if available)
 * - {error}:          Any error messages (if available)
 * - {req_header_*}:   Replace `*` with the lowercased name of a request header to add to the message
 * - {res_header_*}:   Replace `*` with the lowercased name of a response header to add to the message
 * - {req_headers}:    Request headers
 * - {res_headers}:    Response headers
 * - {req_body}:       Request body
 * - {res_body}:       Response body
 */
class MessageFormatterJson extends \GuzzleHttp\MessageFormatter
{
    const FULL = '{request}{response}{ts}{date_iso_8601}{date_common_log}{dest_host}{dest_method}{dest_uri}{dest_version}{target}{hostname}{code}{phrase}{error}{req_header_*}{res_header_*}{req_headers}{res_headers}{req_body}{res_body}';
    const CLF = "{hostname} {req_header_User-Agent} - [{date_common_log}] \"{dest_method} {target} HTTP/{dest_version}\" {code} {res_header_Content-Length}";
    const DEBUG = ">>>>>>>>\n{request}\n<<<<<<<<\n{response}\n--------\n{error}";
    const SHORT = '[{ts}] "{dest_method} {target} HTTP/{dest_version}" {code}';
    /** @var string Template used to format log messages */
    private $template;
    /**
     * @param string $template Log message template
     * @param array $contextArray channel & LogLevel
     */
    public function __construct($template = self::FULL, array $contextArray)
    {
        $this->template = $template ?: self::FULL;
        $this->extraInfo = $contextArray;

    }
    /**
     * Returns a formatted message string.
     *
     * @param RequestInterface  $request  Request that was sent
     * @param ResponseInterface $response Response that was received
     * @param \Exception        $error    Exception that was received
     *
     * @return string
     */
    public function format(
        RequestInterface $request,
        ResponseInterface $response = null,
        Exception $error = null
    ) {
        $dateRequest = new DateTime();
        $cache = [];

        $cache['@datetime'] = $dateRequest->format('Y-m-d\TH:i:s\Z');
        $cache['channel'] = $this->extraInfo['channel'];

        preg_replace_callback(
            '/{\s*([A-Za-z_\-\.0-9]+)\s*}/',
            function (array $matches) use ($request, $response, $error, &$cache) {
                if (isset($cache[$matches[1]])) {
                    return $cache[$matches[1]];
                }
                $result = '';
                switch ($matches[1]) {
                    case 'request':
                        $result = Psr7\str($request);
                        break;
                    case 'response':
                        $result = $response ? Psr7\str($response) : '';
                        break;
                    case 'req_headers':
                        $result = array_merge([
                            'Protocol-Method' => $request->getMethod(),
                            'Protocol-Version' => $request->getProtocolVersion(),
                            'Protocol-Host' => $request->getUri()->getHost(),
                            'Protocol-Port' => $request->getUri()->getPort() ? $request->getUri()->getPort() : 80,
                            'Protocol-Request-Target' => $request->getRequestTarget()
                        ],
                            $this->headers($request)
                        )
                        ;
                        break;
                    case 'res_headers':
                        $result = $response ?
                            array_merge([
                                'Protocol-Version' => $response->getProtocolVersion(),
                                'Status-Code' => $response->getStatusCode(),
                                'Reason-Phrase' => $response->getReasonPhrase()
                            ],
                                $this->headers($response)
                            )
                            : 'NULL';
                        ;
                        break;
                    case 'req_body':

                        $result = $request->getBody()->__toString();
                        break;
                    case 'res_body':
                        $result = $response ? $response->getBody()->__toString() : 'NULL';
                        break;
                    case 'ts':
                    case 'date_iso_8601':
                        $result = gmdate('c');
                        break;
                    case 'date_common_log':
                        $result = date('d/M/Y:H:i:s O');
                        break;
                    case 'dest_method':
                        $result = $request->getMethod();
                        break;
                    case 'dest_version':
                        $result = $request->getProtocolVersion();
                        break;
                    case 'dest_uri':
                    case 'url':
                        $result = $request->getUri()->__toString();
                        break;
                    case 'target':
                        $result = $request->getRequestTarget();
                        break;
                    case 'req_version':
                        $result = $request->getProtocolVersion();
                        break;
                    case 'res_version':
                        $result = $response
                            ? $response->getProtocolVersion()
                            : 'NULL';
                        break;
                    case 'dest_host':
                        $result = $request->getHeaderLine('Host');
                        break;
                    case 'hostname':
                        $result = gethostname();
                        break;
                    case 'code':
                        $result = $response ? $response->getStatusCode() : 'NULL';
                        break;
                    case 'phrase':
                        $result = $response ? $response->getReasonPhrase() : 'NULL';
                        break;
                    case 'error':
                        $result = $error ? $error->getMessage() : 'NULL';
                        break;
                    default:
                        // handle prefixed dynamic headers
                        if (strpos($matches[1], 'req_header_') === 0) {
                            $result = $request->getHeaderLine(substr($matches[1], 11));
                        } elseif (strpos($matches[1], 'res_header_') === 0) {
                            $result = $response
                                ? $response->getHeaderLine(substr($matches[1], 11))
                                : 'NULL';
                        }
                }
                if ($result) {
                    $cache[$matches[1]] = $result;
                }
                return;
            },
            $this->template
        );
        return json_encode($cache);
    }

    private function headers(MessageInterface $message)
    {
        $result = [];
        foreach ($message->getHeaders() as $name => $values) {
            $result[$name] = implode(', ', $values);
        }
        return $result;
    }
}
