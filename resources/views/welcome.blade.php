@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-2">

            <div class="row">
                <div class="col">
                    <h2>Categories</h2>
                </div>
            </div>

            <div class="row">
                <div class="col">
                    <ul class="list-group">
                        @foreach($categories as $category)
                        <a href="{{route('categories.products.show', ['title' => $category->title, 'id' => $category->identifier])}}" class="list-group-item">
                            {{$category->title}}
                        </a>
                        @endforeach
                    </ul>
                </div>
            </div>

        </div>

        <div class="col">

            <div class="row">
                <div class="col">
                    <h2 class="display-2">Products</h2>
                </div>
            </div>

            <div class="row">
                @foreach($products as $product)
                    <div class="col-4">
                        <a href="{{route('products.show', ['title' => $product->title, 'id' => $product->identifier])}}">
                            <div class="card">
                                <img src="{{$product->picture}}" class="card-img-top">
                                <div class="card-body">
                                    <h5 class="card-title">{{$product->title}} ({{$product->stock}})</h5>
                                    <p class="card-text">{{$product->details}}</p>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</div>
@endsection
