<?php

namespace app\modules\shop\models;

use app;
use app\modules\config\models\BaseConfigurationModel;
use Yii;

/**
 * Class ConfigConfigurationModel represents configuration model for retrieving user input
 * in backend configuration subsystem.
 *
 * @package app\modules\shop\models
 */
class ConfigConfigurationModel extends BaseConfigurationModel
{
    /**
     * @var int How much products per page to show
     */
    public $productsPerPage = 15;

    /**
     * @var string How show products in category
     */
    public $listViewType = 'blockView';

    /**
     * @var int How much products allow to compare at once
     */
    public $maxProductsToCompare = 3;

    /**
     * @var bool Should we show and query for products of subcategories
     */
    public $showProductsOfChildCategories = true;

    /**
     * @var int How much products to show on search results page
     */
    public $searchResultsLimit = 9;

    /**
     * @var boolean Possible to search generated products
     */
    public $allowSearchGeneratedProducts = 0;

    /**
     * @var bool Show delete order in backend
     */
    public $deleteOrdersAbility = false;

    /**
     * @var bool Filtration works only on parent products but not their children
     */
    public $filterOnlyByParentProduct = true;

    /**
     * @var int How much last viewed products ID's to store in session
     */
    public $maxLastViewedProducts = 9;

    /**
     * @var bool Allow to add same product in the order
     */
    public $allowToAddSameProduct = 0;

    /**
     * @var bool Count only unique products in the order
     */
    public $countUniqueProductsOnly = 1;

    /**
     * @var bool Count children products in the order
     */
    public $countChildrenProducts = 1;

    /**
     * @var int Default measure ID
     */
    public $defaultMeasureId = 1;

    /**
     * @var int Final order stage leaf
     */
    public $finalOrderStageLeaf = 0;

    /**
     * @var int Default filter for Orders by stage in backend
     */
    public $defaultOrderStageFilterBackend = 0;

    /***
     * @var bool registration Guest User In Cart as new user and send data on e-mail
     */
    public $registrationGuestUserInCart = 0;
    /**
     * @var int Show deleted orders in backend or not
     */
    public $showDeletedOrders = 0;

    /**
     * @var array
     */
    public $ymlConfig = [];

    /**
     * @var bool Show filter links in breadcrumbs
     */
    public $showFiltersInBreadcrumbs = false;

    /**
     * @var bool Use method ceilQuantity of Measure model
     */
    public $useCeilQuantity = true;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [
                [
                    'productsPerPage',
                    'maxProductsToCompare',
                    'searchResultsLimit',
                ],
                'integer',
                'min' => 1,
            ],
            [
                'listViewType',
                'in',
                'range' => [
                    'listView',
                    'blockView'
                ],
                'strict' => true
            ],
            [
                [
                    'maxLastViewedProducts',
                    'finalOrderStageLeaf',
                    'defaultOrderStageFilterBackend',
                ],
                'integer',
            ],
            [
                [
                    'productsPerPage',
                    'maxProductsToCompare',
                    'searchResultsLimit',
                ],
                'filter',
                'filter' => 'intval',
            ],
            [
                [
                    'productsPerPage',
                    'maxProductsToCompare',
                    'searchResultsLimit',
                    'maxLastViewedProducts',
                ],
                'required',
            ],
            [
                [
                    'showProductsOfChildCategories',
                    'deleteOrdersAbility',
                    'filterOnlyByParentProduct',
                    'showDeletedOrders',
                    'showFiltersInBreadcrumbs',
                ],
                'boolean',
            ],
            [
                [
                    'showProductsOfChildCategories',
                    'deleteOrdersAbility',
                    'filterOnlyByParentProduct',
                ],
                'filter',
                'filter' => 'boolval',
            ],
            [
                [
                    'allowToAddSameProduct',
                    'countUniqueProductsOnly',
                    'countChildrenProducts',
                    'allowSearchGeneratedProducts',
                    'registrationGuestUserInCart'
                ],
                'boolean'
            ],
            [['defaultMeasureId'], 'integer'],
            [
                ['ymlConfig'],
                function ($attribute, $params) {
                    if (!is_array($this->$attribute)) {
                        $this->$attribute = [];
                    }
                }
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'allowToAddSameProduct' => Yii::t('app', 'Allow to add same product'),
            'countUniqueProductsOnly' => Yii::t('app', 'Count unique products only'),
            'countChildrenProducts' => Yii::t('app', 'Count children products'),
            'defaultMeasureId' => Yii::t('app', 'Default measure'),
            'registrationGuestUserInCart' => Yii::t('app', 'Registration Guest User In Cart'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function defaultValues()
    {
        /** @var app\modules\shop\ShopModule $module */
        $module = $this->getModuleInstance();

        $attributes = array_keys($this->getAttributes());
        foreach ($attributes as $attribute) {
            $this->{$attribute} = $module->{$attribute};
        }
    }

    /**
     * Returns array of module configuration that should be stored in application config.
     * Array should be ready to merge in app config.
     * Used both for web only.
     *
     * @return array
     */
    public function webApplicationAttributes()
    {
        $attributes = $this->getAttributes();
        return [
            'modules' => [
                'shop' => $attributes,
            ],
        ];
    }

    /**
     * Returns array of module configuration that should be stored in application config.
     * Array should be ready to merge in app config.
     * Used both for console only.
     *
     * @return array
     */
    public function consoleApplicationAttributes()
    {
        return [
            'modules' => [
                'shop' => [
                    'ymlConfig' => $this->ymlConfig,
                ]
            ]
        ];
    }

    /**
     * Returns array of module configuration that should be stored in application config.
     * Array should be ready to merge in app config.
     * Used both for web and console.
     *
     * @return array
     */
    public function commonApplicationAttributes()
    {
        return [];
    }

    /**
     * Returns array of key=>values for configuration.
     *
     * @return mixed
     */
    public function keyValueAttributes()
    {
        return [];
    }

    /**
     * Returns array of aliases that should be set in common config
     * @return array
     */
    public function aliases()
    {
        return [
            '@shop' => dirname(__FILE__) . '/../',
            '@category' => '/shop/product/list',
            '@product' => '/shop/product/show',
        ];
    }
}