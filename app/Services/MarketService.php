<?php

namespace App\Services;

use App\Traits\AuthorizesMarketRequests;
use App\Traits\ConsumesExternalServices;
use App\Traits\Responses;

class MarketService
{
    use ConsumesExternalServices, AuthorizesMarketRequests, Responses;

    //The URL to send the requests
    protected $baseUri;

    public function __construct()
    {
        //filename.service
        $this->baseUri = config('services.market.base_uri');
    }

    /**
     * Obtains the list of products from the API
     */
    public function getProducts()
    {
        return $this->makeRequest('GET', 'products');
    }

    /**
     * Obtains a products from the API
     */
    public function getProduct($id)
    {
        return $this->makeRequest('GET', "products/{$id}");
    }

    /**
     * Publish a product on the API
     */
    public function publishProduct($sellerId, $productData)
    {
        return $this->makeRequest(
            'POST',
            "sellers/{$sellerId}/products",
            [],//query params
            $productData,//body
            [],//headers
            $hasFile = true
        );
    }

    /**
     * Associate a product to the category
     */
    public function setProductCategory($productId, $categoryId)
    {
        return $this->makeRequest(
            'PUT',
            "products/{$productId}/categories/{$categoryId}"
        );
    }

    /**
     * Update an existing product
     */
    public function updateProduct($sellerId, $productId, $productData)
    {
        //as the product may have an image in the body, we cannot
        //send PUT or PATCH request because we cannot send raw data into these kinds of requests
        //So we need to send a post request, but we need to, let's say, spoof the method
        $productData['_method'] = 'PUT';

        return $this->makeRequest(
            'POST',
            "sellers/{$sellerId}/products/{$productId}",
            [],
            $productData,
            [],
            $hasFile = isset($productData['picture'])
        );
    }

    /**
     * Allows to purchase a product
     */
    public function purchaseProduct($productId, $buyerId, $quantity)
    {
        return $this->makeRequest(
            'POST',
            "products/{$productId}/buyers/{$buyerId}/transactions",
            [],
            ['quantity' => $quantity]
        );
    }

    /**
     * Obtains the list of categories from the API
     */
    public function getCategories()
    {
        return $this->makeRequest('GET', 'categories');
    }

    /**
     * Obtains the products from the API
     */
    public function getCategoryProducts($id)
    {
        return $this->makeRequest('GET', "categories/{$id}/products");
    }

    /**
     * Retrieve the user information from the API
     * @return stdClass
     */
    public function getUserInformation()
    {
        return $this->makeRequest('GET', 'users/me');
    }

    /**
     * Obtains the list of purchases
     */
    public function getPurchases($buyerId)
    {
        return $this->makeRequest('GET', "buyers/{$buyerId}/products");
    }

    /**
     * Obtains the list of publications
     */
    public function getPublications($sellerId)
    {
        return $this->makeRequest('GET', "sellers/{$sellerId}/products");
    }
}
