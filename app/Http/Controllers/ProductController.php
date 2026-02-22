<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
  public function index()
  {
    return response()->json(Product::all(), 200);
  }

  // Tambah barang baru
  public function store(Request $request)
  {
    $this->validate($request, [
      'product_name' => 'required',
      'sku' => 'required|unique:products',
      'price' => 'required|numeric',
      'stock' => 'required|numeric',
    ]);

    $product = Product::create($request->all());
    return response()->json(['message' => 'Barang berhasil ditambah', 'data' => $product], 201);
  }

  // Update Data Produk
  public function update(Request $request, $id)
  {
    $this->validate($request, [
      'product_name' => 'required',
      'price'        => 'required|numeric',
      'stock'        => 'required|numeric',
    ]);

    $product = Product::find($id);
    if (!$product) {
      return response()->json(['message' => 'Barang tidak ditemukan'], 404);
    }

    $product->update($request->all());
    return response()->json(['message' => 'Data berhasil diubah', 'data' => $product], 200);
  }

  // Hapus Data Produk
  public function destroy($id)
  {
    $product = Product::find($id);
    if (!$product) {
      return response()->json(['message' => 'Barang tidak ditemukan'], 404);
    }

    $product->delete();
    return response()->json(['message' => 'Barang berhasil dihapus'], 200);
  }
}