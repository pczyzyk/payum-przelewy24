<?php

declare(strict_types = 1);

namespace pczyzyk\P24\Action;

use pczyzyk\P24\Api;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Request\GetStatusInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;

class StatusAction implements ActionInterface
{
    /**
     * {@inheritDoc}
     *
     * @param GetStatusInterface $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        //dump('status');
        //dump($model);
        //dump($model['status']);
        //dump($request);
        if (null === $model['status'] || Api::STATUS_NEW == $model['status']) {
            $request->markNew();
            return;
        }
        /*elseif ($model['status'] == 'PENDING') {
            $request->markPending();
            return;
        } elseif ($model['status'] == 'COMPLETED') {
            $request->markCaptured();
            return;
        }elseif ($model['status'] == 'CANCELED') {
            $request->markCanceled();
            return;
        } elseif ($model['status'] == 'REJECTED') {
            $request->markFailed();
            return;
        }*/

        switch (true) {
            case (null === $model['status'] || Api::STATUS_NEW == $model['status']):
                $request->markNew();
                return;
            case (null === $model['status'] || Api::STATUS_VERIFIED == $model['status']):
                $request->markCaptured();
                return;
            default:
                dump($model['status']);
                exit('StatusAction: unknown status');
                $request->markUnknown();
                exit;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
