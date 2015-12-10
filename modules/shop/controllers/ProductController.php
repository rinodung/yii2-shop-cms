<?php

namespace app\modules\shop\controllers;

use app\components\Controller;
use app\extensions\DefaultTheme\components\BaseWidget;
use app\extensions\DefaultTheme\models\ThemeActiveWidgets;
use app\extensions\DefaultTheme\models\ThemeWidgets;
use app\extensions\DefaultTheme\widgets\FilterSets\Widget;
use app\models\PropertyStaticValues;
use app\modules\core\helpers\EventTriggeringHelper;
use app\modules\shop\events\ProductPageShowed;
use app\modules\shop\models\Category;
use app\models\Object;
use app\modules\shop\models\Product;
use app\models\Search;
use app\traits\DynamicContentTrait;
use devgroup\TagDependencyHelper\ActiveRecordHelper;
use Yii;
use yii\caching\TagDependency;
use yii\data\Pagination;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

class ProductController extends Controller
{
    use DynamicContentTrait;

    /**
     * Products listing by category with filtration support.
     *
     * @return string
     * @throws \Exception
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     */
    public function actionList()
    {

        $request = Yii::$app->request;

        if (null === $request->get('category_group_id')) {
            throw new NotFoundHttpException;
        }

        if (null === $object = Object::getForClass(Product::className())) {
            throw new ServerErrorHttpException('Object not found.');
        }

        $category_group_id = intval($request->get('category_group_id', 0));

        $title_append = $request->get('title_append', '');
        if (!empty($title_append)) {
            $title_append = is_array($title_append) ? implode(' ', $title_append) : $title_append;
            unset($_GET['title_append']);
        }

        $values_by_property_id = $request->get('properties', []);
        if (!is_array($values_by_property_id)) {
            $values_by_property_id = [$values_by_property_id];
        }

        if (Yii::$app->request->isPost && isset($_POST['properties'])) {
            if (is_array($_POST['properties'])) {
                foreach ($_POST['properties'] as $key => $value) {
                    if (isset($values_by_property_id[$key])) {
                        $values_by_property_id[$key] = array_unique(
                            ArrayHelper::merge(
                                $values_by_property_id[$key],
                                $value
                            )
                        );
                    } else {
                        $values_by_property_id[$key] = array_unique($value);
                    }
                }
            }
        }

        $selected_category_ids = $request->get('categories', []);
        if (!is_array($selected_category_ids)) {
            $selected_category_ids = [$selected_category_ids];
        }

        if (null !== $selected_category_id = $request->get('last_category_id')) {
            $selected_category_id = intval($selected_category_id);
        }

        $result = Product::filteredProducts(
            $category_group_id,
            $values_by_property_id,
            $selected_category_id,
            false,
            null,
            true,
            false
        );
        /** @var Pagination $pages */
        $pages = $result['pages'];
        if (Yii::$app->response->is_prefiltered_page) {
            $pages->route = '/' . Yii::$app->request->pathInfo;
            $pages->params = [

            ];
        }
        $allSorts = $result['allSorts'];
        $products = $result['products'];

        // throw 404 if we are at filtered page without any products
        if ( !empty($values_by_property_id) && empty($products)) {
            throw new NotFoundHttpException();
        }

        if (null !== $selected_category = $selected_category_id) {
            if ($selected_category_id > 0) {
                if (null !== $selected_category = Category::findById($selected_category_id, null)) {
                    if (!empty($selected_category->meta_description)) {
                        $this->view->registerMetaTag(
                            [
                                'name' => 'description',
                                'content' => $selected_category->meta_description,
                            ],
                            'meta_description'
                        );
                    }
                    $this->view->title = $selected_category->title;
                }
            }
        }
        if (is_null($selected_category) || !$selected_category->active) {
            throw new NotFoundHttpException;
        }

        if (!empty($title_append)) {
            $this->view->title .= " " . $title_append;
        }

        $this->view->blocks['h1'] = $selected_category->h1;
        $this->view->blocks['announce'] = $selected_category->announce;
        $this->view->blocks['content'] = $selected_category->content;

        $this->loadDynamicContent($object->id, 'shop/product/list', $request->get());

        $params = [
            'model' => $selected_category,
            'selected_category' => $selected_category,
            'selected_category_id' => $selected_category_id,
            'selected_category_ids' => $selected_category_ids,
            'values_by_property_id' => $values_by_property_id,
            'products' => $products,
            'object' => $object,
            'category_group_id' => $category_group_id,
            'pages' => $pages,
            'title_append' => $title_append,
            'selections' => $request->get(),
            'breadcrumbs' => $this->buildBreadcrumbsArray($selected_category, null, $values_by_property_id),
            'allSorts' => $allSorts,
        ];
        $viewFile = $this->computeViewFile($selected_category, 'list');

        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;

            $content = $this->renderAjax(
                $viewFile,
                $params
            );
            $filters = '';
            $activeWidgets = ThemeActiveWidgets::getActiveWidgets();
            foreach ($activeWidgets as $activeWidget) {
                if ($activeWidget->widget->widget == Widget::className()) {
                    /** @var ThemeWidgets $widgetModel */
                    $widgetModel = $activeWidget->widget;
                    /** @var BaseWidget $widgetClassName */
                    $widgetClassName =  $widgetModel->widget;
                    $widgetConfiguration = Json::decode($widgetModel->configuration_json, true);
                    if (!is_array($widgetConfiguration)) {
                        $widgetConfiguration = [];
                    }
                    $activeWidgetConfiguration = Json::decode($activeWidget->configuration_json, true);
                    if (!is_array($activeWidgetConfiguration)) {
                        $activeWidgetConfiguration  = [];
                    }
                    $config = ArrayHelper::merge($widgetConfiguration, $activeWidgetConfiguration);
                    $config['themeWidgetModel'] = $widgetModel;
                    $config['partRow'] = $activeWidget->part;
                    $config['activeWidget'] = $activeWidget;
                    $filters = $widgetClassName::widget($config);
                }
            }
            return [
                'content' => $content,
                'filters' => $filters,
                'title' => $this->view->title,
                'url' => Url::to(
                    [
                        '/shop/product/list',
                        'last_category_id' => $selected_category_id,
                        'properties' => $values_by_property_id
                    ]
                ),
            ];
        } else {
            return $this->render(
                $viewFile,
                $params
            );
        }
    }

    /**
     * Product page view
     *
     * @param null $model_id
     * @return string
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     */
    public function actionShow($model_id = null)
    {
        if (null === $object = Object::getForClass(Product::className())) {
            throw new ServerErrorHttpException('Object not found.');
        }

        $cacheKey = 'Product:' . $model_id;
        if (false === $product = Yii::$app->cache->get($cacheKey)) {
            if (null === $product = Product::findById($model_id)) {
                throw new NotFoundHttpException;
            }
            Yii::$app->cache->set(
                $cacheKey,
                $product,
                86400,
                new TagDependency(
                    [
                        'tags' => [
                            ActiveRecordHelper::getObjectTag(Product::className(), $model_id),
                        ]
                    ]
                )
            );
        }

        $request = Yii::$app->request;

        $values_by_property_id = $request->get('properties', []);
        if (!is_array($values_by_property_id)) {
            $values_by_property_id = [$values_by_property_id];
        }

        $selected_category_id = $request->get('last_category_id');

        $selected_category_ids = $request->get('categories', []);
        if (!is_array($selected_category_ids)) {
            $selected_category_ids = [$selected_category_ids];
        }

        $category_group_id = intval($request->get('category_group_id', 0));

        // trigger that we are to show product to user!
        // wow! such product! very events!
        $specialEvent = new ProductPageShowed([
            'product_id' => $product->id,
        ]);
        EventTriggeringHelper::triggerSpecialEvent($specialEvent);

        if (!empty($product->meta_description)) {
            $this->view->registerMetaTag(
                [
                    'name' => 'description',
                    'content' => $product->meta_description,
                ],
                'meta_description'
            );
        }

        $selected_category = ($selected_category_id > 0) ? Category::findById($selected_category_id) : null;

        $this->view->title = $product->title;
        $this->view->blocks['announce'] = $product->announce;
        $this->view->blocks['content'] = $product->content;
        $this->view->blocks['title'] = $product->title;


        return $this->render(
            $this->computeViewFile($product, 'show'),
            [
                'model' => $product,
                'category_group_id' => $category_group_id,
                'values_by_property_id' => $values_by_property_id,
                'selected_category_id' => $selected_category_id,
                'selected_category' => $selected_category,
                'selected_category_ids' => $selected_category_ids,
                'object' => $object,
                'breadcrumbs' => $this->buildBreadcrumbsArray($selected_category, $product)
            ]
        );
    }

    /**
     * Search handler
     * @return array
     * @throws ForbiddenHttpException
     */
    public function actionSearch()
    {
        $headers = Yii::$app->response->getHeaders();
        $headers->set('X-Robots-Tag', 'none');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('X-Content-Type-Options', 'nosniff');
        if (!Yii::$app->request->isAjax) {
            throw new ForbiddenHttpException();
        }
        $model = new Search();
        $model->load(Yii::$app->request->get());
        $cacheKey = 'ProductSearchIds: ' . $model->q;
        $ids = Yii::$app->cache->get($cacheKey);
        if ($ids === false) {
            $ids = ArrayHelper::merge(
                $model->searchProductsByDescription(),
                $model->searchProductsByProperty()
            );
            Yii::$app->cache->set(
                $cacheKey,
                $ids,
                86400,
                new TagDependency(
                    [
                        'tags' => ActiveRecordHelper::getCommonTag(Product::className()),
                    ]
                )
            );
        }

        /** @var \app\modules\shop\ShopModule $module */
        $module = Yii::$app->modules['shop'];

        $pages = new Pagination(
            [
                'defaultPageSize' => $module->searchResultsLimit,
                'forcePageParam' => false,
                'totalCount' => count($ids),
            ]
        );
        $cacheKey .= ' : ' . $pages->offset;
        $products = Yii::$app->cache->get($cacheKey);
        if ($products === false) {
            $products = Product::find()->where(
                [
                    'in',
                    '`id`',
                    array_slice(
                        $ids,
                        $pages->offset,
                        $pages->limit
                    )
                ]
            )->addOrderBy('sort_order')->with('images')->all();
            Yii::$app->cache->set(
                $cacheKey,
                $products,
                86400,
                new TagDependency(
                    [
                        'tags' => ActiveRecordHelper::getCommonTag(Product::className()),
                    ]
                )
            );
        }
        Yii::$app->response->format = Response::FORMAT_JSON;
        return [
            'view' => $this->renderAjax(
                'search',
                [
                    'model' => $model,
                    'pages' => $pages,
                    'products' => $products,
                ]
            ),
            'totalCount' => count($ids),
        ];

    }

    /**
    * This function build array for widget "Breadcrumbs"
    * @param Category $selCat - model of current category
    * @param Product|null $product - model of product, if current page is a page of product
    * @param array $properties - array of properties and static values
    * Return an array for widget or empty array
    */
    private function buildBreadcrumbsArray($selCat, $product = null, $properties = [])
    {
        if ($selCat === null) {
            return [];
        }

        // init
        $breadcrumbs = [];
        if ($product !== null) {
            $crumbs[$product->slug] = !empty($product->breadcrumbs_label) ? $product->breadcrumbs_label : '';
        }
        $crumbs[$selCat->slug] = $selCat->breadcrumbs_label;

        // get basic data
        $parent = $selCat->parent_id > 0 ? Category::findById($selCat->parent_id) : null;
        while ($parent !== null) {
            $crumbs[$parent->slug] = $parent->breadcrumbs_label;
            $parent = $parent->parent;
        }

        // build array for widget
        $url = '';
        $crumbs = array_reverse($crumbs, true);
        foreach ($crumbs as $slug => $label) {
            $url .= '/' . $slug;
            $breadcrumbs[] = [
                'label' => $label,
                'url' => $url
            ];
        }
        if (is_null($product) && $this->module->showFiltersInBreadcrumbs && !empty($properties)) {
            $route = [
                '@category',
                'last_category_id' => $selCat->id,
                'category_group_id' => $selCat->category_group_id,
            ];
            $params = [];
            foreach ($properties as $propertyId => $propertyStaticValues) {
                $localParams = $params;
                foreach ($propertyStaticValues as $propertyStaticValue) {
                    $psv = PropertyStaticValues::findById($propertyStaticValue);
                    if (is_null($psv)) {
                        continue;
                    }
                    $localParams[$propertyId][] = $propertyStaticValue;
                    $breadcrumbs[] = [
                        'label' => $psv['name'],
                        'url' => array_merge($route, ['properties' => $localParams]),
                    ];
                }
                $params[$propertyId] = $propertyStaticValues;
            }
        }
        unset($breadcrumbs[count($breadcrumbs) - 1]['url']); // last item is not a link

        if (isset(Yii::$app->response->blocks['breadcrumbs_label'])) {
            // last item label rewrited through prefiltered page or something similar
            $breadcrumbs[count($breadcrumbs) - 1]['label'] = Yii::$app->response->blocks['breadcrumbs_label'];
        }

        return $breadcrumbs;
    }
}
