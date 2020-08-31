<?php

declare(strict_types=1);

namespace pczyzyk\P24;

use ArrayAccess;
use GuzzleHttp\Psr7\Response;
use Http\Message\MessageFactory;
use pczyzyk\P24\Exception\GatewayException;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\Http\HttpException;
use Payum\Core\HttpClientInterface;
use Unirest;

class Api {

    public const STATUS_NEW = 'new';
    public const STATUS_PENDING = 'pending';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_VERIFIED = 'verified';
    public const CURRENCY_PLN = 'PLN';
    public const CURRENCY_EUR = 'EUR';
    public const CURRENCY_GBP = 'GBP';
    public const CURRENCY_CZK = 'CZK';
    private const METHOD_REGISTER = 'trnRegister';
    private const METHOD_VERIFY = 'trnVerify';
    private const METHOD_TEST = 'testConnection';

    /** Api version */
    private const VERSION = '3.2';

    /** defaulut api url */
    private const DEFAULT_URL = 'https://secure.przelewy24.pl/';
    private const SANDBOX_URL = 'https://sandbox.przelewy24.pl/';

    /**
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * @var array
     */
    protected $options = [
        'p24_merchant_id' => null,
        'p24_pos_id' => null,
        'CRC' => null,
        'redirect' => false, // Set true to redirect to Przelewy24 after transaction registration
        'sandbox' => true,
    ];

    /**
     * @param array               $options
     * @param HttpClientInterface $client
     * @param MessageFactory      $messageFactory
     *
     * @throws \Payum\Core\Exception\InvalidArgumentException if an option is invalid
     */
    public function __construct(array $options, HttpClientInterface $client, MessageFactory $messageFactory) {
        $options = ArrayObject::ensureArrayObject($options);
        $options->defaults($this->options);
        $options->validateNotEmpty([
            'p24_merchant_id', 'p24_pos_id', 'CRC'
        ]);

        $this->options = $options;
        $this->client = $client;
        $this->messageFactory = $messageFactory;

        //dump($this);
    }

    /* public function testConnection()
      {
      $fields = [
      'p24_merchant_id' => $this->options['p24_merchant_id'],
      'p24_pos_id' => $this->options['p24_pos_id'],
      'p24_sign' => md5(
      $this->options['p24_pos_id'].'|'.$this->options['CRC']
      ),
      ];

      $response = $this->doRequest('testConnection', $fields);

      dump($fields);
      $res = $response->getBody()->getContents();
      dump(explode('&', $res));

      exit;
      } */

    /**
     * @return bool
     */
    public function isRedirect(): bool {
        return (bool) $this->options['redirect'];
    }

    /**
     * Prepare a transaction request
     */
    public function trnRegister(ArrayAccess $details): string {
        $details->validateNotEmpty([
            'p24_session_id', 'p24_amount', 'p24_currency', 'p24_email', 'p24_description', 'notify_url', 'done_url'
        ]);
//dump($details);
        $fields = [
            'sign' => $this->generateCrcSum($details, static::METHOD_REGISTER),
            'merchantId' => $this->options['p24_merchant_id'],
            'posId' => $this->options['p24_pos_id'],
            'sessionId' => $details['p24_session_id'],
            'amount' => $details['p24_amount'],
            'currency' => $details['p24_currency'],
            'description' => $details['p24_description'] ?? '',
            'email' => $details['p24_email'],
            'urlStatus' => $details['notify_url'],
            'urlReturn' => $details['done_url'],
            'country' => 'PL',
            'language' => 'pl',
            'encoding' => 'UTF-8',
            'channel' => 16,
        ];
        /** @var Response $response */
        $response = $this->doRequest(static::METHOD_REGISTER, $fields);
        return $response['data']['token'];
    }

    public function trnRequest(string $token) {
        header("Location:" . $this->getApiEndpoint() . "trnRequest/" . $token);
        exit;
    }

    public function trnVerify(ArrayAccess $details) {
        $details->validateNotEmpty([
            'sessionId', 'amount', 'currency', 'orderId'
        ]);

        $fields = [
            'sign' => $this->generateCrcSum($details, static::METHOD_VERIFY),
            'merchantId' => $details['merchantId'],
            'posId' => $details['posId'],
            'sessionId' => $details['sessionId'],
            'amount' => $details['amount'],
            'currency' => $details['currency'],
            'orderId' => $details['orderId'],
        ];

        /** @var Response $response */
        $response = $this->doRequest(static::METHOD_VERIFY, $fields);
        return $response['data'];
    }

    /**
     * @param ArrayAccess $details
     *
     * @return string
     */
    protected function generateCrcSum(ArrayAccess $details, string $type): string {
        $crc = $this->options['CRC'];
        $what = [
            "sessionId" => $details['p24_session_id'],
            "merchantId" => $this->options['p24_merchant_id'],
            "amount" => $details['p24_amount'],
            "currency" => $details['p24_currency'],
            "crc" => $crc
        ];
        $whatVerify = [
            "sessionId" => $details['sessionId'],
            "orderId" => $details['orderId'],
            "amount" => $details['amount'],
            "currency" => $details['currency'],
            "crc" => $crc
        ];
        switch ($type) {
            case static::METHOD_REGISTER:
                $controlSum = hash('sha384', json_encode($what, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                break;
            case static::METHOD_VERIFY:
                $controlSum = hash('sha384', json_encode($whatVerify, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                break;
            case static::METHOD_TEST:
                $controlSum = md5(
                        $this->options['p24_pos_id'] . "|"
                        . $this->options['CRC']
                );
                break;
            default:
                throw GatewayException::factory('Bad method call');
                break;
        }

        return $controlSum;
    }

    /**
     * @param $method
     * @param array $fields
     *
     * @return array
     */
    protected function doRequest($function, array $fields) {
        $method = 'POST';
        $user = $this->options['p24_merchant_id'];
        $pass = $this->options['secret'];
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $fields = Unirest\Request\Body::json($fields);

        Unirest\Request::auth($user, $pass);

        if ($function == "trnRegister") {
            $function = "api/v1/transaction/register";
            $response = Unirest\Request::post($this->getApiEndpoint() . $function, $headers, $fields);
        } else if ($function == "trnVerify") {
            $function = "api/v1/transaction/verify";
            $response = Unirest\Request::put($this->getApiEndpoint() . $function, $headers, $fields);
        } else {
            $response = Unirest\Request::post($this->getApiEndpoint() . $function, $headers, $fields);
        }

        if (false == ($response->code >= 200 && $response->code < 300)) {
          
            throw new \Exception("$response->code, $response->raw_body");

        }

        $content = $response->body;

        if (empty($content) || 0 !== $content->responseCode) {
            throw new \Exception($content);

        }

        $status = (int) $content->responseCode;
        if ($function == "trnRegister") {
            $data = !empty($content) ? ['token' => $content->data->token] : null;
        } else if ($function == "trnVerify") {
            $data = !empty($content) ? ['status' => $content->data->status] : null;
        } else {
            $data = !empty($content) ? (array)$content->data : null;
        }

        $response = [
            'status' => $status,
            'data' => $data
        ];

        return $response;
    }

    /**
     * @return string
     */
    protected function getApiEndpoint(): string {
        return !$this->options['sandbox'] ?
                self::DEFAULT_URL :
                self::SANDBOX_URL;
    }

}
