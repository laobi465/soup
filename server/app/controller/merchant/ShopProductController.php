<?php
declare (strict_types = 1);

namespace app\controller\merchant;

use app\BaseController;
use app\model\ShopProduct;
use app\model\Merchant;
use app\model\App;
use think\Request;

class ShopProductController extends BaseController
{
    public function index(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $status = $request->param('status', '');
        $category = $request->param('category', '');
        $keyword = $request->param('keyword', '');

        $query = ShopProduct::where('merchant_id', $merchant->id)->order('id', 'desc');

        if ($status !== '') {
            $query->where('status', intval($status));
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($keyword) {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $item) {
            $items[] = [
                'id' => $item->id,
                'name' => $item->name,
                'image' => $item->image,
                'description' => $item->description,
                'price' => $item->price,
                'stock' => $item->stock,
                'category' => $item->category,
                'app_id' => $item->app_id,
                'limit_per_user' => $item->limit_per_user,
                'limit_per_ip' => $item->limit_per_ip,
                'limit_per_device' => $item->limit_per_device,
                'status' => $item->status,
                'status_text' => $item->status_text,
                'created_at' => $item->created_at,
            ];
        }

        $data = [
            'list' => $items,
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'pageSize' => $list->listRows(),
        ];

        return success($data, '获取成功');
    }

    public function read(Request $request, $id)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $product = ShopProduct::where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->find();

        if (!$product) {
            return error('商品不存在', 404);
        }

        return success([
            'id' => $product->id,
            'merchant_id' => $product->merchant_id,
            'app_id' => $product->app_id,
            'name' => $product->name,
            'image' => $product->image,
            'description' => $product->description,
            'price' => $product->price,
            'stock' => $product->stock,
            'category' => $product->category,
            'limit_per_user' => $product->limit_per_user,
            'limit_per_ip' => $product->limit_per_ip,
            'limit_per_device' => $product->limit_per_device,
            'status' => $product->status,
            'status_text' => $product->status_text,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ], '获取成功');
    }

    public function save(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $data = $request->param();

        $name = $data['name'] ?? '';
        $appId = intval($data['app_id'] ?? 0);
        $price = floatval($data['price'] ?? 0);
        $stock = intval($data['stock'] ?? 0);

        if (empty($name)) {
            return error('商品名称不能为空', 400);
        }

        if ($appId <= 0) {
            return error('请选择关联应用', 400);
        }

        $app = App::where('id', $appId)->where('merchant_id', $merchant->id)->find();
        if (!$app) {
            return error('应用不存在', 404);
        }

        $product = new ShopProduct();
        $product->merchant_id = $merchant->id;
        $product->app_id = $appId;
        $product->name = $name;
        $product->image = $data['image'] ?? '';
        $product->description = $data['description'] ?? '';
        $product->price = $price;
        $product->stock = $stock;
        $product->category = $data['category'] ?? '';
        $product->limit_per_user = intval($data['limit_per_user'] ?? 0);
        $product->limit_per_ip = intval($data['limit_per_ip'] ?? 0);
        $product->limit_per_device = intval($data['limit_per_device'] ?? 0);
        $product->status = ShopProduct::STATUS_ONLINE;
        $product->save();

        return success(['id' => $product->id], '商品创建成功');
    }

    public function update(Request $request, $id)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $product = ShopProduct::where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->find();

        if (!$product) {
            return error('商品不存在', 404);
        }

        $data = $request->param();

        if (isset($data['name'])) {
            $product->name = $data['name'];
        }
        if (isset($data['image'])) {
            $product->image = $data['image'];
        }
        if (isset($data['description'])) {
            $product->description = $data['description'];
        }
        if (isset($data['price'])) {
            $product->price = floatval($data['price']);
        }
        if (isset($data['stock'])) {
            $product->stock = intval($data['stock']);
        }
        if (isset($data['category'])) {
            $product->category = $data['category'];
        }
        if (isset($data['limit_per_user'])) {
            $product->limit_per_user = intval($data['limit_per_user']);
        }
        if (isset($data['limit_per_ip'])) {
            $product->limit_per_ip = intval($data['limit_per_ip']);
        }
        if (isset($data['limit_per_device'])) {
            $product->limit_per_device = intval($data['limit_per_device']);
        }

        $product->save();

        return success(null, '商品更新成功');
    }

    public function updateStatus(Request $request, $id)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $product = ShopProduct::where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->find();

        if (!$product) {
            return error('商品不存在', 404);
        }

        $status = intval($request->param('status', 0));
        $product->status = $status;
        $product->save();

        return success(null, $status == 1 ? '商品已上架' : '商品已下架');
    }

    public function delete(Request $request, $id)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $product = ShopProduct::where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->find();

        if (!$product) {
            return error('商品不存在', 404);
        }

        $product->delete();

        return success(null, '商品删除成功');
    }
}
