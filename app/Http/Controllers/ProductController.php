<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MarketService;

class ProductController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(MarketService $marketService)
    {
        $this->middleware('auth')->except(['showProduct']);

        parent::__construct($marketService);
    }

    /**
     * Show the details of a product.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function showProduct($title, $id)
    {
        $product = $this->marketService->getProduct($id);

        return view('products.show')
            ->with([
                'product' => $product,
            ]);
    }

    /**
     * Enable to purchase a products form the API
     */
    public function purchaseProduct(Request $request, $title, $id)
    {
        $this->marketService->purchaseProduct($id, $request->user()->service_id, 1);

        return redirect()
            ->route('products.show',
                [
                    'title' => $title,
                    'id' => $id,
                ])
            ->with('succes', ['Product purchased']);
    }

    /**
     * Show the form to create a product on the API
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function showPublishProductForm()
    {
        $categories = $this->marketService->getCategories();

        return view('products.publish')
            ->with([
                'categories' => $categories,
            ]);
    }

    /**
     * Create the product on the API
     */
    public function publishProduct(Request $request)
    {
        $rules = [
            'title' => 'required',
            'details' => 'required',
            'stock' => 'required|min:1',
            'picture' => 'required|image',
            'category' => 'required',
        ];

        $productData = $this->validate($request, $rules);//validate

        $productData['picture'] = fopen($request->picture->path(), 'r');//read mode

        $productData = $this->marketService->publishProduct($request->user()->service_id, $productData);

        $this->marketService->setProductCategory($productData->identifier, $request->category);//attaching a category

        $this->marketService->updateProduct($request->user()->service_id, $productData->identifier, ['situation' => 'available']);//changing the status to available

        return redirect()
            ->route('products.show',
                [
                    'title' => $productData->title,
                    'id' => $productData->identifier,
                ])
            ->with('success', ['Product created successfully']);
    }
}
