<?php
// +----------------------------------------------------------------------
// | 产品资源控制器
// +----------------------------------------------------------------------

namespace app\controller;

class Product extends Base
{
    /**
     * 获取公共查询字段
     * @return string 查询字段
     */
    private function getFields()
    {
        $fields = 'goods_id as id, goods_name as name, goods_small_logo as thumbnail, goods_big_logo as picture, goods_price as price, goods_number as amount, hot_mumber as sales';

        if (is_include('introduce')) {
            $fields .= ', goods_introduce as introduce';
        }

        if (is_include('category')) {
            $fields .= ', category.cat_id as category_id, category.cat_name as category_name';
            $fields .= ', primary.cat_id as primary_category_id, primary.cat_name as primary_category_name';
            $fields .= ', secondary.cat_id as secondary_category_id, secondary.cat_name as secondary_category_name';
        } else {
            $fields .= ', goods.cat_id as category_id, goods.cat_one_id as primary_category_id, goods.cat_two_id as secondary_category_id';
        }

        return $fields;
    }

    /**
     * 获取公共查询对象
     * @return Query 查询对象
     */
    private function getQuery($cat_id = null)
    {
        $query = model('Goods')::alias('goods');

        if (is_include('category')) {
            $query
                ->join('Category category', 'category.cat_id = goods.cat_id')
                ->join('Category primary', 'primary.cat_id = goods.cat_one_id')
                ->join('Category secondary', 'secondary.cat_id = goods.cat_two_id');
        }

        // 如果有分类筛选
        if (!empty($cat_id)) {
            $level = model('Category')::where('cat_id', $cat_id)->where('cat_deleted', 0)->value('cat_level');
            if ($level === null) abort(404, 'Not Found');

            switch ($level) {
                case 0:
                    $query->where('goods.cat_one_id', $cat_id);
                    break;
                case 1:
                    $query->where('goods.cat_two_id', $cat_id);
                    break;
                case 2:
                    $query->where('goods.cat_id', $cat_id);
                    break;

                default:
                    abort(404, 'Not Found');
                    break;
            }
        }

        $search = input('get.q/s');
        if (!empty($search)) {
            $search = str_replace(' ', '%', $search);
            $query->where('goods.goods_name', 'like', '%' . $search . '%');
        }

        return $query
            ->where('goods.is_del', 0)
            ->where('goods.goods_state', 2);
    }

    /**
     * 转换输出结果
     * @param  array $records 查询结果集
     * @return array          转换后的结果
     */
    private function parseResult($records)
    {
        $result = [];
        foreach ($records as $row) {
            $item['id'] = $row['id'];
            $item['name'] = $row['name'];
            $item['thumbnail'] = $row['thumbnail'];
            $item['picture'] = $row['picture'];
            $item['price'] = $row['price'];
            $item['amount'] = $row['amount'];
            $item['sales'] = $row['sales'];

            if (isset($row['introduce'])) {
                $item['introduce'] = $row['introduce'];
            }

            if (isset($row['category_name'])) {
                $item['category'] = [
                    'id' => $row['category_id'],
                    'name' => $row['category_name'],
                    'parent' => [
                        'id' => $row['primary_category_id'],
                        'name' => $row['primary_category_name'],
                        'parent' => [
                            'id' => $row['secondary_category_id'],
                            'name' => $row['secondary_category_name']
                        ]
                    ]
                ];
            } else {
                $item['category'] = [
                    'id' => $row['category_id'],
                    'parent' => [
                        'id' => $row['primary_category_id'],
                        'parent' => [
                            'id' => $row['secondary_category_id']
                        ]
                    ]
                ];
            }

            $result[] = $item;
        }
        return $result;
    }

    /**
     * 随机推荐（猜你喜欢）
     *
     * @return \think\Response
     */
    private function like($cat_id)
    {
        $pagination = get_pagination_params(5, 20);

        $records = $this->getQuery($cat_id)
            ->limit($pagination['limit'])
            ->order('rand()')
            ->field($this->getFields())
            ->select();

        return json($this->parseResult($records));
    }

    /**
     * 数据资源列表
     */
    private function list($cat_id)
    {
        $pagination = get_pagination_params(20, 100);

        $query = $this->getQuery($cat_id);

        // 查询总条数
        $pagination['total_count'] = (clone $query)->count();

        // 查询数据
        $records = $query
            ->limit($pagination['offset'], $pagination['limit'])
            ->order($pagination['sort'])
            ->field($this->getFields())
            ->select();

        return json($this->parseResult($records))->header(get_pagination_header($pagination));
    }

    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $filter = get_filter_params();

        $cat_id = 0;
        if (isset($filter['category']) || isset($filter['cat'])) {
            $cat_id = intval(isset($filter['category']) ? $filter['category'] : $filter['cat']);
        }

        if (input('get.type/s') === 'like') {
            // 猜你喜欢
            return $this->like($cat_id);
        }

        return $this->list($cat_id);
    }

    /**
     * 根据分类显示资源列表
     *
     * @return \think\Response
     */
    public function category($id)
    {
        return $this->list($id);
    }

    /**
     * 显示指定的资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function read($id)
    {
        $id = intval($id);
        if (empty($id)) {
            abort(400, 'Bad Request');
        }

        // 查询商品记录
        $records = $this->getQuery()
            ->where('goods.goods_id', $id)
            ->limit(1)
            ->field($this->getFields())
            ->select();

        if (!count($records)) {
            abort(404, 'Not Found');
        }

        $result = $this->parseResult($records)[0];

        if (is_include('pictures')) {
            $result['pictures'] = model('GoodsPics')::where('goods_id', $result['id'])
                ->field('pics_big as large, pics_mid as middle, pics_sma as small')
                ->select();
        }

        return json($result);
    }
}
