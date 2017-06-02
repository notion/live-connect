<?php
/*
 * This file is part of the siftware/live-connect package.
 *
 * (c) 2014 Siftware <code@siftware.com>
 *
 * Author - Darren Beale @bealers
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Siftware;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

use GuzzleHttp\Psr7\Request;

use GuzzleHttp\Subscriber\Log\LogSubscriber;
use GuzzleHttp\Subscriber\Log\Formatter;

/**
* Wrapper to GuzzleHttp\Client
*/
class LiveRequest
{
    private $endpoint;
    private $authToken;

    private $client;

    private $defaultHeaders;

    private $logger;

    public function __construct($endpoint, LoggerInterface $logger, $authToken = null)
    {
        $this->endpoint     = $endpoint;
        $this->authToken    = $authToken;
        $this->logger       = $logger;
        $this->client       = new Client();

        $this->defaultHeaders = array(
            'User-Agent' => 'https://github.com/siftware/live-connect'
        );

        $this->extraHeaders = array();
    }

    // --

    public function setDebug($debug = false)
    {
        if ($debug)
        {
            /**
            * @see https://github.com/guzzle/log-subscriber
            */
            $subscriber = new LogSubscriber($this->logger, Formatter::DEBUG);
            $this->client->getEmitter()->attach($subscriber);
        }
    }

    // --

    public function post($fields = array(), $files = array())
    {
        $headers = $this->getDefaultHeaders();
        $form_data = [];
        $file_data = [];

        // standard post fields
        foreach ($fields as $key => $value) {
            $form_data[$key] = $value;
        }

        // multipart files, uses Guzzle 4.0 PostFile
        foreach ($files as $file) {
            if (is_array($file)) {
                $file_data[] = $file;
            } else {
                // handle me better
                $this->logger->error("File ignored, not of type multipart");
            }
        }

        $request = new Request("POST", $this->endpoint, $headers);

        return $this->send($request, ['headers' => $headers, 'form_params' => $form_data]);
    }


    // --

    public function get()
    {
        $headers = $this->getDefaultHeaders();

        $request = new Request('GET', $this->endpoint, $headers);

        return $this->send($request, ['headers' => $headers]);
    }

    /**
    * @return \GuzzleHttp\Message\ResponseInterface
    * @todo improve exception handling, return response and allow clients to handle internally
    */
    private function send($request, $options = [])
    {
        try {
            $response = $this->client->send($request, $options);
        } catch (\Exception $e) {

            if ($e->hasResponse()) {

                $response = $e->getResponse();
                $this->logger->error($response->getStatusCode() . ": " . $response->getReasonPhrase());

                $body = $response->getBody();

                if ($body != "")
                {
                     // the error message from Live Connect
                    $json = json_decode($response->getBody());

                    if (property_exists($json, 'error_description')) {

                        $this->logger->debug($json->error_description);
                    } elseif (property_exists($json, 'error')) {

                        $this->logger->debug($json->error->message);
                    } else {

                        $this->logger->debug("Unknown error when communicating with Live Connect");
                    }
                }

            } else {

                $this->logger->error("Unknown error when communicating with Live Connect");
            }

            return false;
        }

        // OK this is a bit weird, the code receiving this is expecting
        // to json_decode it and json_decode($response->json()) give unexpected
        // results. @todo get to the bottom of this
        return (string) $response->getBody();
    }

    /**
    * Headers we should send with every request
    */
    private function getDefaultHeaders()
    {
        $headers = [];
        if (isset($this->authToken)) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }

        $headers = array_merge($headers, $this->defaultHeaders, $this->extraHeaders);

        return $headers;
    }

}