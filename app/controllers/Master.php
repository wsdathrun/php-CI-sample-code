<?php
/**
 * Created by PhpStorm.
 * User: philips
 * Date: 16/12/6
 * Time: 下午5:59
 */

defined('BASEPATH') OR exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
require APPPATH . '/libraries/REST_Controller.php';

class Master extends REST_Controller
{
    private $_patternRes = array();
    function __construct()
    {
        parent::__construct();
        $this->load->model('Utility_model','utility');
        $this->load->model('Master_model','master');
        $this->load->add_package_path(APPPATH.'third_party');
        $this->load->library('PHPExcel');
        $this->load->library('PHPExcel/IOFactory');
        $this->load->helper(array('form', 'url'));

    }

    /**
     * 礼盒维护新增接口
     */
    public function addGift_post()
    {
        $code = 1;
        $message = '礼盒创建成功';
        $data = array();
        header("Access-Control-Allow-Origin: *");
        $boxSku = trim($this->post('sku'));
        $name = trim($this->post('name'));
        $comment = $this->post('comment');
        $prodType = $this->post('prodType');
        $operator = $this->post('operator');
        $prodItemDetail = $this->post('prodItemDetail');
        $prodItemDetail = json_decode($prodItemDetail,true);

        // 验证参数
        if(!$this->_checkBoxParam($boxSku,$name,$prodType,$operator,$prodItemDetail))
        {
            return;
        }

        // 处理
        if(!$this->_processAddGift($boxSku,$name,$prodType,$operator,$comment,$prodItemDetail))
        {
            return;
        }

        $out = array(
            'code'  =>  $code,
            'msg'   =>  $message,
            'data'  =>  $data,
        );
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    /**
     * 验证礼盒创建参数
     * @param $boxSku
     * @param $name
     * @param $prodType
     * @param $operator
     * @param $prodItemDetail
     * @return bool
     */
    protected function _checkBoxParam($boxSku,$name,$prodType,$operator,$prodItemDetail)
    {
        // 参数sku验证
        if(is_null($boxSku))
        {
            $this->returnError('礼盒SKU不能为空');
            return false;
        }
        else if(!$this->extend->validateSku($boxSku))
        {
            $this->returnError('礼盒SKU只能由数字、字母和符号“-”“_”组成');
            return false;
        }
        else if($this->utility->select_row('box_master','sku',array('sku' => $boxSku)))
        {
            $this->returnError('该礼盒SKU已经存在,不能重复');
            return false;
        }

        // 参数sku验证
        if(is_null($name))
        {
            $this->returnError('礼盒名称不能为空');
            return false;
        }
        else if($this->utility->select_row('box_master','sku',array('name' => $name)))
        {
            $this->returnError('该礼盒名称已经存在,不能重复');
            return false;
        }

        // 参数录入方式验证
        if(($prodType != 'B') && ($prodType != 'S'))
        {
            $this->returnError("录入方式不合法或者为空");
            return false;
        }

        // 参数创建人验证
        if(is_null($operator))
        {
            $this->returnError('创建人不能为空');
            return false;
        }

        // 参数组装项验证
        if(is_null($prodItemDetail))
        {
            $this->returnError('礼盒组装项不能为空');
            return false;
        }
        return true;
    }

    /**
     * 处理礼盒创建
     * @param $boxSku
     * @param $name
     * @param $prodType
     * @param $operator
     * @param $comment
     * @param $prodItemDetail
     * @return bool
     */
    protected function _processAddGift($boxSku,$name,$prodType,$operator,$comment,$prodItemDetail)
    {
        // 事务处理
        $this->db->trans_start();

        // 创建礼盒主数据
        try
        {
            $boxId = $this->_createBoxMaster($boxSku,$name,$comment,$operator);
        }
        catch(Exception $e)
        {
            $this->returnError('礼盒主数据创建失败。'.$e->getMessage());
            return false;
        }

        // 创建礼盒组装项数据
        try
        {
            $this->_createBoxItem($boxId,$prodItemDetail,$prodType);
        }
        catch(Exception $e)
        {
            $this->returnError('礼盒组装项数据创建失败。'.$e->getMessage());
            return false;
        }

        // 创建礼盒组合数据
        try
        {
            $this->_createBoxPattern($boxId);
        }
        catch(Exception $e)
        {
            $this->returnError('礼盒组合数据创建失败。'.$e->getMessage());
            return false;
        }

        $this->db->trans_complete();
        return true;
    }

    /**
     * 创建礼盒主数据
     * @param $boxSku
     * @param $name
     * @param $comment
     * @param $operator
     * @return mixed
     */
    protected function _createBoxMaster($boxSku,$name,$comment,$operator)
    {
        $boxMasterData = array(
            'sku'           =>  $boxSku,
            'name'          =>  $name,
            'comment'       =>  $comment,
            'creater'       =>  $operator,
            'created_at'    =>  date('Y-m-d H:i:s',time()),
        );
        return $this->utility->insert('box_master',$boxMasterData);
    }

    /**
     * 创建组装项数据
     * @param $boxId
     * @param $prodItemDetail
     * @param $prodType
     * @return bool
     * @throws Exception
     */
    protected function _createBoxItem($boxId,$prodItemDetail,$prodType)
    {

        foreach($prodItemDetail as $ik=>$iv)
        {
            foreach($iv as $k=>$v)
            {
                switch($prodType)
                {
                    case 'B':
                        $keyName = 'barcode';
                        break;
                    case 'S':
                        $keyName = 'sku';
                        break;
                    default:
                        break;
                }
                $productId = $this->utility->getProductId($v[$keyName],$prodType);
                // 商品不存在
                if(!$productId)
                {
                    throw new Exception('系统中不存在商品'.$v[$keyName]);
                    return false;
                }
                else{
                    $this->master->addBoxItem($boxId,$ik,$productId);
                }
            }
        }
        return true;
    }

    /**
     * 创建礼盒组合数据
     * @param $boxId
     * @return bool
     */
    protected function _createBoxPattern($boxId)
    {
        $result = true;
        $groups = $this->master->getBoxItemGroups($boxId);
        $this->_patternRes = array();
        $this->_createPatternRecursive($groups);
        $this->_insertPatternData($boxId);
        return $result;
    }

    /**
     * 递归生成礼盒排列全组合
     * @param $groups
     * @param $comp
     * @return array
     */
    private function _createPatternRecursive($groups,$comp=array())
    {
        $current = array_pop($groups);
        $end = empty($groups);
        foreach($current as $productId)
        {
            $tmpComp = $comp;
            $tmpComp[] = $productId;
            if($end)
            {
                $this->_patternRes[] = $tmpComp;
            }
            else{
                $this->_createPatternRecursive($groups,$tmpComp);
            }
        }
    }

    /**
     * 插入组合数据
     * @param $boxId
     */
    private function _insertPatternData($boxId)
    {
        if(empty($this->_patternRes))
        {
            return;
        }
        // 获取礼盒sku中的数字部分
        $sku = $this->master->getBoxSku($boxId);
        $skuNum = preg_replace('/\D/s', '', $sku);
        if(strlen($skuNum) >= 8)
        {
            $skuNum = substr($skuNum, -8);
        }
        $leftBit= 12 - strlen($skuNum);
        $patternCount = 1;
        foreach($this->_patternRes as $group)
        {
            $patternCode = $this->extend->ean13CheckDigit($skuNum . sprintf('%0'.$leftBit.'d',$patternCount));
            foreach($group as $patterns)
            {
                foreach($patterns as $k=>$v)
                {
                    $patternData =
                        array(
                            'box_id'        =>  $boxId,
                            'barcode'       =>  $patternCode,
                            'item_id'       =>  $k,
                            'product_id'    =>  $v,
                        );
                    $this->utility->insert('box_pattern',$patternData);
                }
            }
            $patternCount ++;

        }
    }

    /**
     * 添加组品接口
     */
    public function addProduct_post(){

        $code = 0;
        $message = '';
        $data = array();
        header("Access-Control-Allow-Origin: *");
        $sku = $this->post('sku');
        $barCode = $this->post('barCode');
        $name = $this->post('name');
        $comment = $this->post('comment');
        $creater = $this->post('operator');
        $createdAt = date('Y-m-d H:i:s',time());

        // 验证参数
        if(!$this->_checkProductParam($sku,$barCode,$creater))
        {
            return;
        }

        if ($this->_createProductMaster($sku,$barCode,$name,$comment,$createdAt,$creater)){
            $code = 1;
            $message = '组品添加成功';
            $data = array();
            $out = array(
                'code'  =>  $code,
                'msg'   =>  $message,
                'data'  =>  $data,
            );
        }

        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code

    }

    /**
     * 验证组品创建参数
     * @param $sku
     * @param $barCode
     * @param $creater
     * @return bool
     */
    protected function _checkProductParam($sku,$barCode,$creater)
    {
        //参数sku验证
        if($sku == '')
        {
            $this->returnError('组品SKU不能为空');
            return false;
        }
        else if(!$this->extend->validateSku($sku))
        {
            $this->returnError('组品SKU只能由数字、字母和符号“-”“_”组成');
            return false;
        }
        else if(($this->utility->select_count('material_master',array('sku' => $sku))) > 0)
        {
            $this->returnError('组品SKU已经存在');
            return false;
        }

        //参数barCode验证
        if($barCode == '')
        {
            $this->returnError('组品条码不能为空');
            return false;
        }else if(($this->utility->select_count('material_master',array('barcode' => $barCode))) > 0)
        {
            $this->returnError('组品条码已经存在');
            return false;
        }
        //参数创建人验证
        if($creater == '')
        {
            $this->returnError('创建人不能为空');
            return false;
        }

        return true;
    }

    /**
     * 新增组品数据
     * @param $sku
     * @param $barCode
     * @param $name
     * @param $comment
     * @param $createdAt
     * @param $creater
     * @return mixed
     */
    protected function _createProductMaster($sku,$barCode,$name,$comment,$createdAt,$creater)
    {
        $result = false;
        $productData = array(
            'sku'       =>  $sku,
            'barcode'   =>  $barCode,
            'name'      =>  $name,
            'comment'   =>  $comment,
            'created_at' =>  $createdAt,
            'creater'   =>  $creater,
            'del_flg'   =>  1
        );
        try
        {
            $this->utility->insert('material_master',$productData);
        }
        catch(Exception $e)
        {
            $this->returnError('新增组品失败。'.$e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * 查询组品接口
     */
    public function index_get()
    {
        $code = 1;
        $message = '';
        $data = array();

        header("Access-Control-Allow-Origin: *");
        $searchType = $this->get('searchType');
        $pageSize = $this->get('pageSize');
        $pageNum = $this->get('pageNum');

        $data = array(
            'sku'=>$this->get('sku'),
            'barCode'=>$this->get('barCode'),
            'name'=>$this->get('name'),
            'comment'=>$this->get('comment'),
            'createdFrom' => $this->get('createdFrom'),
            'createdTo' => $this->get('createdTo'),
            'creater' => $this->get('creater'),
        );

        if($pageSize != ''){
            $limitNum = $pageSize;
        }else{
            $limitNum = 10;
        }
        if ($pageNum != ''){
            $limitStart = ($pageNum - 1) * $limitNum ;
        }else{
            $limitStart = 0;
        }

        $tableName = 'material_master';
        $result = $this->master->select_result_by_cond($data,$tableName,$searchType,$limitNum,$limitStart);
        $resultDetail = array();
        foreach ($result as $k=>$v){
                if ($v['del_flg'] == '1') {
                    $validRemove = 'Y';
                }else{
                    $validRemove = 'N';
                }
                $reformatArray = array(
                    'sku'        => $v['sku'],
                    'barCode'    => $v['barcode'],
                    'name'       => $v['name'],
                    'comment'    => $v['comment'],
                    'createdAt'  => $v['created_at'],
                    'creater'    => $v['creater'],
                    'validRemove'=> $validRemove,
                );
            array_push($resultDetail, $reformatArray);
        }
        $resultCount = $this->master->select_result_count($data,$tableName,$searchType);

        $data = array(
            'productInfo' => $resultDetail,
            'totalCount' => $resultCount
        );

        $out = array(
            'code' => 1,
            'msg' => $message,
            'data' => json_encode($data),
        );
        $this->set_response($out, REST_Controller::HTTP_OK);
    }

    /**
     * 修改组品内容
     */
    public function updateProduct_post(){
        header("Access-Control-Allow-Origin: *");
        $code = 0;
        $message = '';
        $data = array();
        $sku = $this->post('sku');
        $name = $this->post('name');
        $comment = $this->post('comment');

        if ($this->_updateProductMaster($sku,$name,$comment)){
            $code = 1;
            $message = '组品修改成功';
            $out = array(
                'code'  =>  $code,
                'msg'   =>  $message,
                'data'  =>  $data,
            );
        }else{
            $message = '组品修改失败';
        }
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code

    }

    /**
     * 修改组品数据
     * @param $sku
     * @param $name
     * @param $comment
     * @return mixed
     */
    protected function _updateProductMaster($sku,$name,$comment)
    {
        $result = false;
        $productData = array(
            'name'      =>  $name,
            'comment'   =>  $comment,
        );
        return $this->utility->update('material_master',$productData,'sku='."'$sku'");
    }

    public function removeProduct_post(){
        header("Access-Control-Allow-Origin: *");
        $code = 0;
        $message = '';
        $data = array();
        $sku = $this->post('sku');

        $flag = $this->utility->select_row('material_master','del_flg','sku='."'$sku'")->del_flg;
        if ($flag == 0){
            $this->returnError('组品已加入礼盒,不能删除');
            return false;
        }
        elseif ($this->utility->delete('material_master','sku='."'$sku'")){
            $code = 1;
            $message = '组品删除成功';
            $out = array(
                'code'  =>  $code,
                'msg'   =>  $message,
                'data'  =>  $data,
            );
        }else{
            $message = '组品删除失败';
        }
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    /**
     * 礼盒维护查询接口
     */
    public function searchGift_get()
    {
        $code = 1;
        $message = '';
        $data = array();

        header("Access-Control-Allow-Origin: *");
        $pageSize = $this->get('pageSize');
        $pageNum   = $this->get('pageNum');

        $data = array(
            'sku'         => $this->get('sku'),
            'name'        => $this->get('name'),
            'comment'     => $this->get('comment'),
            'createdFrom' => $this->get('createdFrom'),
            'createdTo'   => $this->get('createdTo'),
            'creater'     => $this->get('creater'),
            'operator'    => $this->get('operator'),
        );

        if($pageSize != ''){
            $limitNum = $pageSize;
        }else{
            $limitNum = 10;
        }

        if ($pageNum != ''){
            $limitStart = ($pageNum - 1) * $limitNum;
        }else{
            $limitStart = 0;
        }

        $searchType = 'sku';
        $tableName = 'box_master';
        $result = $this->master->select_result_by_cond($data,$tableName,$searchType,$limitNum,$limitStart);

        $resultDetail = array();
        foreach ($result as $k=>$v){
            if ($v['del_flg'] == '1') {
                $validRemove = 'Y';
            }else{
                $validRemove = 'N';
            }
            $reformatArray = array(
                'sku'        => $v['sku'],
                'name'       => $v['name'],
                'comment'    => $v['comment'],
                'createdAt'  => $v['created_at'],
                'creater'    => $v['creater'],
                'validRemove'=> $validRemove,
            );
            array_push($resultDetail, $reformatArray);
        }

        $resultCount = $this->master->select_result_count($data,$tableName,$searchType);

        $data = array(
            'boxInfo' => $resultDetail,
            'totalCount' => $resultCount
        );

        $out = array(
            'code' => 1,
            'msg' => $message,
            'data' => json_encode($data),
        );
        $this->set_response($out, REST_Controller::HTTP_OK);
    }

    /**
     * 礼盒维护查询组装项接口
     */
    public function searchGroup_get()
    {
        $code = 1;
        $message = '';
        $data = array();
        $itemId = 0;
        $dup = 0;
        header("Access-Control-Allow-Origin: *");
        $sku = $this->get('sku');

        $tableName = 'box_master';
        $searchResult = $this->master->getBoxItemDetail($sku);
        foreach ($searchResult as $k=>$v){
            if ($itemId == $v['item_id']){
                $boxInfo = array(
                    'itemId' => $v['item_id'],
                    $boxItem = array(
                        'prodSku'     => $v['sku'],
                        'prodBarCode' => $v['barcode'],
                    )
                );
                array_push($data, $boxInfo);
            }else{
                $boxInfo = array(
                    'itemId' => $v['item_id'],
                    $boxItem = array(
                        'itemId' => $v['item_id'],
                        'prodSku'     => $v['sku'],
                        'prodBarCode' => $v['barcode'],
                    )
                );
                array_push($data, $boxInfo);
                $itemId = $v['item_id'];
            }
        }

        $result = array(
            'code' => 1,
            'msg' => $message,
            'data' => json_encode($data),
        );
        $this->set_response($result, REST_Controller::HTTP_OK);
    }


    /**
     * 礼盒删除接口
     */
    public function removegift_post()
    {
        $code = 1;
        $message = '礼盒删除成功';
        $data = array();
        header("Access-Control-Allow-Origin: *");
        $boxSku = $this->post('sku');
        $operator = $this->post('operator');

        // 验证参数
        if(!$this->_checkRemoveGiftParam($boxSku,$operator))
        {
            return;
        }

        // 处理
        $boxId = $this->master->getBoxId($boxSku);
        if(!$this->_processRemoveGift($boxId))
        {
            return;
        }

        $out = array(
            'code'  =>  $code,
            'msg'   =>  $message,
            'data'  =>  $data,
        );
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    /**
     * 验证礼盒删除接口参数
     * @param $boxSku
     * @param $operator
     * @return bool
     */
    protected function _checkRemoveGiftParam($boxSku,$operator)
    {
        // 参数boxId验证
        if(is_null($boxSku))
        {
            $this->returnError('礼盒sku不能为空');
            return false;
        }

        $boxId = $this->master->getBoxId($boxSku);
        if(!$boxId)
        {
            $this->returnError('该在系统中礼盒不存在。');
            return false;
        }

        $taskInfo = $this->utility->select_row('task_master','box_id',array('box_id' => $boxId));
        if($taskInfo)
        {
            $this->returnError('该礼盒已经在组装任务中,无法删除。');
            return false;
        }

        // 参数操作人员验证
        if(is_null($operator))
        {
            $this->returnError('操作人员不能为空');
            return false;
        }

        return true;
    }

    /**
     * 处理礼盒删除
     * @param $boxId
     * @return bool
     */
    protected function _processRemoveGift($boxId)
    {
        $delCond = array(
            "box_id" => $boxId,
        );

        // 事务处理
        $this->db->trans_start();

        // 重置组品删除标记位
        try
        {
            $this->_updateProdDelFlg($boxId);
        }
        catch(Exception $e)
        {
            $this->returnError('标记位重置失败。'.$e->getMessage());
            return false;
        }

        // 删除礼盒主数据
        try
        {
            $this->utility->delete('box_master',array('id' => $boxId));
        }
        catch(Exception $e)
        {
            $this->returnError('礼盒主数据删除失败。'.$e->getMessage());
            return false;
        }

        // 删除礼盒组装项数据
        try
        {
            $this->utility->delete('box_item',$delCond);
        }
        catch(Exception $e)
        {
            $this->returnError('礼盒组装项数据删除失败。'.$e->getMessage());
            return false;
        }

        // 删除礼盒组合数据
        try
        {
            $this->utility->delete('box_pattern',$delCond);
        }
        catch(Exception $e)
        {
            $this->returnError('礼盒组合数据删除失败。'.$e->getMessage());
            return false;
        }

        $this->db->trans_complete();
        return true;
    }

    /**
     * 重置组品删除标记位
     * @param $boxId
     * @return bool
     */
    protected function _updateProdDelFlg($boxId)
    {
        $productInfo = $this->utility->select('box_item','product_id',array('box_id' => $boxId));
        if($productInfo)
        {
            foreach($productInfo as $prodId)
            {
                if(!$this->utility->select_count('box_item','product_id ='.$prodId->product_id.' and box_id <>'.$boxId))
                {
                    $update_data = array(
                        'del_flg' => 1,
                    );
                    $this->utility->update('material_master',$update_data,array('id' => $prodId->product_id));
                }
            }
        }

        return true;
    }

    /**
     * 礼盒维护查询礼盒条形码接口
     */
    public function giftBarCode_get()
    {
        $code = 1;
        $message = '';
        $data = array();

        $itemId = 1;
        header("Access-Control-Allow-Origin: *");
        $sku = $this->get('sku');

        if ($data = $this->_processGiftBarCode($sku)){
            $out = array(
                'code'  =>  $code,
                'msg'   =>  $message,
                'data'  =>  json_encode($data),
            );
        }else {
            $out = array(
            'code' => 0,
            'msg' => '礼盒组合条码查询失败',
            'data' => json_encode($data),
            );
        }
        
        $this->set_response($out, REST_Controller::HTTP_OK);
    }

    /**
     * 处理礼盒条形码
     * @param $sku
     * @return array
     */
    protected function _processGiftBarCode($sku)
    {
        $table1 = 'box_pattern bp';
        $table2 = 'box_master bm';
        $table3 = 'material_master mm';
        $join_type = 'left';
        $fields = array(
            'bp.barcode as box_barcode',
            'mm.sku',
            'mm.barcode',
            'mm.name',
        );
        $searchData = array(
            'table1'    => 'box_pattern bp',
            'table2'    => 'box_master bm',
            'table3'    => 'material_master mm',
            'cond'      => 'bm.sku = '."'$sku'",
            'on1'       => 'bp.box_id = bm.id',
            'on2'       => 'mm.id = bp.product_id',
            'join_type' => 'left'
        );
        
        $resultData = $this->utility->select_join($searchData, $fields);

        $prevBarCode = '';
        $data = array();
        foreach ($resultData as $k=>$v){

            if ($prevBarCode == $v['barcode']){
                $boxInfo = array(
                    'giftBarCode' => $v['box_barcode'],
                    $boxItem = array(
                        'prodSku'        => $v['sku'],
                        'productBarCode' => $v['barcode'],
                        'name'           => $v['name'],
                    )
                );
                array_push($data, $boxInfo);
            }else{
                $prevBarCode = $v['box_barcode'];
                $boxInfo = array(
                    'giftBarCode' => $v['box_barcode'],
                    $boxItem = array(
                        'prodSku'        => $v['sku'],
                        'productBarCode' => $v['barcode'],
                        'name'           => $v['name'],
                    )
                );
                array_push($data, $boxInfo);
            }

        }
        return $data;
    }

    /**
     * 导出礼盒SKU & BARCODE
     * @return bool
     */
    public function exportGiftBarCode_get()
    {
        header("Access-Control-Allow-Origin: *");
        $sku = $this->get('sku');
        $query = $this->master->getBoxBarCode($sku);

        if(!$query){
            return false;
        }

        // Starting the PHPExcel library
        $this->load->library('PHPExcel');
        $this->load->library('PHPExcel/IOFactory');
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->getProperties()->setTitle("export")->setDescription("none");
        // 创建第一个SHEET,设置title为box barcode,设置列名
        $currentSheet=$objPHPExcel->createSheet();
        $currentSheet1=$objPHPExcel->setactivesheetindex(0);
        $currentSheet=$objPHPExcel->getActiveSheet()->setTitle('box barcode');
        $currentSheet1->getCell('a1')->setValue("BOX SKU");
        $currentSheet1->getCell('b1')->setValue("BOX BARCODE");
//        $currentSheet1=$objPHPExcel->getActiveSheet()->getStyle('a')->getNumberFormat()
//            ->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);
//        $currentSheet1=$objPHPExcel->getActiveSheet()->getStyle('b')->getNumberFormat()
//            ->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);

        $a_net=0;
        foreach($query as $k => $value)
        {
            $sku     = $value['sku'];
            $barCode = $value['barcode'];
            $u1_net  = $a_net+2;

            $currentSheet->getCell('a'.$u1_net)->setValue(' '."$sku");
            $currentSheet->getCell('b'.$u1_net)->setValue(' '."$barCode");
            $a_net++;
        }
        $objPHPExcel->setActiveSheetIndex(0);
        $objWriter = IOFactory::createWriter($objPHPExcel, 'Excel5');
        //发送标题强制用户下载文件
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="Box BarCode_'.date('dMy').'.xls"');
        header('Cache-Control: max-age=0');
        $objWriter->save('php://output');
    }

    /**
     * 打印礼盒条形码
     */
    public function printGift_get(){
        $code = 1;
        $message = '打印条形码';
        $data = array();
        header("Access-Control-Allow-Origin: *");
        require_once(APPPATH.'third_party/libraries/tcpdf/tcpdf.php');
        require_once(APPPATH.'third_party/libraries/fpdi/fpdi.php');

        $pdf = new FPDI('p');
        $template = APPPATH.'third_party/template/blank_template.pdf';
        $font = new TCPDF_FONTS();
        $font1 = $font->addTTFfont(APPPATH.'third_party/libraries/tcpdf/fonts/Arial.ttf');

        $pdf->setSourceFile($template);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont($font1, '', 10.5);

        $sku = $this->get('sku');
        $barCode = $this->get('barCode');
        $boxBarCode = 0;
        $result = $this->master->getBoxBarCode($sku);
        foreach ($result as $k=>$v){
            if ($v['barcode'] == $barCode){
                $boxBarCode = $v['barcode'];
            }
        }

        $pdf->write1DBarcode($barCode, 'EAN13', 4.5, 1.5, 27);
        $pdf->SetXY(3.5, 11);
        $pdf->Cell(16, 3.5, $barCode,'C');

        $pdf->Output("Box".$sku.date("YmdHis",time()).".pdf", "I");
        $out = array(
            'code'  =>  1,
            'msg'   =>  '',
            'data'  =>  $data,
        );
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    /**
     * 组装项验证接口
     */
    public function prodVerify_post()
    {
        $code = 1;
        $message = '商品验证通过';
        $data = array();
        header("Access-Control-Allow-Origin: *");
        $product= $this->post('product');
        $prodType = $this->post('prodType');
        $operator = $this->post('operator');


        // 验证参数
        if(!$this->_checkProdVerifyParam($product,$prodType,$operator))
        {
            return;
        }

        // 处理
        if(!$this->_verifyProduct($product,$prodType))
        {
            return;
        }


        $out = array(
            'code'  =>  $code,
            'msg'   =>  $message,
            'data'  =>  $data,
        );
        //$out = json_encode($out);
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    /**
     * 验证组装项产品参数
     * @param $product
     * @param $prodType
     * @param $operator
     * @return bool
     */
    protected function _checkProdVerifyParam($product,$prodType,$operator)
    {
        // 礼盒id验证
        if(is_null($product))
        {
            $this->returnError('商品参数不能为空');
            return false;
        }

        // 参数录入方式验证
        if(($prodType != 'B') && ($prodType != 'S'))
        {
            $this->returnError("录入方式不合法或者为空");
            return false;
        }

        // 参数创建人验证
        if(is_null($operator))
        {
            $this->returnError('创建人不能为空');
            return false;
        }
        return true;
    }

    /**
     * 处理组装项商品验证
     * @param $product
     * @param $prodType
     * @return bool
     */
    protected function _verifyProduct($product,$prodType)
    {
        switch($prodType)
        {
            case 'B':
                if(!$this->utility->select_row('material_master','id',array('barcode' => $product)))
                {
                    $this->returnError('组品'.$product.'不存在');
                    return false;
                }
                break;
            case 'S':
                if(!$this->utility->select_row('material_master','id',array('sku' => $product)))
                {
                    $this->returnError('组品'.$product.'不存在');
                    return false;
                }
                break;
            default:
                break;
        }
        return true;
    }

    /**
     * 下载导入文件模版
     */
    public function exportTemplate_get()
    {
        header("Access-Control-Allow-Origin: *");

        $query = array(
            'sku' => 'sku0001',
            'barCode' => '100010001001',
            'name' => '商品名称',
            'comment' => '备注',
        );

        // Starting the PHPExcel library
        $this->load->library('PHPExcel');
        $this->load->library('PHPExcel/IOFactory');
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->getProperties()->setTitle("export")->setDescription("none");
        // 创建第一个SHEET,设置title为box barcode,设置列名
        $currentSheet=$objPHPExcel->createSheet();
        $currentSheet1=$objPHPExcel->setactivesheetindex(0);
        $currentSheet=$objPHPExcel->getActiveSheet()->setTitle('Template');
        $currentSheet1->getCell('a1')->setValue("SKU");
        $currentSheet1->getCell('b1')->setValue("BARCODE");
        $currentSheet1->getCell('c1')->setValue("NAME");
        $currentSheet1->getCell('d1')->setValue("COMMENT");

        $a_net=0;
        foreach($query as $k => $value)
        {
            $u1_net = $a_net+2;

            $currentSheet->getCell('a'.$u1_net)->setValue($query['sku']);
            $currentSheet->getCell('b'.$u1_net)->setValue(' '.$query['barCode']);
            $currentSheet->getCell('c'.$u1_net)->setValue($query['name']);
            $currentSheet->getCell('d'.$u1_net)->setValue($query['comment']);
            $a_net++;
        }
        $objPHPExcel->setActiveSheetIndex(0);
        $objWriter = IOFactory::createWriter($objPHPExcel, 'Excel5');
        //发送标题强制用户下载文件
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="Template_'.date('dMy His').'.xls"');
        header('Cache-Control: max-age=0');
        $objWriter->save('php://output');
    }

    /**
     * 组品文件导入接口
     */
    public function importProduct_post(){

        $code = 1;
        header("Access-Control-Allow-Origin: *");
        $operator = $this->post('operator');
        // 验证参数
        if(!$this->_chkImportProductParam($operator))
        {
            return;
        }
        $config['upload_path']      = './upload/';
        $config['allowed_types']    = 'xls|xlsx';
        //文件上传，保存文件
        $this->load->library('upload',$config);
        if ($this->upload->do_upload('fileName')) {
            $filePath = $this->upload->data('full_path');
        }
        else
        {
            $this->returnError($this->upload->display_errors(NULL, NULL));
            return;
        }

        //处理文件导入
        try
        {
            $countRes = $this->_excelInput($filePath,$operator);
            unlink($filePath);
        }
        catch(Exception $e)
        {
            $this->returnError('文件导入失败。'.$e->getMessage());
            return;
        }

        $out = array(
            'code' => $code,
            'msg'  => '成功导入'.$countRes['count'].'条,失败'.$countRes['errCount'].'条',
            'data'  => array(),
        );
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    /**
     * 处理文件导入
     * @param $filePath
     * @param $operator
     * @return array|bool
     */
    protected function _excelInput($filePath,$operator){
        $PHPReader = new PHPExcel_Reader_Excel2007();
        if(!$PHPReader->canRead($filePath)){
            $PHPReader = new PHPExcel_Reader_Excel5();
            if(!$PHPReader->canRead($filePath)){
                $result = array(
                    'count'     =>  0,
                    'errCount'  =>  0,
                );
                return $result;
            }
        }
        $count = 0;
        $errCount = 0;
        // 加载excel文件
        $PHPExcel = $PHPReader->load($filePath);
        // 读取excel文件中的第一个工作表
        $currentSheet = $PHPExcel->getSheet(0);
        // 取得一共有多少行
        $allRow = $currentSheet->getHighestRow();
        // 从第二行开始输出，因为excel表中第一行为列名
        for($currentRow = 2;$currentRow <= $allRow;$currentRow++){
            $data = array(
                'sku'           =>  $currentSheet->getCellByColumnAndRow(0,$currentRow)->getValue(),
                'barcode'       =>  $currentSheet->getCellByColumnAndRow(1,$currentRow)->getValue(),
                'name'          =>  $currentSheet->getCellByColumnAndRow(2,$currentRow)->getValue(),
                'comment'       =>  $currentSheet->getCellByColumnAndRow(3,$currentRow)->getValue(),
                'created_at'    =>  date('Y-m-d H:i:s',time()),
                'creater'       =>  $operator,
            );
            if(!$this->_insertProduct($data))
            {
                $errCount ++;
                continue;
            }else{
                $count ++ ;
            }
        }
        $result = array(
            'count'     =>  $count,
            'errCount'  =>  $errCount,
        );
        return $result;
    }

    /**
     * 插入组品操作
     * @param $data
     * @return bool
     */
    protected function _insertProduct($data){

        if((is_null($data['sku'])) || (is_null($data['barcode'])))
        {
            return false;
        }
        // 去空格
        else if((trim($data['sku']) == '') || (trim($data['barcode']) ==''))
        {
            return false;
        }
        else if(!$this->extend->validateSku($data['sku']))
        {
            return false;
        }
        else if(!$this->extend->validateBarcode($data['barcode']))
        {
            return false;
        }
        else if($this->_isProductDuplicated($data))
        {
            return false;
        }
        else
        {
            $this->utility->insert('material_master',$data);
            return true;
        }
    }

    /**
     * 验证组品导入接口参数
     * @param $operator
     * @return bool
     */
    protected function _chkImportProductParam($operator)
    {
        // 参数操作人员验证
        if(is_null($operator))
        {
            $this->returnError('操作人员不能为空');
            return false;
        }
        return true;
    }

    /**
     * 检查组品信息是否重复
     * @param $data
     * @return bool
     */
    protected function _isProductDuplicated($data)
    {
        if($this->utility->select_count('material_master',array('sku' => $data['sku'])) > 0)
        {
            return true;
        }
        else if ($this->utility->select_count('material_master',array('barcode' => $data['barcode'])) > 0)
        {
            return true;
        }
        else
        {
            return false;
        }

    }

}
