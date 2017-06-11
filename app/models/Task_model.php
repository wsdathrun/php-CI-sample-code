<?php
/**
 * Created by PhpStorm.
 * User: philips
 * Date: 16/12/11
 * Time: 下午3:59
 */

class Task_model extends CI_Model
{
    function __construct()
    {
        parent::__construct();
        $this->load->model('Utility_model', 'utility');
    }

    /**
     * 获取礼盒组装项记录的id
     * @param $itemId
     * @param $boxId
     * @param $productId
     * @return int
     */
    public function getBoxItemId($itemId,$boxId,$productId)
    {
        $condData = array(
            'item_id'       =>  $itemId,
            'box_id'        =>  $boxId,
            'product_id'    =>  $productId,
        );
        $boxItemInfo = $this->utility->select_row('box_item','id',$condData);
        if($boxItemInfo)
        {
            return $boxItemInfo->id;
        }
        else
        {
            return 0;
        }
    }

    /**
     * 创建组装任务组装项
     * @param $taskId
     * @param $itemId
     * @param $qty
     * @return int
     */
    public function addTaskItem($taskId,$itemId,$qty)
    {
        $itemData = array(
            "item_id"       =>  $itemId,
            "task_id"       =>  $taskId,
            "qty"           =>  $qty,
        );
        return $this->utility->insert('task_item',$itemData);
    }

    /**
     * 获取组装任务组装项明细
     * @param $taskId
     * @return array
     */
    public function getTaskItemInfo($taskId)
    {
        $result = array();
        $sql = '
        select bi.item_id,bi.product_id,ti.qty as qty from task_item ti 
        left join box_item bi on ti.item_id = bi.id 
        where task_id = "' .$taskId . '" order by qty desc;
        ';
        $query = $this->db->query($sql);
        $records = $query->result();

        // 对搜索结果进行进一步排序
        while(count($records))
        {
            $item_id = reset($records)->item_id;
            foreach($records as $k=>$row)
            {
                if($row->item_id == $item_id)
                {
                    $result[] = $row;
                    unset($records[$k]);
                }
            }
        }
        return $result;
    }

    /**
     * 获取组装任务的组合信息
     * @param $taskId
     * @return array
     */
    public function getTaskPattern($taskId)
    {
        $result = $this->utility->select('task_pattern','barcode,qty', array( 'task_id'   =>  $taskId));
        return $result;
    }

    /**
     * 入库更新库存
     * @param $barcode
     * @param $qty
     * @param $operator
     */
    public function addInventory($barcode,$qty,$operator)
    {
        $sql = 'INSERT INTO inventory (barcode,qty) 
          VALUES ("'.$barcode.'","'.$qty.'") 
          ON DUPLICATE KEY UPDATE
          qty=(qty+'.$qty.')';
        $this->db->query($sql);
        $this->_addMovementLog($barcode,$qty,MOVEMENT_TYOE_IN,$operator);
    }

    /**
     * 出库更新库存
     * @param $barcode
     * @param $qty
     * @param $operator
     */
    public function removeInventory($barcode,$qty,$operator)
    {
        $sql = 'INSERT INTO inventory (barcode,qty) 
          VALUES ("'.$barcode.'","'.$qty.'") 
          ON DUPLICATE KEY UPDATE
          qty=(qty-'.$qty.')';
        $this->db->query($sql);
        $this->_addMovementLog($barcode,$qty,MOVEMENT_TYOE_OUT,$operator);
    }

    /**
     * 记录库存移动日志
     * @param $barcode
     * @param $qty
     * @param $type
     * @param $operator
     */
    protected function _addMovementLog($barcode,$qty,$type,$operator)
    {
        $logData = array(
            'barcode'       =>  $barcode,
            'type'          =>  $type,
            'qty'           =>  $qty,
            'operator'      =>  $operator,
            'created_at'    =>  date('Y-m-d H:i:s',time()),
        );
        $this->utility->insert('movement_history',$logData);
    }


    /**
     * 自增并获取唯一组装任务id
     * @return int
     */
    public function generateTaskId()
    {
        $this->db->simple_query('update config set val_int=(val_int+1)  where config.key="task_id"');
        $code = $this->utility->select_row('config','val_int',array( 'key' => 'task_id'));
        return 'T' . sprintf("%08d",$code->val_int);
    }

    /**
     * 查找符合条件的组装任务信息
     * @param $fields
     * @param null $limitNum
     * @param null $limitStart
     * @return mixed
     */
    public function getTaskDetail($fields, $limitNum=null, $limitStart=null){
        $this->db->select('t.id, b.name, t.qty, t.comment, t.created_at, t.creater, t.status, b.del_flg');
        $this->db->join('box_master b', 'b.id = t.box_id', 'left');
        if ($fields['taskId']) {
            $this->db->like('t.id', $fields['taskId']);
        }
        if ($fields['boxName']) {
            $this->db->like('b.name', $fields['boxName']);
        }
        if ($fields['qty']) {
            $this->db->like('t.qty', $fields['qty']);
        }
        if ($fields['comment']) {
            $this->db->like('t.comment', $fields['comment']);
        }
        if ($fields['createdFrom']) {
            $this->db->where('t.created_at >= ', $fields['createdFrom']);
        }
        if ($fields['createdTo']) {
            if ($fields['createdTo'] == $fields['createdFrom']) {
                $this->db->where('t.created_at <= ', $fields['createdTo'].' 23:59:59');
            }else{
                $this->db->where('t.created_at <= ', $fields['createdTo']);
            }
        }
        if ($fields['status'] != '') {
            $this->db->where('t.status', $fields['status']);
        }

        if ($fields['creater']) {
            $this->db->like('t.creater', $fields['creater']);
        }
        if(($limitNum > 0) || ($limitStart > 0))
        {
            $this->db->limit($limitNum,$limitStart);
        }
        $this->db->order_by('t.created_at', 'desc');
        $result= $this->db->get('task_master t');
        return $result->result_array();
    }

    /**
     * 统计满足条件的组装任务个数
     * @param $fields
     * @return mixed
     */
    public function getTaskCount($fields){
        $this->db->select('t.id, b.name, t.qty, t.comment, t.created_at, t.creater, t.status, b.del_flg')
            ->from('task_master t')
            ->join('box_master b', 'b.id = t.box_id', 'left');
        if ($fields['taskId']) {
            $this->db->like('t.id', $fields['taskId']);
        }
        if ($fields['boxName']) {
            $this->db->like('b.name', $fields['boxName']);
        }
        if ($fields['qty']) {
            $this->db->like('t.qty', $fields['qty']);
        }
        if ($fields['comment']) {
            $this->db->like('t.comment', $fields['comment']);
        }
        if ($fields['createdFrom']) {
            $this->db->where('t.created_at >= ', $fields['createdFrom']);
        }
        if ($fields['createdTo']) {
            if ($fields['createdTo'] == $fields['createdFrom']) {
                $this->db->where('t.created_at <= ', $fields['createdTo'].' 23:59:59');
            }else{
                $this->db->where('t.created_at <= ', $fields['createdTo']);
            }
        }
        if ($fields['creater']) {
            $this->db->like('t.creater', $fields['creater']);
        }
        if ($fields['status'] != '') {
            $this->db->where('t.status', $fields['status']);
        }
        return $result = $this->db->get()->num_rows();
    }

    /**
     * 获取组装项内容
     * @param $taskId
     * @return mixed
     */
    public function getTaskItemDetail($taskId){
        $this->db->select('bi.item_id, mm.sku, mm.barcode, ti.qty')
            ->from('task_item ti')
            ->join('box_item bi', 'bi.id = ti.item_id', 'left')
            ->join('material_master mm', 'mm.id = bi.product_id', 'left')
            ->where('ti.task_id = ', $taskId);
        return $result = $this->db->get()->result_array();
    }

    /**
     * 获取组装项任务中的信息
     * @param $taskId
     * @return mixed
     */
    public function getContentDetail($taskId){
        $this->db->select('bp.barcode, bp.product_id, mm.sku, mm.barcode as mbarcode, mm.name, tp.qty, 
                    tm.barcocde_print_time, tm.barcocde_print_operator, tm.task_print_time, tm.task_print_operator')
            ->from('box_pattern bp')
            ->join('material_master mm', 'mm.id=bp.product_id', 'left')
            ->join('task_pattern tp', 'tp.barcode = bp.barcode', 'left')
            ->join('task_master tm', 'tm.id = tp.task_id', 'left')
            ->where('tm.id = ', $taskId);
        return $result = $this->db->get()->result_array();
    }

    /**
     * 获取组装任务中条码信息
     * @param $taskId
     * @return mixed
     */
    public function getBarCodeDetail($taskId){
        $this->db->select('bp.barcode, tp.qty')
            ->from('box_pattern bp')
            ->join('task_pattern tp', 'tp.barcode = bp.barcode', 'left')
            ->join('task_master tm', 'tm.id = tp.task_id', 'left')
            ->where('tm.id = ', $taskId)
            ->group_by('bp.barcode');
        return $result = $this->db->get()->result_array();
    }

    /**
     * 获取组装任务中的组品信息
     * @param $taskId
     * @return mixed
     */
    public function getContentProduct($taskId){
        $this->db->select('bp.barcode, bp.product_id, mm.sku, mm.barcode as mbarcode, mm.name, sum(tp.qty) as qty')
            ->from('box_pattern bp')
            ->join('material_master mm', 'mm.id=bp.product_id', 'left')
            ->join('task_pattern tp', 'tp.barcode = bp.barcode', 'left')
            ->join('task_master tm', 'tm.id = tp.task_id', 'left')
            ->where('tm.id = ', $taskId)
            ->group_by('mm.sku');
        return $result = $this->db->get()->result_array();
    }

    /**
     * 查找组装任务基础数据
     * @param $taskId
     * @return mixed
     */
    public function getTaskBasicDetail($taskId){
        $this->db->select('tm.id, tm.status, bp.barcode, bp.product_id, mm.sku, mm.barcode as mbarcode, mm.name, tm.qty, bm.sku, bm.name, tm.created_at, tm.creater')
            ->from('box_pattern bp')
            ->join('material_master mm', 'mm.id=bp.product_id', 'left')
            ->join('task_pattern tp', 'tp.barcode = bp.barcode', 'left')
            ->join('task_master tm', 'tm.id = tp.task_id', 'left')
            ->join('box_master bm', 'bm.id = tm.box_id', 'left')
            ->where('tm.id = ', $taskId);
        return $result = $this->db->get()->row_array();
    }

    /**
     * 获取库存数
     * @param $barcode
     * @return mixed
     */
    public function getInventoryQty($barcode){
        $this->db->select('qty')
            ->from('inventory')
            ->where('barcode = ', $barcode);
        return $result = $this->db->get()->row_array();
    }
}