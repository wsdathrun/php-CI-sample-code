<?php
/**
 * Created by PhpStorm.
 * User: Eathan
 * Date: 16/12/7
 * Time: 下午6:26
 */

class Master_model extends CI_Model
{
    function __construct()
    {
        parent::__construct();
        $this->load->model('Utility_model','utility');

    }

    /**
     * 按条件搜索出符合条件的组品/礼盒商品
     * @param $searchType
     * @param $sku
     * @param $barCode
     * @param $name
     * @param $comment
     * @param $createdFrom
     * @param $createdTo
     * @param $creater
     * @return mixed
     */
    public function select_result_by_cond($data,$tableName,$searchType,$limitNum=null,$limitStart=null){
        $this->db->select('*');
        if ($data['createdFrom']) {
            $this->db->where('created_at >= ', $data['createdFrom']);
        }
        if ($data['createdTo']) {
            if ($data['createdTo'] == $data['createdFrom']) {
                $this->db->where('created_at <= ', $data['createdTo'].' 23:59:59');
            }else{
                $this->db->where('created_at <= ', $data['createdTo'].' 23:59:59');
            }
        }

        if ($data['name']){
            $this->db->like('name', $data['name']);
        }
        if ($data['comment']){
            $this->db->like('comment', $data['comment']);
        }
        if ($data['creater']){
            $this->db->like('creater', $data['creater']);
        }
        if ($searchType == 'sku'){
            $this->db->like('sku', $data['sku']);
        }elseif($searchType == 'barCode'){
            $this->db->like('barcode',$data['barCode']);
        }
        if(($limitNum > 0) || ($limitStart > 0))
        {
            $this->db->limit($limitNum,$limitStart);
        }

        $this->db->order_by('created_at desc, id desc');
        $result= $this->db->get($tableName);
        return $result->result_array();
    }

    /**
     * 统计符合条件的总数
     * @param $data
     * @param $tableName
     * @param $searchType
     * @return mixed
     */
    public function select_result_count($data,$tableName,$searchType){
        $this->db->select('*');
        if ($data['createdFrom']) {
            $this->db->where('created_at >= ', $data['createdFrom']);
        }
        if ($data['createdTo']) {
            if ($data['createdTo'] == $data['createdFrom']) {
                $this->db->where('created_at <= ', $data['createdTo'].' 23:59:59');
            }else{
                $this->db->where('created_at <= ', $data['createdTo'].' 23:59:59');
            }
        }
        if ($data['name']){
            $this->db->like('name', $data['name']);
        }
        if ($data['comment']){
            $this->db->like('comment', $data['comment']);
        }
        if ($data['creater']){
            $this->db->like('creater', $data['creater']);
        }
        if ($searchType == 'sku'){
            $this->db->like('sku', $data['sku']);
        }elseif($searchType == 'barCode'){
            $this->db->like('barcode',$data['barCode']);
        }

        $this->db->order_by('created_at desc, id desc');
        $result= $this->db->get($tableName);
        return $result->num_rows();
    }

    /**
     * 创建礼盒组装项
     * @param $boxId
     * @param $itemId
     * @param $productId
     * @return int
     */
    public function addBoxItem($boxId,$itemId,$productId)
    {
        $updateData = array
        (
            'id'    => $productId,
            'del_flg'   =>0
        );
        $this->utility->update('material_master',$updateData,array('id' => $productId));

        $itemData = array(
            "item_id"       =>  $itemId,
            "box_id"        =>  $boxId,
            "product_id"    =>  $productId,
        );
        return $this->utility->insert('box_item',$itemData);
    }

    /**
     * 自增并获取唯一8位组合编码
     * @return int
     */
    public function getPatternCode()
    {
        $this->db->simple_query('update config set val_int=(val_int+1)  where config.key="pattern_code"');
        $code = $this->utility->select_row('config','val_int',array( 'key' => 'pattern_code'));
        return sprintf("%08d",$code->val_int);
    }

    /**
     * 返回礼盒组装项数组
     * @param $boxId
     * @return array
     */
    public function getBoxItemGroups($boxId)
    {
        $result = array();
        $group = array();
        $itemNum = 1;
        $itemInfo = $this->utility->select('box_item','item_id,product_id',array('box_id' => $boxId),null,null,array('item_id' => 'asc'));
        foreach ($itemInfo as $k=>$v)
        {
            if($itemNum != $v->item_id)
            {
                $result[] = $group;
                $group = array();
                $itemNum ++;
            }
            $group[] =
                array(
                    $v->item_id => $v->product_id,
                );
        }
        $result[] = $group;
        return array_reverse($result);
    }

    /**
     * 获取礼盒sku
     * @param $boxId
     * @return null|string
     */
    public function getBoxSku($boxId)
    {

        if(!$boxId)
        {
            return null;
        }
        $boxInfo = $this->utility->select_row('box_master','sku', array('id' => $boxId));
        if($boxInfo)
        {
            return $boxInfo->sku;
        }
        else
        {
            return null;
        }
    }

    /**
     * 获取礼盒id
     * @param $boxSku
     * @return null|string
     */
    public function getBoxId($boxSku)
    {

        if(!$boxSku)
        {
            return null;
        }
        $boxInfo = $this->utility->select_row('box_master','id', array('sku' => $boxSku));
        if($boxInfo)
        {
            return $boxInfo->id;
        }
        else
        {
            return null;
        }
    }

    /**
     * 查找礼盒SKU组装项内容
     * @param $data
     * @param $tableName
     * @return mixed
     */
    public function getBoxItemDetail($sku){
        $this->db->select('b.item_id, m.sku, m.barcode')
                 ->from('material_master m')
                 ->join('box_item b', 'b.product_id = m.id', 'left')
                 ->join('box_master bm', 'bm.id = b.box_id', 'left')
                 ->where('bm.sku = ', $sku);
        return $result = $this->db->get()->result_array();
    }

    /**
     * 查找礼盒SKU,条码
     * @param $sku
     * @return mixed
     */
    public function getBoxBarCode($sku){
        $this->db->select('sku, barcode')
            ->from('box_master bm')
            ->join('box_pattern bp', 'bp.box_id = bm.id', 'left')
            ->where('sku = ', $sku)
            ->group_by('barcode');
        return $result = $this->db->get()->result_array();
    }

}