<?php

declare(strict_types = 1);

namespace pczyzyk\P24;

use pczyzyk\P24\Action\AuthorizeAction;
use pczyzyk\P24\Action\CancelAction;
use pczyzyk\P24\Action\ConvertPaymentAction;
use pczyzyk\P24\Action\CaptureAction;
use pczyzyk\P24\Action\NotifyAction;
use pczyzyk\P24\Action\RefundAction;
use pczyzyk\P24\Action\StatusAction;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

class P24GatewayFactory extends GatewayFactory
{
    /**
     * {@inheritDoc}
     */
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults([
            'payum.factory_name' => 'p24',
            'payum.factory_title' => 'Przelewy24',

            'payum.action.capture' => new CaptureAction(),
            'payum.action.authorize' => new AuthorizeAction(),
            'payum.action.refund' => new RefundAction(),
            'payum.action.cancel' => new CancelAction(),
            'payum.action.notify' => new NotifyAction(),
            'payum.action.status' => new StatusAction(),
            'payum.action.convert_payment' => new ConvertPaymentAction(),
        ]);

        if (false == $config['payum.api']) {
            $config['payum.default_options'] = array(
                'merchantId' => '',
                'posId' => '',
                'CRC' => '',
                'redirect' => true,
                'sandbox' => true,
            );

            $config->defaults($config['payum.default_options']);
            $config['payum.required_options'] = [
                'merchantId', 'posId', 'CRC'
            ];

            $config['payum.api'] = function (ArrayObject $config) {
                $config->validateNotEmpty($config['payum.required_options']);
                $p24Config = [
                    'p24_merchant_id' => $config['merchantId'],
                    'p24_pos_id' => $config['posId'],
                    'CRC' => $config['CRC'],
                    'redirect' => $config['redirect'],
                    'sandbox' => $config['sandbox'],
                    'secret' => $config['secret'],
                ];

                return new Api(
                    $p24Config,
                    $config['payum.http_client'],
                    $config['httplug.message_factory']
                );
            };
        }
    }
}
