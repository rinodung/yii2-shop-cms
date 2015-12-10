<?php
namespace app\modules\shop\widgets;

use yii\db\Query;
use yii\helpers\ArrayHelper;
use Yii;


class PriceSliderRangeWidget extends SliderRangeWidget
{

    public $attributeName = 'Цена';

    public $minAttribute = 'price_min';
    public $maxAttribute = 'price_max';
    public $changeFlagAttribute = 'price_change_flag';

    public $categoryId;


    public function init()
    {
        $cacheKey = 'priceRangeCategory' . $this->categoryId;

        if (!$data = Yii::$app->cache->get($cacheKey)) {
            $data = (new Query())->select('MIN(product.price) as min_price, MAX(product.price) as max_price')
                ->from(['product', 'product_category'])
                ->where('product.id = product_category.object_model_id')
                ->andWhere(
                    [
                        'product.active' => 1,
                        'product_category.category_id' => $this->categoryId
                    ]
                )->one();

            if ($data) {
                Yii::$app->cache->set($cacheKey, $data, 86400);
            }

        }
        if ($data && isset($data['min_price']) && isset($data['max_price'])) {
            $this->minValue = $data['min_price'];
            $this->maxValue = $data['max_price'];
            $get = ArrayHelper::merge(Yii::$app->request->get(), Yii::$app->request->post());

            if (isset($get[$this->minAttribute]) && is_numeric($get[$this->minAttribute])) {
                $this->changeFlagDefaultValue = 1;
                $this->minValueNow = $get[$this->minAttribute];
            } else {
                $this->minValueNow = $this->minValue;
            }

            if (isset($get[$this->maxAttribute]) && is_numeric($get[$this->maxAttribute])) {
                $this->changeFlagDefaultValue = 1;
                $this->maxValueNow = $get[$this->maxAttribute];
            } else {
                $this->maxValueNow = $this->maxValue;
            }
        }
        return parent::init();

    }


}