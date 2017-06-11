<?php
/**
 * Created by PhpStorm.
 * User: philips
 * Date: 16/12/6
 * Time: 下午5:31
 */

class Utility_model extends CI_Model
{
    function __construct()
    {
        parent::__construct();
    }

    /**
     * 查询一条记录
     * @param $table
     * @param $fields
     * @param $cond
     * @param array $order
     * @return mixed
     */

    function select_row($table,$fields,$cond,$order=array()){
        $this->db->select($fields);
        $this->db->where($cond);
        if(!empty($order)){
            foreach($order as $key=>$val){
                $this->db->order_by($key,$val);
            }
        }
        $query = $this->db->get($table);
        $result = $query->row();
        return $result;
    }

    /**
     * 查询多条记录
     * @param $table
     * @param $fields
     * @param $cond
     * @param null $limitNum
     * @param null $limitStart
     * @param array $order
     * @return mixed
     */

    function select($table,$fields,$cond,$limitNum=null,$limitStart=null,$order=array()){
        $this->db->select($fields);
        $this->db->where($cond);
        if(($limitNum > 0) && ($limitStart > 0))
        {
            $this->db->limit($limitNum,$limitStart);
        }
        if(!empty($order)){
            foreach($order as $key=>$val){
                $this->db->order_by($key,$val);
            }
        }
        $query = $this->db->get($table);
        if($query->num_rows())
        {
            return $query->result();
        }
        else
        {
            return null;
        }
    }

    /**
     * 连表查询
     * @param $table1
     * @param $table2
     * @param $fields
     * @param $on
     * @param $cond
     * @param $join_type
     * @param array $order
     * @return mixed
     */
    function select_join1($table1,$table2,$table3,$fields,$on,$cond,$join_type,$order=array()){
        $this->db->select($fields);
        $this->db->from($table1);
        $this->db->join($table2, $on,$join_type);
        $this->db->join($table3, $on,$join_type);
        $this->db->where($cond);
        if(!empty($order)){
            foreach($order as $key=>$val){
                $this->db->order_by($key,$val);
            }
        }
        $query = $this->db->get();
        $result = $query->result_array();
        return $result;
    }

    function select_join($searchData,$fields,$order=array()){
        $this->db->select($fields);
        $this->db->from($searchData['table1']);
        $this->db->join($searchData['table2'], $searchData['on1'],$searchData['join_type']);
        $this->db->join($searchData['table3'], $searchData['on2'],$searchData['join_type']);
        $this->db->where($searchData['cond']);
        if(!empty($order)){
            foreach($order as $key=>$val){
                $this->db->order_by($key,$val);
            }
        }
        $query = $this->db->get();
        $result = $query->result_array();
        return $result;
    }

    /**
     * 计数
     * @param $table
     * @param array $cond
     * @return mixed
     */
    function select_count($table,$cond=array()){
        if ($cond){
            $this->db->where($cond);
        }
        $query = $this->db->from($table);
        $result= $query->count_all_results();
        return $result;
    }

    /**
     * 插入方法
     * @param $table
     * @param $data
     * @return mixed
     */
    function insert($table,$data){
        $result = $this->db->insert($table,$data);
        $id = $this->db->insert_id();
        return $id;
    }

    /**
     * 更新
     * @param $table
     * @param $cond
     * @param $data
     * @param int $str_flag
     * @return mixed
     */
    function update($table,$data,$cond,$str_flag=0){
        if($str_flag){
            foreach($str_flag as $k=>$v){
                $result = $this->db->update($table,$data,array($cond=>$v));
            }
        }else{
            $result = $this->db->update($table,$data,$cond);
        }
        return $result;
    }

    /**
     * 删除
     * @param $table
     * @param $cond
     * @param int $str_flag
     * @return mixed
     */
    function delete($table,$cond,$str_flag=0){
        if($str_flag){
            foreach($str_flag as $k=>$v){
                $result = $this->db->delete($table,array($cond=>$v));
            }
        }else{
            $result = $this->db->delete($table,$cond);
        }
        return $result;
    }

    /**
     * 根据sku或者条形码获取产品Id
     * @param $param
     * @param $type     'S'=sku, 'B'=barCode
     * @return int
     */
    public function getProductId($param,$type)
    {

        switch ($type)
        {
            case 'B':
                $prodInfo = $this->utility->select_row('material_master','id',array('barcode' => $param));
                if($prodInfo)
                {
                    $result = $prodInfo->id;
                }
                else{
                    $result = 0;
                }
                break;
            case 'S':
                $prodInfo = $this->utility->select_row('material_master','id',array('sku' => $param));
                if($prodInfo)
                {
                    $result = $prodInfo->id;
                }
                else{
                    $result = 0;
                }
                break;
            default:
                break;
        }
        return $result;

    }

}
