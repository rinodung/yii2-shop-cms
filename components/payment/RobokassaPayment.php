<?php

namespace app\components\payment;

use app\modules\shop\models\OrderTransaction;
use yii\helpers\Json;
use yii\web\BadRequestHttpException;

/***
 *
 * В настройках прописать следующие данные, отправлять с помощью POST
 *
 * Result URL: /shop/payment/custom-check?type=2&action=result
 * Success Url /shop/payment/custom-check?type=2&action=success
 * Fail Url /shop/payment/custom-check?type=2&action=fail
 *
 * Где type - id Robokassa на странице /shop/backend-payment-type/index
 *
 * Class RobokassaPayment
 * @package app\components\payment
 */
class RobokassaPayment extends AbstractPayment
{
    public $merchantLogin;
    public $merchantPass1;
    public $merchantPass2;
    public $merchantUrl;

    public function content()
    {
        $invoiceDescription = urlencode(\Yii::t('app', 'Payment of order #{orderId}', ['orderId' => $this->order->id]));
        $inCurrency = '';
        $culture = 'ru';

        $data = [
            'MrchLogin' => $this->merchantLogin,
            'OutSum' => $this->transaction->total_sum,
            'InvId' => $this->transaction->id,
            'IncCurrLabel' => $inCurrency,
            'Desc' => $invoiceDescription,
            'Culture' => $culture,
            'Encoding' => 'utf-8'
        ];
        if ($this->merchantUrl == 'test.robokassa.ru') {
            $data['IsTest'] = 1;
        }
        $SignatureValue = $this->getSignature();
        $data['SignatureValue'] = $SignatureValue;


        $url = 'http://' . $this->merchantUrl . '/Index.aspx?' . http_build_query($data);


        return $this->render(
            'robokassa',
            [
                'order' => $this->order,
                'url' => $url,
            ]
        );
    }

    /**
     * @return string|null
     */
    public function customCheck()
    {
        if (null === $this->transaction = $this->loadTransaction(\Yii::$app->request->post('InvId'))) {
            throw new BadRequestHttpException();
        }
        if (\Yii::$app->request->get('action') == 'result') {
            $this->checkResult($this->transaction->generateHash());
        } elseif (\Yii::$app->request->get('action') == 'success') {
            return $this->redirect(
                $this->createSuccessUrl()
            );
        } else {
            return $this->redirect(
                $this->createErrorUrl()
            );
        }

    }


    public function checkResult($hash = '')
    {
        $result = "bad sign\n";

        if ($this->getSignature(true) == \Yii::$app->request->post('SignatureValue')) {
            $this->transaction->result_data = Json::encode([
                \Yii::$app->request->post('OutSum'),
                \Yii::$app->request->post('InvId'),
                \Yii::$app->request->post('SignatureValue')
            ]);
            $this->transaction->status = OrderTransaction::TRANSACTION_SUCCESS;
            if ($this->transaction->save(true, ['status', 'result_data'])) {
                $result = "OK" . $this->transaction->id . "\n";
            }
        }
        echo $result;
        $this->end();
    }

    protected function getSignature($check = false)
    {
        $sData = [
            $this->merchantLogin,
            $this->transaction->total_sum,
            $this->transaction->id,

        ];
        $sData[] = $check ? $this->merchantPass2 : $this->merchantPass1;
        return md5(implode(':', $sData));
    }

}

?>