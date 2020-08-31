<?php

declare(strict_types = 1);

namespace pczyzyk\P24\Action;

use pczyzyk\P24\Api;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Request\Notify;

class NotifyAction implements ActionInterface, GatewayAwareInterface, ApiAwareInterface
{
    use ApiAwareTrait;
    use GatewayAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    /**
     * {@inheritDoc}
     *
     * @param Notify $request
     */
    public function execute($request)
    {
        /** @var Api $api */
        $api = $this->api;

        RequestNotSupportedException::assertSupports($this, $request);

        /** @var ArrayObject $model */
        $model = ArrayObject::ensureArrayObject($request->getModel());

        $this->gateway->execute($httpRequest = new GetHttpRequest());

        if('POST' != $httpRequest->method) {
            throw new HttpResponse('The notification is invalid. Code 1', 400);
        }
        if(false == $httpRequest->content) {
            throw new HttpResponse('The notification is invalid. Code 2', 400);
        }

        /** @var ArrayObject $details */
        $details = ArrayObject::ensureArrayObject($httpRequest->request);

        $res = $api->trnVerify($details);

        $model['status'] = API::STATUS_VERIFIED;
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Notify &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
