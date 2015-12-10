<?php
/**
 * @var \yii\web\View $this
 * @var \app\modules\shop\models\Customer $model
 */

use \app\backend\widgets\BackendWidget;
use yii\helpers\Html;
use app\components\Helper;

    $this->title = Yii::t('app', 'Customer edit');
    $this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Customers'), 'url' => ['index']];
    $this->params['breadcrumbs'][] = $this->title;
?>

    <div class="col-md-12" style="margin: 15px 0;">
        <div class="row">
            <?= Html::a('All customer orders', '#orders', ['class' => 'btn btn-default']); ?>
            <?= Html::a('All customer contragents', '#contragents', ['class' => 'btn btn-default']); ?>
        </div>
    </div>

    <div class="col-md-12">
        <div class="row">
<?php
    $form = \yii\bootstrap\ActiveForm::begin([
        'id' => 'customer-form',
        'action' => \yii\helpers\Url::toRoute(['edit', 'id' => $model->id]),
        'layout' => 'horizontal',
    ]);

    BackendWidget::begin([
        'icon' => 'user',
        'title' => Yii::t('app', 'Customer edit'),
        'footer' => \yii\helpers\Html::submitButton(Yii::t('app', 'Save'), ['class' => 'btn btn-success']),
    ]);

    $_jsTemplateResultFunc = <<< 'JSCODE'
function (data) {
    if (data.loading) return data.text;
    var tpl = '<div class="s2customer-result">' +
        '<strong>' + (data.username || '') + '</strong>' +
        '<div>' + (data.first_name || '') + ' (' + (data.email || '') + ')</div>' +
        '</div>';
    return tpl;
}
JSCODE;

    echo \app\backend\widgets\Select2Ajax::widget([
        'initialData' => [$model->user_id => null !== $model->user ? $model->user->username : 'Guest'],
        'model' => $model,
        'modelAttribute' => 'user_id',
        'form' => $form,
        'multiple' => false,
        'searchUrl' => \yii\helpers\Url::toRoute(['ajax-user']),
        'pluginOptions' => [
            'allowClear' => false,
            'escapeMarkup' => new \yii\web\JsExpression('function (markup) {return markup;}'),
            'templateResult' => new \yii\web\JsExpression($_jsTemplateResultFunc),
            'templateSelection' => new \yii\web\JsExpression('function (data) {return data.username || data.text;}'),
        ]
    ]);
    echo \app\modules\shop\widgets\Customer::widget([
        'viewFile' => 'customer/inherit_form',
        'form' => $form,
        'model' => $model,
        'additional' => [
            'hideHeader' => true,
        ],
    ]);
    BackendWidget::end();
    $form->end();

    /*******  CONTRAGENTS LIST  *******/
    echo Html::a('', '#', ['name' => 'contragents']);
    $searchModelConfig = [
        'model' => \app\modules\shop\models\Contragent::className(),
        'additionalConditions' => [
            ['customer_id' => $model->id],
        ],
    ];
    /** @var \app\components\SearchModel $searchModel */
    $searchModel = new \app\components\SearchModel($searchModelConfig);
    $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
    echo \kartik\dynagrid\DynaGrid::widget([
        'options' => [
            'id' => 'contragents-index-grid',
        ],
        'theme' => 'panel-default',
        'gridOptions' => [
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'hover' => true,
            'panel' => [
                'heading' => Html::tag('h3', $this->title, ['class' => 'panel-title']),
                'after' => Html::a(
                    \kartik\icons\Icon::show('plus') . Yii::t('app', 'Add'),
                    ['/shop/backend-contragent/create', 'customer' => $model->id, 'returnUrl' => \app\backend\components\Helper::getReturnUrl()],
                    ['class' => 'btn btn-success']
                ),
            ],
            'rowOptions' => function ($model, $key, $index, $grid) {
                /** @var \app\modules\shop\models\Contragent $model */
                if (null === $model->customer) {
                    return [
                        'class' => 'danger',
                    ];
                }
                return [];
            },
        ],
        'columns' => [
            'id',
            'type',
            [
                'label' => Yii::t('app', 'Additional information'),
                'value' => function ($model, $key, $index, $column) {
                    /** @var \app\modules\shop\models\Contragent $contragent */
                    /** @var \app\properties\AbstractModel $abstractModel */
                    $abstractModel = $model->getAbstractModel();
                    $abstractModel->setArrayMode(false);
                    $props = '';
                    foreach ($abstractModel->attributes() as $attr) {
                        $props .= '<li>' . $abstractModel->getAttributeLabel($attr) . ': ' . $abstractModel->$attr .'</li>';
                    }

                    return !empty($props) ? '<ul class="additional_information">'.$props.'</ul>' : '';
                },
                'format' => 'raw',
            ],
            [
                'class' => 'app\backend\components\ActionColumn',
                'buttons' =>  function($model, $key, $index, $parent) {
                    $result = [
                        [
                            'url' => '/shop/backend-contragent/edit',
                            'icon' => 'eye',
                            'class' => 'btn-info',
                            'label' => Yii::t('app','View'),
                        ],
                    ];
                    return $result;
                },
            ],
        ],
    ]);

    /*******  ORDERS TABLE  *******/
    echo Html::a('', '#', ['name' => 'orders']);
    $searchModelConfig = [
        'defaultOrder' => ['id' => SORT_DESC],
        'model' => \app\modules\shop\models\Order::className(),
        'partialMatchAttributes' => ['start_date', 'end_date', 'user_username'],
        'additionalConditions' => [
            ['customer_id' => $model->id],
        ],
    ];
    /** @var \app\components\SearchModel $searchModel */
    $searchModel = new \app\components\SearchModel($searchModelConfig);
    $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
    echo \kartik\dynagrid\DynaGrid::widget(
        [
            'options' => [
                'id' => 'orders-grid',
            ],
            'theme' => 'panel-default',
            'gridOptions' => [
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'hover' => true,
                'panel' => [
                    'heading' => Html::tag('h3', 'Customer orders', ['class' => 'panel-title']),
                ],
                'rowOptions' => function ($model, $key, $index, $grid) {
                    if ($model->is_deleted) {
                        return [
                            'class' => 'danger',
                        ];
                    }
                    return [];
                },
            ],
            'columns' => [
                [
                    'attribute' => 'id',
                ],
                [
                    'attribute' => 'user_username',
                    'label' => Yii::t('app', 'User'),
                    'value' => function ($model, $key, $index, $column) {
                        if ($model === null || $model->user === null) {
                            return null;
                        }
                        return $model->user->username;
                    },
                ],
                'start_date',
                'end_date',
                [
                    'attribute' => 'order_stage_id',
                    'filter' => Helper::getModelMap(\app\modules\shop\models\OrderStage::className(), 'id', 'name_short'),
                    'value' => function ($model, $key, $index, $column) {
                        if ($model === null || $model->stage === null) {
                            return null;
                        }
                        return $model->stage->name_short;
                    },
                ],
                [
                    'attribute' => 'shipping_option_id',
                    'filter' => Helper::getModelMap(\app\modules\shop\models\ShippingOption::className(), 'id', 'name'),
                    'value' => function ($model, $key, $index, $column) {
                        if ($model === null || $model->shippingOption === null) {
                            return null;
                        }
                        return $model->shippingOption->name;
                    },
                ],
                [
                    'attribute' => 'payment_type_id',
                    'filter' => Helper::getModelMap(\app\modules\shop\models\PaymentType::className(), 'id', 'name'),
                    'value' => function ($model, $key, $index, $column) {
                        if ($model === null || $model->paymentType === null) {
                            return null;
                        }
                        return $model->paymentType->name;
                    },
                ],
                'items_count',
                'total_price',
                [
                    'class' => 'app\backend\components\ActionColumn',
                    'buttons' =>  function($model, $key, $index, $parent) {
                        $result = [
                            [
                                'url' => '/shop/backend-order/view',
                                'icon' => 'eye',
                                'class' => 'btn-info',
                                'label' => Yii::t('app','View'),
                            ],
                        ];
                        return $result;
                    },
                ],
            ],
        ]
    );
?>
        </div>
    </div>
